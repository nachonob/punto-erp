<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);$id=(int)($_GET['id']??0);$email='';$whatsapp='';$client='';$project='';$version=1;
try{$cfg=require $root.'/config.php';$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$s=$db->prepare('SELECT q.version_no,p.project_number,c.business_name,c.contact_name,c.email,c.whatsapp FROM quotes q JOIN projects p ON p.id=q.project_id JOIN clients c ON c.id=p.client_id WHERE q.id=?');$s->execute([$id]);$r=$s->fetch()?:[];$email=trim((string)($r['email']??''));$whatsapp=preg_replace('/\D+/','',(string)($r['whatsapp']??''))??'';$client=trim((string)($r['contact_name']??''))?:trim((string)($r['business_name']??''));$project=(string)($r['project_number']??'');$version=(int)($r['version_no']??1);}catch(Throwable $e){}
ob_start();require __DIR__.'/print_v8.php';$html=ob_get_clean();
$subject='Presupuesto Punto Domótica · '.$project.' · v'.$version;$text='Hola'.($client!==''?' '.$client:'').', te enviamos el presupuesto correspondiente al proyecto '.$project.'.';
if($whatsapp!==''&&!str_starts_with($whatsapp,'54'))$whatsapp='54'.ltrim($whatsapp,'0');
$mail=$email!==''?'mailto:'.$email.'?subject='.rawurlencode($subject).'&body='.rawurlencode($text):'';$wa=$whatsapp!==''?'https://wa.me/'.$whatsapp.'?text='.rawurlencode($text):'';
$mailTag=$mail!==''?'<a id="mailQuoteBtn" class="mail-btn" href="'.htmlspecialchars($mail,ENT_QUOTES,'UTF-8').'">Enviar por mail</a>':'<a id="mailQuoteBtn" class="mail-btn" href="#" onclick="alert(\'El cliente no tiene un correo cargado.\');return false;">Enviar por mail</a>';
$waTag=$wa!==''?'<a id="waQuoteBtn" class="wa-btn" href="'.htmlspecialchars($wa,ENT_QUOTES,'UTF-8').'" target="_blank" rel="noopener">Enviar por WhatsApp</a>':'<a id="waQuoteBtn" class="wa-btn" href="#" onclick="alert(\'El cliente no tiene un WhatsApp cargado.\');return false;">Enviar por WhatsApp</a>';
$html=preg_replace('/<button id="mailQuoteBtn"[^>]*>Enviar por mail<\/button>/',$mailTag,$html)??$html;$html=preg_replace('/<button id="waQuoteBtn"[^>]*>Enviar por WhatsApp<\/button>/',$waTag,$html)??$html;
$html=str_replace('</head>','<style>.toolbar a.mail-btn{background:#3157a4;color:#fff}.toolbar a.wa-btn{background:#168c4d;color:#fff}</style></head>',$html);
// print_v7 deja un script que intenta reasignar onclick. Al ser enlaces nativos, anulamos esos ids para ese script y restauramos después.
$html=str_replace("const mail=document.getElementById('mailQuoteBtn'),wab=document.getElementById('waQuoteBtn');","const mail=null,wab=null;",$html);
echo $html;
