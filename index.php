<?php
declare(strict_types=1);
$module=$_GET['module']??'accounts';
$registry=require __DIR__.'/app/modules.php';
if(!isset($registry[$module])){http_response_code(404);exit('Módulo inexistente.');}
if(empty($registry[$module]['enabled'])){
 require __DIR__.'/app/Core/ComingSoon.php';
 exit;
}
require __DIR__.'/'.$registry[$module]['entry'];
