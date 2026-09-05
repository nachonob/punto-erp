<?php
declare(strict_types=1);
session_start();
$root=dirname(__DIR__,3);
if(empty($_SESSION['user'])){http_response_code(401);exit('No autorizado.');}
$family=(string)($_GET['family']??'');
$type=(string)($_GET['type']??'');
$families=['lifesmart','control4','shelly'];
$types=['prologo','pago'];
if(!in_array($family,$families,true)||!in_array($type,$types,true)){http_response_code(400);exit('Archivo inválido.');}
$path=$root.'/storage/uploads/pdf_assets/'.$type.'/'.$family.'.pdf';
if(!is_file($path)){http_response_code(404);exit('PDF no cargado.');}
header('Content-Type: application/pdf');
header('Content-Length: '.filesize($path));
header('Cache-Control: private, max-age=60');
header('Content-Disposition: inline; filename="'.$type.'-'.$family.'.pdf"');
readfile($path);
exit;
