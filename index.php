<?php
declare(strict_types=1);
$a=$_GET['a']??'';
if($a==='sales_quotes'){$a='quotes';$_GET['a']='quotes';}

function recoveredQuoteRouteAccess():array{
 if(session_status()!==PHP_SESSION_ACTIVE)session_start();
 $user=$_SESSION['user']??null;$logged=!empty($user);
 $isAdmin=$logged&&(($user['role']??'')==='admin');
 $permissions=$user['permissions']??[];
 $view=$isAdmin||!empty($permissions['projects']['view'])||!empty($permissions['projects']['manage'])||!empty($permissions['sales_quotes']['view'])||!empty($permissions['sales_quotes']['manage']);
 $manage=$isAdmin||!empty($permissions['projects']['manage'])||!empty($permissions['sales_quotes']['manage']);
 if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
 return [$logged,$view,$manage];
}

if($a==='quotes'){
 [$logged,$view,$manage]=recoveredQuoteRouteAccess();
 if($logged&&!$view){http_response_code(403);exit('Tu perfil no permite acceder a presupuestos.');}
 if($logged&&$view&&!$manage){header('Location:?a=projects');exit;}
 require __DIR__.'/app/Modules/Quotes/list_v2.php';exit;
}
if($a==='quote_pdf_assets'){
 [,,$manage]=recoveredQuoteRouteAccess();
 if(!$manage){http_response_code(403);exit('Tu perfil no permite administrar archivos de presupuestos.');}
 require __DIR__.'/app/Modules/Quotes/pdf_assets_admin.php';exit;
}
if($a==='quote_pdf_asset'){
 [,,$manage]=recoveredQuoteRouteAccess();
 if(!$manage){http_response_code(403);exit('Tu perfil no permite acceder a archivos comerciales del presupuesto.');}
 require __DIR__.'/app/Modules/Quotes/pdf_asset.php';exit;
}
if(in_array($a,['quote_view','quote_print'],true)){
 [$logged,$view,$manage]=recoveredQuoteRouteAccess();
 if($logged&&!$view){http_response_code(403);exit('Tu perfil no permite acceder a presupuestos.');}
 if($logged&&$view&&!$manage){require __DIR__.'/app/Modules/Quotes/technical_view.php';exit;}
 require __DIR__.'/app/Modules/Quotes/print_v11.php';exit;
}
if(in_array($a,['new_quote','save_quote','edit_quote','update_quote'],true)){
 [,,$manage]=recoveredQuoteRouteAccess();
 if(!$manage){http_response_code(403);exit('Tu perfil es de solo lectura y no permite crear ni editar presupuestos.');}
 require __DIR__.'/app/Modules/Quotes/module_v21.php';exit;
}
if(in_array($a,['quote_followup','save_quote_followup'],true)){
 require __DIR__.'/app/Modules/Quotes/module.php';exit;
}
$module=$_GET['module']??'accounts';
$registry=require __DIR__.'/app/modules.php';
if(!isset($registry[$module])){http_response_code(404);exit('Módulo inexistente.');}
if(empty($registry[$module]['enabled'])){
 require __DIR__.'/app/Core/ComingSoon.php';
 exit;
}
require __DIR__.'/'.$registry[$module]['entry'];
