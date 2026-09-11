<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$cfg=require $root.'/config.php';
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
 $rows=$db->query("SELECT sq.id,sq.quote_number,sq.next_followup_date,sq.total,sq.currency,c.business_name,u.name responsible_name,u.email responsible_email FROM sales_quotes sq JOIN clients c ON c.id=sq.client_id JOIN users u ON u.id=sq.responsible_user_id WHERE sq.next_followup_date<=CURDATE() AND sq.followup_closed_at IS NULL AND sq.reminder_sent_at IS NULL ORDER BY sq.next_followup_date,sq.id")->fetchAll();
 $sent=0;
 foreach($rows as $quote){
  $claim=$db->prepare('UPDATE sales_quotes SET reminder_sent_at=NOW() WHERE id=? AND next_followup_date<=CURDATE() AND followup_closed_at IS NULL AND reminder_sent_at IS NULL');
  $claim->execute([$quote['id']]);if(!$claim->rowCount())continue;
  $recipients=array_values(array_unique(array_filter([$quote['responsible_email'],'iescobar@puntodomotica.com'],static fn(string $email):bool=>filter_var($email,FILTER_VALIDATE_EMAIL)!==false)));
  $url=rtrim((string)($cfg['base_url']??''),'/').'/?a=sales_quote&id='.$quote['id'];
  $subject='Seguimiento pendiente · '.$quote['quote_number'];
  $body="Hola {$quote['responsible_name']},\n\nEl presupuesto {$quote['quote_number']} de {$quote['business_name']} tiene seguimiento pendiente desde {$quote['next_followup_date']}.\nTotal: {$quote['currency']} {$quote['total']}\n\nGestionar: {$url}\n";
  $headers='From: '.($cfg['company_email']??'iescobar@puntodomotica.com');
  if(!$recipients||!mail(implode(',',$recipients),$subject,$body,$headers)){$db->prepare('UPDATE sales_quotes SET reminder_sent_at=NULL WHERE id=?')->execute([$quote['id']]);continue;}
  $sent++;
 }
 echo "Recordatorios enviados: {$sent}\n";
}catch(Throwable $error){
 if(PHP_SAPI!=='cli')http_response_code(500);
 error_log('No se pudieron procesar los recordatorios: '.$error->getMessage());echo "No se pudieron procesar los recordatorios.\n";exit(1);
}
