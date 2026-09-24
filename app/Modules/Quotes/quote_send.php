<?php
declare(strict_types=1);

if(session_status()!==PHP_SESSION_ACTIVE)session_start();
$root=dirname(__DIR__,3);
$cfg=require $root.'/config.php';
require_once $root.'/app/Core/ProjectFollowup.php';
date_default_timezone_set($cfg['timezone']??'America/Argentina/Buenos_Aires');
if(empty($_SESSION['user'])){header('Location:index.php');exit;}
if(!hash_equals($_SESSION['csrf']??'',(string)($_GET['csrf']??''))){http_response_code(419);exit('Solicitud vencida.');}

$id=(int)($_GET['id']??0);
$channel=(string)($_GET['channel']??'');
$target=base64_decode((string)($_GET['target']??''),true)?:'';
if(!$id||!in_array($channel,['email','whatsapp'],true)){http_response_code(422);exit('Envío inválido.');}
if($channel==='email'&&!str_starts_with($target,'mailto:')){http_response_code(422);exit('Destino de correo inválido.');}
if($channel==='whatsapp'&&!str_starts_with($target,'https://wa.me/')){http_response_code(422);exit('Destino de WhatsApp inválido.');}

$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
]);
if(!(bool)$db->query("SHOW COLUMNS FROM quotes LIKE 'locked_at'")->fetch())$db->exec("ALTER TABLE quotes ADD COLUMN locked_at DATETIME NULL AFTER sent_at");
$statusType=(string)$db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='quotes' AND COLUMN_NAME='status'")->fetchColumn();
if(!str_contains($statusType,'aprobado_definitivo'))$db->exec("ALTER TABLE quotes MODIFY status ENUM('borrador','enviado','aprobado_inicial','aprobado_definitivo','final','rechazado') NOT NULL DEFAULT 'borrador'");
ensureProjectFollowupSchema($db);
$s=$db->prepare('SELECT id,project_id,status FROM quotes WHERE id=?');
$s->execute([$id]);
$quote=$s->fetch();
if(!$quote){http_response_code(404);exit('Presupuesto inexistente.');}

$sent=date('Y-m-d');
$next=new DateTimeImmutable($sent);$days=0;
while($days<10){$next=$next->modify('+1 day');if((int)$next->format('N')<=5)$days++;}
$nextDate=$next->format('Y-m-d');
$userId=(int)($_SESSION['user']['id']??0);

$db->beginTransaction();
$db->prepare("UPDATE quotes SET status='enviado',sent_at=COALESCE(sent_at,?),locked_at=COALESCE(locked_at,NOW()),responsible_user_id=COALESCE(responsible_user_id,?),next_followup_date=COALESCE(next_followup_date,?),reminder_sent_at=NULL,followup_closed_at=NULL WHERE id=?")
   ->execute([$sent,$userId,$nextDate,$id]);
try{
 $db->prepare("INSERT INTO quote_followup_history(quote_id,user_id,event_type,next_contact_date,notes)
 SELECT ?,?,'enviado',?,? WHERE NOT EXISTS (
  SELECT 1 FROM quote_followup_history WHERE quote_id=? AND event_type='enviado'
 )")->execute([$id,$userId,$nextDate,'Enviado por '.$channel,$id]);
}catch(Throwable $ignored){}
scheduleProjectFollowupForQuote($db,(int)$quote['project_id'],$id,$sent,$userId,$channel);
$db->commit();

header('Location: '.$target);
exit;
