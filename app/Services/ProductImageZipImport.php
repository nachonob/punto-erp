<?php
declare(strict_types=1);

function normalizeProductImageSku(string $value): string
{
    return strtoupper(trim($value));
}

function handleProductImageZipImport(string $action, PDO $db, string $erpRoot): bool
{
    if ($action !== 'import_product_images' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }

    csrf();
    if (!class_exists('ZipArchive')) {
        throw new Exception('El servidor no tiene habilitada la extensión ZIP de PHP.');
    }
    if (!isset($_FILES['images_zip'])) {
        throw new Exception('Seleccioná un archivo ZIP.');
    }

    $upload = $_FILES['images_zip'];
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $code = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if (in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new Exception('El ZIP supera el tamaño máximo permitido por el servidor.');
        }
        throw new Exception('No se pudo recibir el archivo ZIP (código '.$code.').');
    }
    if ((int)$upload['size'] > 104857600) {
        throw new Exception('El ZIP no puede superar los 100 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    if (!in_array($mime, ['application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/octet-stream'], true)) {
        throw new Exception('El archivo seleccionado no es un ZIP válido.');
    }

    $products = $db->query("SELECT id,sku,name,image_data FROM products WHERE sku IS NOT NULL AND TRIM(sku)<>''")->fetchAll();
    $bySku = [];
    foreach ($products as $product) {
        $key = normalizeProductImageSku((string)$product['sku']);
        if ($key !== '') {
            $bySku[$key] = $product;
        }
    }

    $destination = $erpRoot.'/storage/product-images';
    if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
        throw new Exception('No se pudo crear la carpeta storage/product-images.');
    }
    if (!is_writable($destination)) {
        throw new Exception('La carpeta storage/product-images no tiene permiso de escritura.');
    }

    $zip = new ZipArchive();
    if ($zip->open($upload['tmp_name']) !== true) {
        throw new Exception('No se pudo abrir el ZIP.');
    }

    $replace = !empty($_POST['replace_existing']);
    $report = ['assigned' => [], 'not_found' => [], 'skipped' => [], 'invalid' => []];
    $seen = [];
    try {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string)$zip->getNameIndex($i);
            if ($entry === '' || substr($entry, -1) === '/') {
                continue;
            }
            $basename = basename(str_replace('\\', '/', $entry));
            $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                continue;
            }
            $sku = normalizeProductImageSku(pathinfo($basename, PATHINFO_FILENAME));
            if ($sku === '' || isset($seen[$sku])) {
                $report['invalid'][] = $basename.($sku !== '' ? ' (SKU repetido)' : '');
                continue;
            }
            $seen[$sku] = true;
            if (!isset($bySku[$sku])) {
                $report['not_found'][] = $basename;
                continue;
            }

            $product = $bySku[$sku];
            $id = (int)$product['id'];
            $existingFile = productImageFile($id);
            $hasImage = $product['image_data'] !== null || $existingFile !== null;
            if ($hasImage && !$replace) {
                $report['skipped'][] = $basename;
                continue;
            }

            $stat = $zip->statIndex($i);
            if (!$stat || (int)$stat['size'] <= 0 || (int)$stat['size'] > 8388608) {
                $report['invalid'][] = $basename.' (vacía o mayor a 8 MB)';
                continue;
            }
            $stream = $zip->getStream($entry);
            if (!$stream) {
                $report['invalid'][] = $basename.' (no se pudo leer)';
                continue;
            }
            $temp = tempnam($destination, 'img-');
            if ($temp === false) {
                fclose($stream);
                throw new Exception('No se pudo crear un archivo temporal para la importación.');
            }
            $output = fopen($temp, 'wb');
            if (!$output) {
                fclose($stream);
                @unlink($temp);
                throw new Exception('No se pudo escribir una imagen en el servidor.');
            }
            stream_copy_to_stream($stream, $output, 8388609);
            fclose($stream);
            fclose($output);

            $actualMime = (new finfo(FILEINFO_MIME_TYPE))->file($temp);
            $mimeExtensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($mimeExtensions[$actualMime]) || filesize($temp) > 8388608) {
                @unlink($temp);
                $report['invalid'][] = $basename.' (contenido de imagen inválido)';
                continue;
            }

            $finalExtension = $mimeExtensions[$actualMime];
            $finalPath = $destination.'/'.$id.'.'.$finalExtension;
            if (!@rename($temp, $finalPath)) {
                @unlink($temp);
                $report['invalid'][] = $basename.' (no se pudo guardar)';
                continue;
            }
            foreach (['jpg', 'png', 'webp'] as $oldExtension) {
                $oldPath = $destination.'/'.$id.'.'.$oldExtension;
                if ($oldPath !== $finalPath && is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
            if ($replace && $product['image_data'] !== null) {
                $db->prepare('UPDATE products SET image_data=NULL,image_mime=NULL WHERE id=?')->execute([$id]);
            }
            $report['assigned'][] = $basename.' → #'.$id.' · '.$product['name'];
        }
    } finally {
        $zip->close();
    }

    $_SESSION['product_image_import_report'] = $report;
    msg(count($report['assigned']).' imágenes asignadas por SKU. '.count($report['not_found']).' sin producto coincidente, '.count($report['skipped']).' omitidas y '.count($report['invalid']).' inválidas.');
    go('?a=import_product_images');
}

function renderProductImageZipImport(): void
{
    $report = $_SESSION['product_image_import_report'] ?? null;
    unset($_SESSION['product_image_import_report']);
    $uploadMax = ini_get('upload_max_filesize');
    $postMax = ini_get('post_max_size');
    ?>
    <div class="actions"><div style="flex:1"><span class="muted">Catálogo de productos</span><h1>Importar imágenes por SKU</h1><p class="muted">Subí un ZIP con archivos llamados como el SKU del producto, por ejemplo <b>C4-CORE3.jpg</b>. El sistema los guardará automáticamente con el ID interno.</p></div><a class="btn light" href="?a=products">Volver a Productos</a></div>
    <div class="card"><form method="post" action="?a=import_product_images" enctype="multipart/form-data"><?=token()?><p><label>Archivo ZIP</label><input type="file" name="images_zip" accept=".zip,application/zip" required><small class="muted">Formatos internos permitidos: JPG, PNG y WebP; hasta 8 MB por imagen. Límite configurado del servidor: upload_max_filesize <?=e($uploadMax)?>, post_max_size <?=e($postMax)?>.</small></p><p><label style="font-weight:500"><input type="checkbox" name="replace_existing" value="1"> Reemplazar imágenes existentes</label><small class="muted">Si lo activás, la imagen del ZIP reemplaza tanto archivos anteriores como imágenes guardadas en la base de datos.</small></p><button class="btn">Importar y asignar imágenes</button></form></div>
    <?php if (is_array($report)): ?>
      <div class="grid">
        <?php foreach (['assigned'=>'Asignadas','not_found'=>'SKU no encontrados','skipped'=>'Omitidas por tener imagen','invalid'=>'Archivos inválidos'] as $key=>$label): ?>
          <div class="card c6"><h2><?=e($label)?> (<?=count($report[$key]??[])?>)</h2><?php if (!empty($report[$key])): ?><div class="scroll" style="max-height:280px"><ul><?php foreach ($report[$key] as $item): ?><li><?=e($item)?></li><?php endforeach; ?></ul></div><?php else: ?><p class="muted">Ninguno.</p><?php endif; ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif;
}
