<?php
declare(strict_types=1);
session_start();
function backWithError(string $message):never{$_SESSION['client_form_error']=$message;$_SESSION['client_form_old']=$_POST;header('Location: index.php');exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: index.php');exit;}
if(!hash_equals($_SESSION['client_form_csrf']??'',$_POST['csrf']??''))backWithError('La solicitud venció. Volvé a intentarlo.');
if(trim($_POST['website']??'')!=='')backWithError('No pudimos procesar la solicitud.');
$expected=(int)($_SESSION['client_form_a']??0)+(int)($_SESSION['client_form_b']??0);
$answer=filter_var($_POST['sum_answer']??null,FILTER_VALIDATE_INT);
unset($_SESSION['client_form_a'],$_SESSION['client_form_b']);
if($answer===false||$answer!==$expected)backWithError('La suma de seguridad es incorrecta. Intentá nuevamente.');
$customerName=trim($_POST['customer_name']??'');$company=trim($_POST['business_name']??'');$cuit=trim($_POST['cuit']??'');$iva=$_POST['iva_condition']??'';$email=strtolower(trim($_POST['email']??''));$whatsapp=trim($_POST['whatsapp']??'');$address=trim($_POST['address']??'');$city=trim($_POST['city']??'');$province=trim($_POST['province']??'');$country=trim($_POST['country']??'');
$allowedIva=['responsable_inscripto','monotributista','exento','consumidor_final','no_responsable'];
if($customerName===''||$city===''||$province===''||$country==='')backWithError('Completá todos los campos obligatorios.');
if($email===''&&$whatsapp==='')backWithError('Ingresá al menos un medio de contacto: email o WhatsApp.');
if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))backWithError('El email ingresado no es válido.');
if(!in_array($iva,$allowedIva,true))backWithError('Seleccioná una condición frente al IVA.');
if(empty($_POST['consent']))backWithError('Necesitamos tu autorización para registrar los datos.');
$configFile=null;foreach([dirname(__DIR__).'/config.php',dirname(__DIR__,2).'/erp/config.php',dirname(__DIR__,2).'/erp-dev/config.php'] as $candidate){if(is_file($candidate)){$configFile=$candidate;break;}}
if(!$configFile)backWithError('El formulario está temporalmente fuera de servicio.');
$cfg=require $configFile;
try{
    $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $checks=[];$params=[];if($cuit!==''){$checks[]='cuit=?';$params[]=$cuit;}if($email!==''){$checks[]='email=?';$params[]=$email;}if($whatsapp!==''){$checks[]='whatsapp=?';$params[]=$whatsapp;}
    $existing=false;if($checks){$s=$db->prepare('SELECT id FROM clients WHERE '.implode(' OR ',$checks).' LIMIT 1');$s->execute($params);$existing=(bool)$s->fetchColumn();}
    if($existing){unset($_SESSION['client_form_old']);header('Location: index.php?existing=1');exit;}
    $businessName=$company!==''?$company:$customerName;$contactName=$company!==''?$customerName:'';
    $db->prepare('INSERT INTO clients(business_name,contact_name,cuit,iva_condition,email,whatsapp,address,city,province,country,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$businessName,$contactName,$cuit,$iva,$email,$whatsapp,$address,$city,$province,$country,'Alta recibida desde la web pública.']);
    $labels=['responsable_inscripto'=>'Responsable inscripto','monotributista'=>'Monotributista','exento'=>'Exento','consumidor_final'=>'Consumidor final','no_responsable'=>'No responsable'];
    $safe=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
    $subject='Nuevo cliente registrado · Punto Domótica';
    $body='<h2>Nuevo cliente registrado desde la web</h2><p><b>Cliente:</b> '.$safe($businessName).'</p><p><b>Contacto:</b> '.$safe($customerName).'</p><p><b>CUIT/DNI:</b> '.$safe($cuit?:'No informado').'</p><p><b>Condición IVA:</b> '.$safe($labels[$iva]??$iva).'</p><p><b>Email:</b> '.$safe($email?:'No informado').'</p><p><b>WhatsApp:</b> '.$safe($whatsapp?:'No informado').'</p><p><b>Domicilio:</b> '.$safe($address?:'No informado').'</p><p><b>Ubicación:</b> '.$safe($city.', '.$province.', '.$country).'</p><p>El cliente ya está disponible en Punto ERP.</p>';
    $from=$cfg['company_email']??'iescobar@puntodomotica.com';$headers="MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: Punto Domótica <{$from}>\r\n";if($email!=='')$headers.="Reply-To: {$email}\r\n";
    @mail('iescobar@puntodomotica.com',$subject,$body,$headers);
    unset($_SESSION['client_form_old']);header('Location: index.php?ok=1');exit;
}catch(Throwable $e){backWithError('No pudimos registrar tus datos en este momento. Intentá nuevamente más tarde.');}
