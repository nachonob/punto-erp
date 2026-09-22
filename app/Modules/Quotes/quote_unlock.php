<?php
declare(strict_types=1);

if(session_status()!==PHP_SESSION_ACTIVE)session_start();
$root=dirname(__DIR__,3);
$cfg=require $root.'/config.php';
date_default_timezone_set($cfg['timezone']??'America/Argentina/Buenos_Aires');
if(empty($_SESSION['user'])){header('Location:index.php');exit;}

function quRedirect(int $id,string $message):void{
 $_SESSION['msg']=$message;
 header('Location:index.php?a=quote_state_edit&id='.$id);
 exit;
}

$id=(int)($_POST['quote_id']??0);
$csrf=(string)($_POST['csrf']??'');
if($id<1||empty($_SESSION['csrf'])||!hash_equals((string)$_SESSION['csrf'],$csrf)){
 http_response_code(400);
 exit('Solicitud inválida.');
}

$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
]);

try{
 $db->beginTransaction();
 $stmt=$db->prepare('SELECT id,status FROM quotes WHERE id=? FOR UPDATE');
 $stmt->execute([$id]);
 $quote=$stmt->fetch();
 if(!$quote){
  $db->rollBack();
  http_response_code(404);
  exit('Presupuesto inexistente.');
 }
 if(!in_array($quote['status'],['enviado','aprobado_inicial'],true)){
  $db->rollBack();
  quRedirect($id,'Este presupuesto no se puede desbloquear desde su estado actual.');
 }

 $db->prepare("UPDATE quotes SET status='borrador',locked_at=NULL,next_followup_date=NULL,followup_closed_at=NULL WHERE id=?")->execute([$id]);
 $db->commit();
 $_SESSION['msg']='Presupuesto desbloqueado. Ya podés editar materiales, mano de obra y valores; los pagos registrados se conservaron.';
 header('Location:index.php?a=edit_quote&id='.$id);
 exit;
}catch(Throwable $e){
 if($db->inTransaction())$db->rollBack();
 quRedirect($id,'No se pudo desbloquear el presupuesto. Intentá nuevamente.');
}
