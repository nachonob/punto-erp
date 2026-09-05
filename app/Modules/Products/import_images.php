<?php
declare(strict_types=1);
session_start();
$root=dirname(__DIR__,3);
$cfg=require $root.'/config.php';
date_default_timezone_set($cfg['timezone']??'America/Argentina/Buenos_Aires');
$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
]);
if(empty($_SESSION['user'])){header('Location:index.php');exit;}
if(($_SESSION['user']['role']??'')!=='admin'){http_response_code(403);exit('Solo administrador.');}
$_SESSION['csrf']??=bin2hex(random_bytes(24));
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function fetchUrl(string $url,int $timeout=20):array{
 $ch=curl_init($url);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>5,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>$timeout,CURLOPT_USERAGENT=>'Mozilla/5.0 PuntoERP/1.0',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_HTTPHEADER=>['Accept: text/html,application/xhtml+xml,image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8']]);
 $body=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);curl_close($ch);
 if($body===false||$code<200||$code>=400)throw new RuntimeException($err?:('HTTP '.$code));
 return [$body,$type];
}
function absUrl(string $base,string $url):string{
 if(preg_match('~^https?://~i',$url))return $url;
 $p=parse_url($base);$scheme=$p['scheme']??'https';$host=$p['host']??'';
 if(str_starts_with($url,'//'))return $scheme.':'.$url;
 if(str_starts_with($url,'/'))return $scheme.'://'.$host.$url;
 $dir=preg_replace('~/[^/]*$~','/',$p['path']??'/');return $scheme.'://'.$host.$dir.$url;
}
function extractImageUrl(string $html,string $page):?string{
 libxml_use_internal_errors(true);$dom=new DOMDocument();@$dom->loadHTML($html);$xp=new DOMXPath($dom);
 $queries=[
  "//meta[@property='og:image']/@content",
  "//meta[@name='twitter:image']/@content",
  "//meta[@property='twitter:image']/@content",
  "//img[contains(@class,'product') or contains(@class,'woocommerce-product-gallery') or contains(@class,'wp-post-image')]/@src",
  "//main//img/@src",
  "//article//img/@src"
 ];
 foreach($queries as $q){foreach($xp->query($q)?:[] as $n){$u=trim((string)$n->nodeValue);if($u==='')continue;if(str_contains($u,'logo'))continue;return absUrl($page,$u);}}
 return null;
}
function normalizeImage(string $data,string $mime):array{
 $mime=strtolower(trim(explode(';',$mime)[0]));
 if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){
  $f=new finfo(FILEINFO_MIME_TYPE);$mime=$f->buffer($data)?:$mime;
 }
 if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))throw new RuntimeException('Formato no admitido: '.$mime);
 if(strlen($data)>3*1024*1024)throw new RuntimeException('Imagen mayor a 3 MB');
 return [$data,$mime];
}

$sources=[
 'LS082WH'=>'https://www.lifesmart.com.mx/producto.php?p=LS082WH',
 'LS227'=>'https://cblifesmart.de/product/lifesmart-nature-7-pro-full-screen-control/',
 'LS280-AQ'=>'https://manuals.plus/ar/lifesmart/ls280ls280-aq-nature-x-nature-x-pro-manual',
 'LS177'=>'https://mall.icbc.com.ar/domotica/104930818-modulo-para-interruptores-lifesmart-2-vias.html',
 'LS193'=>'https://puntodomotica.com/producto/modulo-para-interruptores-pro/',
 'LS124WH'=>'https://www.lifesmart.com.mx/producto.php?p=LS124WH',
 'LS125WH'=>'https://www.lifesmart.com.mx/producto.php?p=LS125WH',
 'LS124BL'=>'https://www.lifesmart.com.mx/producto.php?p=LS124BL',
 'LS125BL'=>'https://www.lifesmart.com.mx/producto.php?p=LS125BL',
 'LS218-WH3'=>'https://www.lifesmart.com.mx/producto.php?p=LS218-WH3',
 'LS174'=>'https://lifesmart.id/product/smart-lighting/switch/dimmer-motion-sensor-switch/',
 'LS069WH'=>'https://lifesmart.id/product/smart-lighting/switch/cube-clicker/',
 'LS069WG'=>'https://www.itdtech.ae/ar/shop/cube-clicker-%D8%AE%D8%B4%D8%A8-1666',
 'LS136'=>'https://lifesmart.id/product/control-center/spot/',
 'LS251WH'=>'https://iot.ilifesmart.com/product/SPOT-Mini/',
 'C200'=>'https://iot.ilifesmart.com/product/Smart-Door-Lock-C200/',
 'LS063WH'=>'https://lifesmart.id/product/room-climate/cube-environmental-sensor/',
 'LS258'=>'https://lifesmart.id/product/home-security/smart-camera/indoor-camera/',
 'LS259'=>'https://mall.icbc.com.ar/domotica/104932538-camara-exterior-lifesmart.html',
 'LS240-WCN'=>'https://iot.ilifesmart.com/product/BLEND-Curtain-Controller-PRO/',
 'LS240-GCN'=>'https://iot.ilifesmart.com/product/BLEND-Curtain-Controller-PRO/',
 'LS220-WT1'=>'https://www.ilifesmart.com/zhidao/',
 'LS220-GT1'=>'https://www.ilifesmart.com/zhidao/',
 'LS215'=>'https://www.lifesmart-sa.com/shop/ls215-smart-home-starter-set-ls215-1207',
 'LS202WH'=>'https://www.lifesmart-sa.com/ar/shop/ls202wh-%D9%85%D8%B3%D8%AA%D8%B4%D8%B9%D8%B1-%D8%A7%D9%84%D8%A8%D8%A7%D8%A8%D8%A7%D9%84%D9%86%D8%A7%D9%81%D8%B0%D8%A9-defed-ls202wh-1072',
 'LS203WH'=>'https://manuals.plus/lifesmart/ls203wh-defed-motion-sensor-manual',
 'LS204WH'=>'https://manualsfile.com/product/k3x18rf5y4q.html',
 'LS205WH'=>'https://www.lifesmart.com.mx/producto.php?p=LS205WH',
 'LS219-WF3'=>'https://www.ilifesmart.com/zhidao/',
 'LS143'=>'https://lifesmart.id/product/control-center/general-controller/',
 'LS012'=>'https://lifesmart.com.mx/producto.php?p=LS012',
 'LS240-GCN'=>'https://iot.ilifesmart.com/product/BLEND-Curtain-Controller-PRO/',
 'LS268-GR3'=>'https://maxsmart.vn/product/lifesmart-ls268-gr3-bang-dieu-khien-nature-mini-pro/',
 'LS235WH'=>'https://mall.icbc.com.ar/domotica/104932537-sensor-de-movimiento-life-smart-pro.html',
 'LS058WH'=>'https://lifesmart.id/product/home-security/smart-sensor/cube-door-window-sensor/'
];
$unresolved=['QS-Zigbee-D02-TRIAC-2C-LN','81.00016','70.00013','24.90069'];
$results=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($_SESSION['csrf'],$_POST['csrf']??'')){http_response_code(419);exit('Solicitud vencida.');}
 foreach($sources as $sku=>$page){
  try{
   $s=$db->prepare('SELECT id,image_data FROM products WHERE sku=? LIMIT 1');$s->execute([$sku]);$p=$s->fetch();if(!$p){$results[]=[$sku,'No existe en productos','warn'];continue;}
   if(!empty($p['image_data'])&&!isset($_POST['replace'])){$results[]=[$sku,'Ya tenía imagen','ok'];continue;}
   [$html,$ct]=fetchUrl($page);$imgUrl=extractImageUrl($html,$page);if(!$imgUrl)throw new RuntimeException('No se encontró imagen en la ficha');
   [$img,$imgCt]=fetchUrl($imgUrl);[$img,$mime]=normalizeImage($img,$imgCt);
   $u=$db->prepare('UPDATE products SET image_data=?,image_mime=? WHERE id=?');$u->bindValue(1,$img,PDO::PARAM_LOB);$u->bindValue(2,$mime);$u->bindValue(3,(int)$p['id'],PDO::PARAM_INT);$u->execute();
   $results[]=[$sku,'Imagen importada · '.round(strlen($img)/1024).' KB','ok'];
  }catch(Throwable $ex){$results[]=[$sku,$ex->getMessage(),'bad'];}
 }
}
$count=(int)$db->query("SELECT COUNT(*) FROM products WHERE image_data IS NOT NULL AND OCTET_LENGTH(image_data)>0")->fetchColumn();
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Importar imágenes · Punto ERP</title><style>body{font:15px/1.45 system-ui;background:#f4f6f8;color:#27303b;margin:0}.wrap{max-width:1050px;margin:40px auto;padding:0 18px}.card{background:#fff;border:1px solid #e1e5e9;border-radius:14px;padding:22px;margin-bottom:18px}.btn{background:#ff6702;color:#fff;border:0;border-radius:8px;padding:11px 16px;font-weight:700;cursor:pointer;text-decoration:none}.muted{color:#6e7781}table{width:100%;border-collapse:collapse}th,td{padding:10px;border-bottom:1px solid #eee;text-align:left}.ok{color:#15815c}.bad{color:#bf3c3c}.warn{color:#a36b00}</style></head><body><div class="wrap"><div class="card"><a href="?a=products">← Productos</a><h1>Imágenes de productos</h1><p>Fuentes curadas para <?=count($sources)?> SKUs. Ya hay <b><?=$count?></b> productos con imagen en la base.</p><p class="muted">El importador descarga la imagen principal de la ficha y la guarda directamente en <code>products.image_data</code> / <code>image_mime</code>. Los presupuestos la toman desde ahí.</p><form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><label><input type="checkbox" name="replace" value="1"> Reemplazar también imágenes existentes</label><br><br><button class="btn">Importar imágenes faltantes</button></form></div><?php if($results):?><div class="card"><h2>Resultado</h2><table><tr><th>SKU</th><th>Estado</th></tr><?php foreach($results as [$sku,$msg,$cls]):?><tr><td><b><?=e($sku)?></b></td><td class="<?=e($cls)?>"><?=e($msg)?></td></tr><?php endforeach;?></table></div><?php endif;?><div class="card"><h2>Pendientes de validación manual</h2><p class="muted">No los importo automáticamente para evitar asociar una foto incorrecta.</p><p><?=e(implode(' · ',$unresolved))?></p></div></div></body></html>