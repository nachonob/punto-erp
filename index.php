<?php
declare(strict_types=1);
$a=$_GET['a']??'';
if(in_array($a,['new_quote','save_quote'],true)){
 require __DIR__.'/app/Modules/Quotes/module.php';
 exit;
}
$module=$_GET['module']??'accounts';
$registry=require __DIR__.'/app/modules.php';
if(!isset($registry[$module])){http_response_code(404);exit('Módulo inexistente.');}
if(empty($registry[$module]['enabled'])){
 require __DIR__.'/app/Core/ComingSoon.php';
 exit;
}
require __DIR__.'/'.$registry[$module]['entry'];
