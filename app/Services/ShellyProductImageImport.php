<?php
declare(strict_types=1);

/**
 * Descarga por tandas las imágenes verificadas del catálogo Shelly.
 * Conserva cualquier imagen que ya exista y registra cada SKU procesado.
 */
function importShellyProductImages20260924(PDO $db, string $erpRoot, int $batchSize = 10): void
{
    $importKey = 'imagenes_shelly_2026_09_24_v1';
    $csvPath = $erpRoot . '/database/data/2026_09_24_imagenes_shelly.csv';
    $targetDir = $erpRoot . '/storage/product-images';
    if (!is_file($csvPath)) return;

    $db->exec("CREATE TABLE IF NOT EXISTS erp_product_image_imports (
        import_key VARCHAR(120) NOT NULL,
        sku VARCHAR(190) NOT NULL,
        product_id INT UNSIGNED NULL,
        status VARCHAR(30) NOT NULL,
        source_url TEXT NULL,
        error_message VARCHAR(500) NULL,
        processed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(import_key,sku)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new RuntimeException('No se pudo crear el directorio de imágenes de productos.');
    }

    $handle = fopen($csvPath, 'rb');
    if ($handle === false) return;
    $headers = fgetcsv($handle, 0, ',', '"', '\\');
    if ($headers !== ['sku', 'image_url', 'source_product']) {
        fclose($handle);
        throw new RuntimeException('El manifiesto de imágenes Shelly no tiene el formato esperado.');
    }

    $processed = $db->prepare('SELECT 1 FROM erp_product_image_imports WHERE import_key=? AND sku=?');
    $findProduct = $db->prepare("SELECT id,image_data FROM products WHERE brand='Shelly' AND LOWER(TRIM(sku))=LOWER(TRIM(?)) LIMIT 1");
    $save = $db->prepare('INSERT INTO erp_product_image_imports(import_key,sku,product_id,status,source_url,error_message) VALUES(?,?,?,?,?,?)');
    $count = 0;

    while (($values = fgetcsv($handle, 0, ',', '"', '\\')) !== false && $count < $batchSize) {
        if (count($values) !== count($headers)) continue;
        $row = array_combine($headers, $values);
        if ($row === false) continue;
        $sku = trim((string)$row['sku']);
        $url = trim((string)$row['image_url']);
        if ($sku === '' || $url === '') continue;

        $processed->execute([$importKey, $sku]);
        if ($processed->fetchColumn()) continue;
        $count++;

        $productId = null;
        try {
            $findProduct->execute([$sku]);
            $product = $findProduct->fetch();
            if (!$product) {
                $save->execute([$importKey, $sku, null, 'producto_no_encontrado', $url, 'No existe un producto Shelly con ese SKU.']);
                continue;
            }
            $productId = (int)$product['id'];
            $hasFile = false;
            foreach (['webp', 'jpg', 'png'] as $extension) {
                if (is_file($targetDir . '/' . $productId . '.' . $extension)) {
                    $hasFile = true;
                    break;
                }
            }
            if ($product['image_data'] !== null || $hasFile) {
                $save->execute([$importKey, $sku, $productId, 'imagen_existente', $url, null]);
                continue;
            }

            $context = stream_context_create([
                'http' => [
                    'timeout' => 25,
                    'follow_location' => 1,
                    'max_redirects' => 5,
                    'user_agent' => 'Mozilla/5.0 PuntoERP Product Catalog',
                ],
                'https' => [
                    'timeout' => 25,
                    'follow_location' => 1,
                    'max_redirects' => 5,
                    'user_agent' => 'Mozilla/5.0 PuntoERP Product Catalog',
                ],
            ]);
            $data = @file_get_contents($url, false, $context);
            if ($data === false || strlen($data) < 500 || strlen($data) > 8 * 1024 * 1024) {
                throw new RuntimeException('La descarga no devolvió una imagen válida.');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($data);
            $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
            if ($extension === null) throw new RuntimeException('Formato de imagen no admitido: ' . $mime);

            $target = $targetDir . '/' . $productId . '.' . $extension;
            $temporary = $target . '.tmp-' . bin2hex(random_bytes(4));
            if (file_put_contents($temporary, $data, LOCK_EX) === false || !rename($temporary, $target)) {
                @unlink($temporary);
                throw new RuntimeException('No se pudo guardar la imagen descargada.');
            }
            $save->execute([$importKey, $sku, $productId, 'importada', $url, null]);
        } catch (Throwable $e) {
            $save->execute([$importKey, $sku, $productId, 'error', $url, substr($e->getMessage(), 0, 500)]);
        }
    }
    fclose($handle);
}

