<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
$token=strtolower(trim((string)($_GET['t']??'')));
if(!preg_match('/^[a-f0-9]{64}$/',$token)){http_response_code(404);exit('Enlace de presupuesto inválido.');}
try{
 $cfg=require $root.'/config.php';
 $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $s=$db->prepare('SELECT quote_id FROM quote_public_links WHERE token=? AND is_active=1 LIMIT 1');$s->execute([$token]);$id=(int)$s->fetchColumn();
 if($id<1){http_response_code(404);exit('El enlace no existe o fue desactivado.');}
 // Habilita únicamente esta renderización pública; print_v4 conserva la autenticación para el ERP normal.
 if(session_status()!==PHP_SESSION_ACTIVE)session_start();
 $_SESSION['public_quote_access_id']=$id;
 $_GET['id']=$id;
 require __DIR__.'/public_quote_render.php';
}catch(Throwable $e){http_response_code(500);exit('No se pudo abrir el presupuesto.');}
