<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);$id=(int)($_GET['id']??0);$token='';$baseUrl='';
try{
 $cfg=require $root.'/config.php';$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $s=$db->prepare('SELECT token FROM quote_public_links WHERE quote_id=? AND is_active=1 LIMIT 1');$s->execute([$id]);$token=(string)($s->fetchColumn()?:'');
 if($token===''){$token=bin2hex(random_bytes(32));$s=$db->prepare('INSERT INTO quote_public_links(quote_id,token,is_active) VALUES(?,?,1) ON DUPLICATE KEY UPDATE token=VALUES(token),is_active=1');$s->execute([$id,$token]);}
 $https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');$scheme=$https?'https':'http';$host=(string)($_SERVER['HTTP_HOST']??'puntodomotica.com');$dir=rtrim(str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??'/erp-dev/index.php'))),'/');$baseUrl=$scheme.'://'.$host.$dir.'/public-presupuesto.php?t='.$token;
}catch(Throwable $e){}
ob_start();require __DIR__.'/print_v9.php';$html=ob_get_clean();
if($baseUrl!==''){
 $link=htmlspecialchars($baseUrl,ENT_QUOTES,'UTF-8');
 // Reescribe el texto ya codificado de ambos enlaces agregando el acceso seguro al presupuesto.
 $html=preg_replace_callback('/href="(mailto:[^"]+)"/',function($m)use($baseUrl){$u=htmlspecialchars_decode($m[1],ENT_QUOTES);$sep=str_contains($u,'?')?'&':'?';if(str_contains($u,'body=')){$parts=explode('body=',$u,2);$body=rawurldecode($parts[1]);$u=$parts[0].'body='.rawurlencode($body."\n\nVer / descargar presupuesto: ".$baseUrl);}return 'href="'.htmlspecialchars($u,ENT_QUOTES,'UTF-8').'"';},$html)??$html;
 $html=preg_replace_callback('/href="(https:\/\/wa\.me\/[^"]+)"/',function($m)use($baseUrl){$u=htmlspecialchars_decode($m[1],ENT_QUOTES);if(str_contains($u,'?text=')){$parts=explode('?text=',$u,2);$text=rawurldecode($parts[1]);$u=$parts[0].'?text='.rawurlencode($text."\n\nVer / descargar presupuesto: ".$baseUrl);}return 'href="'.htmlspecialchars($u,ENT_QUOTES,'UTF-8').'"';},$html)??$html;
}
echo $html;
