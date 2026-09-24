<?php
declare(strict_types=1);

/**
 * Importa una sola vez el catalogo Shelly recibido en septiembre de 2026.
 *
 * La carga nunca sobrescribe productos existentes: el SKU es la identidad
 * estable. Los costos vacios del archivo se conservan como cero para que el
 * producto pueda completarse posteriormente desde su ficha.
 */
function importShellyProducts20260924(PDO $db, string $erpRoot): void
{
    $importKey = 'productos_shelly_julio_2025_v1';
    $csvPath = $erpRoot . '/database/data/2026_09_24_productos_shelly.csv';
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
        throw new RuntimeException('No se pudo abrir el catalogo Shelly.');
    }

    $headers = fgetcsv($handle, 0, ',', '"', '\\');
    if ($headers !== ['brand', 'sku', 'name', 'cost_usd']) {
        fclose($handle);
        throw new RuntimeException('El catalogo Shelly no tiene el formato esperado.');
    }

    $rows = [];
    while (($values = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        if (count($values) !== count($headers)) {
            continue;
        }
        $row = array_combine($headers, $values);
        if ($row === false || trim((string)$row['sku']) === '' || trim((string)$row['name']) === '') {
            continue;
        }
        $rows[] = $row;
    }
    fclose($handle);

    $db->beginTransaction();
    try {
        $brand = 'Shelly';
        $category = 'Domótica Shelly';
        $db->prepare('INSERT IGNORE INTO product_brands(name,active) VALUES(?,1)')->execute([$brand]);
        $db->prepare('INSERT IGNORE INTO product_categories(name,active) VALUES(?,1)')->execute([$category]);

        $categoryQuery = $db->prepare('SELECT id FROM product_categories WHERE LOWER(TRIM(name))=LOWER(TRIM(?)) LIMIT 1');
        $categoryQuery->execute([$category]);
        $categoryId = (int)$categoryQuery->fetchColumn();
        if (!$categoryId) {
            throw new RuntimeException('No se pudo crear la categoria Domótica Shelly.');
        }

        $lists = $db->query('SELECT id,COALESCE(markup_percentage,0) markup_percentage FROM price_lists WHERE active=1 ORDER BY id')->fetchAll();
        $rules = [];
        foreach ($db->query('SELECT price_list_id,category_id,percentage FROM product_category_price_rules') as $rule) {
            $rules[(int)$rule['category_id']][(int)$rule['price_list_id']] = (float)$rule['percentage'];
        }
        $defaultLocation = (int)$db->query('SELECT id FROM inventory_locations WHERE is_default=1 AND active=1 ORDER BY id LIMIT 1')->fetchColumn();

        $findProduct = $db->prepare("SELECT id FROM products WHERE sku IS NOT NULL AND LOWER(TRIM(sku))=LOWER(TRIM(?)) LIMIT 1");
        $insertProduct = $db->prepare("INSERT INTO products
            (category_id,product_type,sku,name,brand,description,unit,cost_usd,favorite,track_stock,stock_quantity,source_key,active)
            VALUES(?,'bienes',?,?,?,?,?,?,0,1,0,?,1)");
        $insertInventory = $db->prepare('INSERT IGNORE INTO inventory_items(product_id,track_stock) VALUES(?,1)');
        $insertBalance = $db->prepare('INSERT IGNORE INTO inventory_balances(product_id,location_id,quantity) VALUES(?,?,0)');
        $insertPrice = $db->prepare("INSERT INTO product_prices(product_id,price_list_id,price,currency)
            VALUES(?,?,?,'USD') ON DUPLICATE KEY UPDATE price=VALUES(price),currency='USD',updated_at=CURRENT_TIMESTAMP");

        $imported = 0;
        $skipped = 0;
        $blankCosts = 0;
        foreach ($rows as $row) {
            $sku = trim((string)$row['sku']);
            $findProduct->execute([$sku]);
            if ($findProduct->fetchColumn()) {
                $skipped++;
                continue;
            }

            $rawCost = trim((string)$row['cost_usd']);
            if ($rawCost === '') {
                $blankCosts++;
            }
            $cost = round(max(0, (float)$rawCost), 2);
            $sourceKey = 'shelly-202507-' . substr(hash('sha256', strtolower($sku)), 0, 32);
            $insertProduct->execute([
                $categoryId,
                $sku,
                trim((string)$row['name']),
                $brand,
                '',
                'Unidad',
                $cost,
                $sourceKey,
            ]);
            $productId = (int)$db->lastInsertId();
            $insertInventory->execute([$productId]);
            if ($defaultLocation) {
                $insertBalance->execute([$productId, $defaultLocation]);
            }

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
            'costos_vacios_en_origen' => $blankCosts,
            'marca' => $brand,
            'categoria' => $category,
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

