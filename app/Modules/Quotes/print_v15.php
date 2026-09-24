<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$id=(int)($_GET['id']??0);
$imageBySku=[];
$descriptionBySku=[];
try{
    $cfg=require $root.'/config.php';
    $imageDb=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $stmt=$imageDb->prepare("SELECT DISTINCT p.id,p.sku,p.name,p.description,p.image_data,p.image_mime FROM quote_items qi JOIN products p ON p.id=qi.product_id WHERE qi.quote_id=? AND qi.product_id IS NOT NULL");
    $stmt->execute([$id]);
    foreach($stmt as $product){
        $sku=trim((string)($product['sku']??''));
        if($sku==='')continue;
        $productName=trim((string)($product['name']??''));
        $productDetail=trim((string)($product['description']??''));
        // Los catálogos históricos de LifeSmart guardaron el texto comercial en
        // Detalle/Descripción; los nuevos lo guardan en Nombre. Admitimos ambos.
        $descriptionBySku[$sku]=$productDetail!==''?$productDetail:($productName!==''?$productName:$sku);
        $data=$product['image_data']??null;$mime=trim((string)($product['image_mime']??''));
        if($data!==null&&$data!==''){
            $imageBySku[$sku]='data:'.($mime!==''?$mime:'image/jpeg').';base64,'.base64_encode($data);
            continue;
        }
        foreach(['webp'=>'image/webp','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'] as $ext=>$fileMime){
            $path=$root.'/storage/product-images/'.(int)$product['id'].'.'.$ext;
            if(!is_file($path))continue;
            $bytes=@file_get_contents($path);
            if($bytes!==false&&$bytes!=='')$imageBySku[$sku]='data:'.$fileMime.';base64,'.base64_encode($bytes);
            break;
        }
    }
}catch(Throwable $e){}

ob_start();
require __DIR__.'/print_v14.php';
$html=ob_get_clean();

$imagesJson=json_encode($imageBySku,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$descriptionsJson=json_encode($descriptionBySku,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$inject=<<<HTML
<script>
(function(){
 const images=$imagesJson,descriptions=$descriptionsJson;
 document.querySelectorAll('.equipment tbody tr').forEach(row=>{
  if(row.classList.contains('section-title')||row.classList.contains('labor-row')||row.dataset.concept==='1')return;
  const cells=row.querySelectorAll('td');if(cells.length<3)return;
  const sku=(cells[1]?.textContent||'').trim(),src=images[sku],description=descriptions[sku];
  if(description&&!(cells[2]?.textContent||'').trim())cells[2].textContent=description;
  if(!src)return;const cell=cells[0];if(cell.querySelector('img'))return;
  cell.innerHTML='';const img=document.createElement('img');img.src=src;img.alt='';cell.appendChild(img);
 });
})();
</script>
HTML;
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
