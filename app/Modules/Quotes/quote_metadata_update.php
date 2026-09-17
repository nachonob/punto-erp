<?php
declare(strict_types=1);

if(session_status()!==PHP_SESSION_ACTIVE)session_start();
$root=dirname(__DIR__,3);
$cfg=require $root.'/config.php';
if(empty($_SESSION['user'])){header('Location:index.php');exit;}
if(!hash_equals((string)($_SESSION['csrf']??''),(string)($_POST['csrf']??''))){http_response_code(419);exit('Solicitud vencida.');}

$id=(int)($_POST['quote_id']??0);
$name=trim((string)($_POST['proposal_name']??''));
$rubro=trim((string)($_POST['quote_category']??''));
$template=(string)($_POST['quote_template_family']??'lifesmart');
$quoteDate=(string)($_POST['quote_date']??'');
$sentAt=trim((string)($_POST['sent_at']??''));
$notes=trim((string)($_POST['notes']??''));

if($id<1||$name===''){http_response_code(422);exit('Completá el nombre del presupuesto.');}
if(!in_array($template,['lifesmart','control4','shelly'],true)){http_response_code(422);exit('Plantilla inválida.');}
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$quoteDate)){http_response_code(422);exit('Fecha del presupuesto inválida.');}
if($sentAt!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$sentAt)){http_response_code(422);exit('Fecha de envío inválida.');}

$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
]);
$r=$db->prepare('SELECT name FROM quote_rubros WHERE name=? AND active=1');
$r->execute([$rubro]);
if(!$r->fetchColumn()){http_response_code(422);exit('Seleccioná un rubro válido.');}
$s=$db->prepare('SELECT status FROM quotes WHERE id=?');
$s->execute([$id]);
$status=(string)($s->fetchColumn()?:'');
if($status===''){http_response_code(404);exit('Presupuesto inexistente.');}
if($status==='borrador'){header('Location:index.php?a=edit_quote&id='.$id);exit;}

$update=$db->prepare('UPDATE quotes SET proposal_name=?,quote_category=?,quote_template_family=?,quote_date=?,sent_at=?,notes=? WHERE id=?');
$update->execute([$name,$rubro,$template,$quoteDate,$sentAt!==''?$sentAt:null,$notes,$id]);

$_SESSION['msg']='Datos generales del presupuesto actualizados. Los materiales continúan bloqueados.';
header('Location:index.php?a=quote_state_edit&id='.$id);
exit;
