<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$cfg=require $root.'/config.php';
require_once $root.'/app/Core/ProjectFollowup.php';
date_default_timezone_set($cfg['timezone']??'America/Argentina/Buenos_Aires');

$provided=(string)($_GET['token']??'');
if(PHP_SAPI==='cli')foreach(array_slice($argv,1) as $argument)if(str_starts_with($argument,'--token='))$provided=substr($argument,8);
$expected=(string)($cfg['quote_followup_cron_token']??'');
if($expected===''||$provided===''||!hash_equals($expected,$provided)){
 if(PHP_SAPI!=='cli')http_response_code(403);
 exit("Acceso denegado.\n");
}

try{
 $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 ensureProjectFollowupSchema($db);
 $rows=$db->query("SELECT p.id,p.project_number,p.name project_name,p.next_followup_date,c.business_name,c.contact_name,u.name responsible_name,u.email responsible_email,(SELECT COUNT(*) FROM quotes q WHERE q.project_id=p.id) quote_count FROM projects p JOIN clients c ON c.id=p.client_id LEFT JOIN users u ON u.id=p.followup_responsible_user_id WHERE p.next_followup_date<=CURDATE() AND p.followup_closed_at IS NULL AND p.followup_reminder_sent_at IS NULL ORDER BY p.next_followup_date,p.id")->fetchAll();
 $sent=0;
 foreach($rows as $project){
  $claim=$db->prepare('UPDATE projects SET followup_reminder_sent_at=NOW() WHERE id=? AND next_followup_date<=CURDATE() AND followup_closed_at IS NULL AND followup_reminder_sent_at IS NULL');
  $claim->execute([$project['id']]);if(!$claim->rowCount())continue;
  $recipients=array_values(array_unique(array_filter([(string)$project['responsible_email'],'iescobar@puntodomotica.com',(string)($cfg['quote_reminder_email']??'')],static fn(string $email):bool=>filter_var($email,FILTER_VALIDATE_EMAIL)!==false)));
  $projectName=$project['project_number'].' · '.$project['project_name'];
  $url=rtrim((string)($cfg['base_url']??''),'/').'/?a=project_followup&id='.$project['id'];
  $subject='Seguimiento pendiente · '.$projectName;
  $body="Hola ".($project['responsible_name']?:'equipo').",\n\nRecordatorio: hay que contactar a {$project['business_name']} por el proyecto {$projectName}.\nEl proyecto reúne {$project['quote_count']} presupuesto(s) y tiene una única fecha de seguimiento: {$project['next_followup_date']}.\n\nGestionar seguimiento: {$url}\n";
  $headers="From: ".($cfg['company_email']??'iescobar@puntodomotica.com')."\r\nContent-Type: text/plain; charset=UTF-8";
  if(!$recipients||!mail(implode(',',$recipients),$subject,$body,$headers)){$db->prepare('UPDATE projects SET followup_reminder_sent_at=NULL WHERE id=?')->execute([$project['id']]);continue;}
  $sent++;
 }
 echo "Recordatorios enviados: {$sent}\n";
}catch(Throwable $error){
 if(PHP_SAPI!=='cli')http_response_code(500);
 error_log('No se pudieron procesar los recordatorios: '.$error->getMessage());echo "No se pudieron procesar los recordatorios.\n";exit(1);
}
