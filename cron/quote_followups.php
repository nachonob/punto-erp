<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$cfg=require $root.'/config.php';
date_default_timezone_set($cfg['timezone']??'America/Argentina/Buenos_Aires');
$expected=(string)($cfg['quote_followup_cron_token']??'');
$provided=(string)($_GET['token']??'');
if($expected===''||$expected==='CAMBIAR_POR_UN_TOKEN_ALEATORIO_LARGO'||!hash_equals($expected,$provided)){http_response_code(403);exit("Token inválido.\n");}
$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$sql="SELECT q.id,q.version_no,q.next_followup_date,u.name,u.email,p.project_number,p.name project_name,c.business_name FROM quotes q JOIN users u ON u.id=q.responsible_user_id JOIN projects p ON p.id=q.project_id JOIN clients c ON c.id=p.client_id WHERE q.followup_closed_at IS NULL AND q.next_followup_date<=CURDATE() AND q.reminder_sent_at IS NULL";
$rows=$db->query($sql)->fetchAll();$sent=0;
foreach($rows as $q){
 if(!filter_var($q['email'],FILTER_VALIDATE_EMAIL))continue;
 $db->beginTransaction();$lock=$db->prepare('SELECT reminder_sent_at,followup_closed_at FROM quotes WHERE id=? FOR UPDATE');$lock->execute([$q['id']]);$current=$lock->fetch();if(!$current||$current['reminder_sent_at']||$current['followup_closed_at']){$db->rollBack();continue;}
 $recipients=$q['email'].', iescobar@puntodomotica.com';
 $subject='Seguimiento pendiente · '.$q['project_number'].' · presupuesto v'.$q['version_no'];
 $url=rtrim((string)$cfg['base_url'],'/').'/index.php?a=quote_followup&id='.$q['id'];
 $body='<p>Hola '.htmlspecialchars($q['name'],ENT_QUOTES,'UTF-8').', tenés que contactar a <b>'.htmlspecialchars($q['business_name'],ENT_QUOTES,'UTF-8').'</b> por el presupuesto del proyecto '.htmlspecialchars($q['project_name'],ENT_QUOTES,'UTF-8').'.</p><p>Fecha prevista: '.$q['next_followup_date'].'</p><p><a href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">Abrir seguimiento</a></p>';
 $headers="MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: ".($cfg['company_email']??'')."\r\n";
 if(mail($recipients,$subject,$body,$headers)){$up=$db->prepare('UPDATE quotes SET reminder_sent_at=NOW() WHERE id=? AND reminder_sent_at IS NULL');$up->execute([$q['id']]);$sent+=$up->rowCount();$db->commit();}else{$db->rollBack();}
}
header('Content-Type: text/plain; charset=UTF-8');echo "Recordatorios enviados: $sent\n";
