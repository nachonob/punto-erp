<?php
declare(strict_types=1);
$a=$_GET['a']??'';

// Una versión anterior de la vista pública podía dejar este usuario técnico en la sesión.
// Se elimina para que nunca sea interpretado como un usuario real sin permisos.
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(($_SESSION['user']['role']??'')==='public_quote')unset($_SESSION['user']);
session_write_close();
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

function recoveredProjectRouteAccess():array{
 if(session_status()!==PHP_SESSION_ACTIVE)session_start();
 $user=$_SESSION['user']??null;$logged=!empty($user);
 $isAdmin=$logged&&(($user['role']??'')==='admin');
 $permissions=$user['permissions']??[];
 $view=$isAdmin||!empty($permissions['projects']['view'])||!empty($permissions['projects']['manage']);
 $manage=$isAdmin||!empty($permissions['projects']['manage']);
 if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
 return [$logged,$view,$manage];
}

if(in_array($a,['users','edit_user','save_user','update_user','delete_user','save_profile','delete_profile'],true)){
 require __DIR__.'/app/Modules/Projects/users_admin.php';exit;
}
if($a==='edit_profile'&&(int)($_GET['id']??0)===0){require __DIR__.'/app/Modules/Projects/profile_new.php';exit;}
if(in_array($a,['edit_profile','update_profile'],true)){require __DIR__.'/app/Modules/Projects/profile_technical_permissions.php';exit;}
if($a==='daily_report'){require __DIR__.'/app/Modules/Projects/daily_report.php';exit;}
if($a==='transcribe_report'){require __DIR__.'/app/Modules/Projects/transcribe_report.php';exit;}
if($a==='my_day'){require __DIR__.'/app/Modules/Projects/my_day.php';exit;}
if(in_array($a,['technical_schedule','save_schedule_event'],true)){require __DIR__.'/app/Modules/Projects/schedule.php';exit;}
if(in_array($a,['technical_projects','technical_project','save_technical_project'],true)){require __DIR__.'/app/Modules/Projects/technical.php';exit;}
if($a==='technical_quote'){require __DIR__.'/app/Modules/Quotes/technical_print.php';exit;}
if(in_array($a,['project_plan_view','project_plan_download','upload_project_plans','upload_project_cad','delete_project_plan'],true)){require __DIR__.'/app/Modules/Projects/project_plans.php';exit;}
if(in_array($a,['project_statement','public_project_statement'],true)){require __DIR__.'/app/Modules/Projects/account_statement.php';exit;}
if($a==='projects'){
 [$logged,$view]=recoveredProjectRouteAccess();
 if($logged&&!$view){http_response_code(403);exit('Tu perfil no permite acceder a proyectos.');}
 require __DIR__.'/app/Modules/Projects/list_v2.php';exit;
}
if($a==='project'){
 [$logged,$view,$manage]=recoveredProjectRouteAccess();
 if($logged&&!$view){http_response_code(403);exit('Tu perfil no permite acceder a proyectos.');}
 if($logged&&$view&&!$manage){require __DIR__.'/app/Modules/Projects/detail_technical.php';exit;}
 require __DIR__.'/app/Modules/Projects/detail_v5.php';exit;
}
if(in_array($a,['new_project','save_project'],true)){
 [,,$manage]=recoveredProjectRouteAccess();
 if(!$manage){http_response_code(403);exit('Tu perfil no permite crear proyectos.');}
 require __DIR__.'/app/Modules/Projects/new_v2.php';exit;
}

if(in_array($a,['quote_rubros','save_quote_rubro','update_quote_rubro','delete_quote_rubro'],true)){
 [,,$manage]=recoveredQuoteRouteAccess();
 if(!$manage){http_response_code(403);exit('Tu perfil no permite administrar rubros de presupuestos.');}
 require __DIR__.'/app/Modules/Quotes/quote_rubros.php';exit;
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
 require __DIR__.'/app/Modules/Quotes/print_v14.php';exit;
}
if(in_array($a,['new_quote','save_quote','edit_quote','update_quote','duplicate_quote'],true)){
 [,,$manage]=recoveredQuoteRouteAccess();
 if(!$manage){http_response_code(403);exit('Tu perfil es de solo lectura y no permite crear ni editar presupuestos.');}
 require __DIR__.'/app/Modules/Quotes/module_v26.php';exit;
}
if($a==='send_quote'){
 [,,$manage]=recoveredQuoteRouteAccess();
 if(!$manage){http_response_code(403);exit('Tu perfil no permite enviar presupuestos.');}
 require __DIR__.'/app/Modules/Quotes/quote_send.php';exit;
}
if($a==='quote_state_edit'){
 [,,$manage]=recoveredQuoteRouteAccess();
 if(!$manage){http_response_code(403);exit('Tu perfil no permite cambiar el estado del presupuesto.');}
 require __DIR__.'/app/Modules/Quotes/quote_state_edit.php';exit;
}
if($a==='quote_unlock'){
 [,,$manage]=recoveredQuoteRouteAccess();
 if(!$manage){http_response_code(403);exit('Tu perfil no permite desbloquear presupuestos.');}
 require __DIR__.'/app/Modules/Quotes/quote_unlock.php';exit;
}
if($a==='quote_metadata_update'){
 [,,$manage]=recoveredQuoteRouteAccess();
 if(!$manage){http_response_code(403);exit('Tu perfil no permite editar datos del presupuesto.');}
 require __DIR__.'/app/Modules/Quotes/quote_metadata_update.php';exit;
}
if($a==='quote_status'){
 [,,$manage]=recoveredQuoteRouteAccess();
 if(!$manage){http_response_code(403);exit('Tu perfil no permite cambiar el estado del presupuesto.');}
 require __DIR__.'/app/Modules/Quotes/quote_status.php';exit;
}
if(in_array($a,['quote_followup','save_quote_followup'],true)){
 require __DIR__.'/app/Modules/Quotes/module.php';exit;
}
if(in_array($a,['deliveries','new_delivery','save_delivery','delivery','confirm_delivery','delivery_print'],true)){
 [,,$quoteManage]=recoveredQuoteRouteAccess();
 if(!$quoteManage){http_response_code(403);exit('Tu perfil no permite gestionar entregas de materiales.');}
 require __DIR__.'/app/Modules/Deliveries/module.php';exit;
}
$module=$_GET['module']??'accounts';
$registry=require __DIR__.'/app/modules.php';
if(!isset($registry[$module])){http_response_code(404);exit('Módulo inexistente.');}
if(empty($registry[$module]['enabled'])){
 require __DIR__.'/app/Core/ComingSoon.php';
 exit;
}
require __DIR__.'/'.$registry[$module]['entry'];
