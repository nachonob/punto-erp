<?php
declare(strict_types=1);
$a=$_GET['a']??'';
if($a==='quotes'){
 require __DIR__.'/app/Modules/Quotes/list.php';
 exit;
}
if(in_array($a,['new_quote','save_quote','quote_view','quote_print'],true)){
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
?><script>(function(){const nav=document.querySelector('.sidebar-nav');if(!nav||nav.querySelector('a[href="?a=quotes"]'))return;const link=document.createElement('a');link.href='?a=quotes';link.innerHTML='<span class="nav-icon">▥</span>Presupuestos';const projectLink=nav.querySelector('a[href="?a=projects"]');if(projectLink&&projectLink.parentNode===nav){projectLink.insertAdjacentElement('afterend',link)}else{nav.appendChild(link)}})();</script>
