<?php
declare(strict_types=1);

function normalizeProductCatalogFields(PDO $db): void
{
    $migrationKey = 'productos_nombre_unificado_v1';
    $db->exec("CREATE TABLE IF NOT EXISTS erp_data_migrations (migration_key VARCHAR(120) NOT NULL PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->beginTransaction();
    try {
        $mark = $db->prepare('INSERT IGNORE INTO erp_data_migrations(migration_key) VALUES(?)');
        $mark->execute([$migrationKey]);
        if ($mark->rowCount() === 1) {
            $db->exec("UPDATE products SET name=TRIM(description) WHERE (name IS NULL OR TRIM(name)='') AND description IS NOT NULL AND TRIM(description)<>''");
            $db->exec("UPDATE products SET description='' WHERE description IS NOT NULL AND LOWER(TRIM(description))=LOWER(TRIM(name))");
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function normalizeUnifiedProductNamesV2(PDO $db): void
{
    $migrationKey='productos_nombre_unificado_v2';
    $db->exec("CREATE TABLE IF NOT EXISTS erp_data_migrations (migration_key VARCHAR(120) NOT NULL PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done=$db->prepare('SELECT 1 FROM erp_data_migrations WHERE migration_key=?');
    $done->execute([$migrationKey]);
    if($done->fetchColumn())return;

    $db->beginTransaction();
    try{
        $rows=$db->query('SELECT id,sku,name,description FROM products FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
        $update=$db->prepare('UPDATE products SET name=?,description=? WHERE id=?');
        foreach($rows as $row){
            $sku=trim((string)($row['sku']??''));
            $name=trim((string)($row['name']??''));
            $description=trim((string)($row['description']??''));
            $canonical=$name!==''?$name:$description;
            if($sku!==''&&$canonical!==''){
                $quoted=preg_quote($sku,'/');
                $canonical=preg_replace('/^\[\s*'.$quoted.'\s*\]\s*/iu','',$canonical)??$canonical;
                $canonical=preg_replace('/^'.$quoted.'\s*[-–—·:]\s*/iu','',$canonical)??$canonical;
            }
            if($canonical==='')$canonical=$sku!==''?$sku:'Producto';
            $technical=$description;
            if(mb_strtolower($technical,'UTF-8')===mb_strtolower($canonical,'UTF-8')||($sku!==''&&preg_match('/^\[\s*'.preg_quote($sku,'/').'\s*\]\s*/iu',$technical)))$technical='';
            $update->execute([$canonical,$technical,(int)$row['id']]);
        }
        $db->exec("UPDATE quote_items qi JOIN products p ON p.id=qi.product_id SET qi.description=p.name WHERE qi.product_id IS NOT NULL AND TRIM(COALESCE(p.name,''))<>''");
        $mark=$db->prepare('INSERT INTO erp_data_migrations(migration_key) VALUES(?)');
        $mark->execute([$migrationKey]);
        $db->commit();
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}

/**
 * Importa una sola vez el catalogo ADI validado.
 *
 * La carga es deliberadamente conservadora: nunca modifica productos que ya
 * existen, no crea movimientos de stock y calcula los precios de venta usando
 * las reglas vigentes de cada lista/categoria.
 */
function importAdiProducts20260916(PDO $db, string $erpRoot): void
{
    $importKey = 'productos_adi_2026_09_16_seguros_v1';
    $csvPath = $erpRoot . '/database/data/2026_09_16_productos_adi_seguros.csv';
    if (!is_file($csvPath)) {
        return;
    }

    $db->exec("CREATE TABLE IF NOT EXISTS erp_data_imports (
        import_key VARCHAR(120) NOT NULL PRIMARY KEY,
        imported_rows INT UNSIGNED NOT NULL DEFAULT 0,
        skipped_rows INT UNSIGNED NOT NULL DEFAULT 0,
        details TEXT NULL,
        imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $done = $db->prepare('SELECT 1 FROM erp_data_imports WHERE import_key=?');
    $done->execute([$importKey]);
    if ($done->fetchColumn()) {
        return;
    }

    $handle = fopen($csvPath, 'rb');
    if ($handle === false) {
        throw new RuntimeException('No se pudo abrir el catalogo ADI validado.');
    }

    $headers = fgetcsv($handle, 0, ',', '"', '\\');
    if ($headers !== ['sku', 'name', 'description', 'brand', 'category', 'cost_usd']) {
        fclose($handle);
        throw new RuntimeException('El catalogo ADI no tiene el formato esperado.');
    }

    $rows = [];
    while (($values = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        if (count($values) !== count($headers)) {
            continue;
        }
        $row = array_combine($headers, $values);
        if ($row === false || trim((string)$row['sku']) === '') {
            continue;
        }
        $rows[] = $row;
    }
    fclose($handle);

    $db->beginTransaction();
    try {
        $insertBrand = $db->prepare('INSERT IGNORE INTO product_brands(name,active) VALUES(?,1)');
        $insertCategory = $db->prepare('INSERT IGNORE INTO product_categories(name,active) VALUES(?,1)');
        foreach ($rows as $row) {
            $insertBrand->execute([trim((string)$row['brand'])]);
            $insertCategory->execute([trim((string)$row['category'])]);
        }

        $categories = [];
        foreach ($db->query('SELECT id,name FROM product_categories') as $category) {
            $categories[strtolower(trim((string)$category['name']))] = (int)$category['id'];
        }

        $lists = $db->query('SELECT id,COALESCE(markup_percentage,0) markup_percentage FROM price_lists WHERE active=1 ORDER BY id')->fetchAll();
        $rules = [];
        foreach ($db->query('SELECT price_list_id,category_id,percentage FROM product_category_price_rules') as $rule) {
            $rules[(int)$rule['category_id']][(int)$rule['price_list_id']] = (float)$rule['percentage'];
        }

        $findProduct = $db->prepare("SELECT id FROM products WHERE sku IS NOT NULL AND LOWER(TRIM(sku))=LOWER(TRIM(?)) LIMIT 1");
        $insertProduct = $db->prepare("INSERT INTO products
            (category_id,product_type,sku,name,brand,description,unit,cost_usd,favorite,track_stock,stock_quantity,source_key,active)
            VALUES(?,'bienes',?,?,?,?,? ,?,0,1,0,?,1)");
        $insertInventory = $db->prepare('INSERT IGNORE INTO inventory_items(product_id,track_stock) VALUES(?,1)');
        $insertPrice = $db->prepare("INSERT INTO product_prices(product_id,price_list_id,price,currency)
            VALUES(?,?,?,'USD') ON DUPLICATE KEY UPDATE price=VALUES(price),currency='USD',updated_at=CURRENT_TIMESTAMP");

        $imported = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $sku = trim((string)$row['sku']);
            $findProduct->execute([$sku]);
            if ($findProduct->fetchColumn()) {
                $skipped++;
                continue;
            }

            $categoryKey = strtolower(trim((string)$row['category']));
            $categoryId = $categories[$categoryKey] ?? null;
            if ($categoryId === null) {
                throw new RuntimeException('No se encontro la categoria del SKU ' . $sku . '.');
            }

            $productName = trim((string)$row['name']) ?: trim((string)$row['description']);
            $technicalDetail = trim((string)$row['description']);
            if (mb_strtolower($technicalDetail) === mb_strtolower($productName)) $technicalDetail = '';
            $cost = round(max(0, (float)$row['cost_usd']), 2);
            $sourceKey = 'adi-20260916-' . substr(hash('sha256', strtolower($sku)), 0, 32);
            $insertProduct->execute([
                $categoryId,
                $sku,
                $productName,
                trim((string)$row['brand']),
                $technicalDetail,
                'Unidad',
                $cost,
                $sourceKey,
            ]);
            $productId = (int)$db->lastInsertId();
            $insertInventory->execute([$productId]);

            foreach ($lists as $list) {
                $listId = (int)$list['id'];
                $markup = $rules[$categoryId][$listId] ?? (float)$list['markup_percentage'];
                $price = round($cost * (1 + $markup / 100), 2);
                $insertPrice->execute([$productId, $listId, $price]);
            }
            $imported++;
        }

        $details = json_encode([
            'archivo' => basename($csvPath),
            'filas_validadas' => count($rows),
            'importados' => $imported,
            'omitidos_por_sku_existente' => $skipped,
            'conflictos_excluidos' => 32,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $saveImport = $db->prepare('INSERT INTO erp_data_imports(import_key,imported_rows,skipped_rows,details) VALUES(?,?,?,?)');
        $saveImport->execute([$importKey, $imported, $skipped, $details]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}
