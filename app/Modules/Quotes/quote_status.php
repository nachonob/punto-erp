<?php
declare(strict_types=1);

if(session_status()!==PHP_SESSION_ACTIVE)session_start();
$root=dirname(__DIR__,3);
$cfg=require $root.'/config.php';
date_default_timezone_set($cfg['timezone']??'America/Argentina/Buenos_Aires');
if(empty($_SESSION['user'])){header('Location:index.php');exit;}
if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Solicitud vencida.');}

$id=(int)($_POST['quote_id']??0);
$status=(string)($_POST['status']??'');
if(!in_array($status,['aprobado_inicial','aprobado_definitivo','rechazado'],true)){http_response_code(422);exit('Estado inválido.');}

$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
]);
$s=$db->prepare('SELECT q.*,p.engineering_pct FROM quotes q JOIN projects p ON p.id=q.project_id WHERE q.id=?');
$s->execute([$id]);
$q=$s->fetch();
if(!$q){http_response_code(404);exit('Presupuesto inexistente.');}
if(!in_array($q['status'],['enviado','aprobado_inicial','aprobado_definitivo','final'],true)&&$status!=='rechazado'){
    $_SESSION['msg']='Primero enviá el presupuesto al cliente.';
    header('Location:index.php?a=quotes');exit;
}

function qsReplace(PDO $db,array $quote,int $newChargeId,array $types):void{
 $marks=implode(',',array_fill(0,count($types),'?'));
 $sql="SELECT c.id FROM charges c JOIN quotes oq ON oq.id=c.quote_id
       WHERE c.project_id=? AND c.active=1 AND c.id<>? AND c.type IN ($marks)
       AND oq.quote_series_key=?";
 $s=$db->prepare($sql);
 $s->execute(array_merge([(int)$quote['project_id'],$newChargeId],$types,[(string)$quote['quote_series_key']]));
 foreach($s as $old){
  $als=$db->prepare('SELECT payment_id,amount FROM allocations WHERE charge_id=?');$als->execute([$old['id']]);
  foreach($als as $al)$db->prepare('INSERT INTO allocations(payment_id,charge_id,amount) VALUES(?,?,?) ON DUPLICATE KEY UPDATE amount=amount+VALUES(amount)')->execute([$al['payment_id'],$newChargeId,$al['amount']]);
  $db->prepare('DELETE FROM allocations WHERE charge_id=?')->execute([$old['id']]);
  $db->prepare('UPDATE charges SET active=0 WHERE id=?')->execute([$old['id']]);
 }
}

$date=date('Y-m-d');
$db->beginTransaction();
$db->prepare('UPDATE quotes SET status=?,locked_at=COALESCE(locked_at,NOW()),next_followup_date=NULL,followup_closed_at=COALESCE(followup_closed_at,NOW()) WHERE id=?')->execute([$status,$id]);

if($status==='aprobado_inicial'){
 $amount=round((float)$q['total']*(float)$q['engineering_pct']/100,2);
 $db->prepare("INSERT INTO charges(project_id,quote_id,charge_date,type,currency,description,amount) VALUES(?,?,?,'ingenieria','USD',?,?)")
    ->execute([(int)$q['project_id'],$id,$date,'Adelanto de ingeniería '.(float)$q['engineering_pct'].'% · '.($q['proposal_name']?:'Presupuesto').' · v'.$q['version_no'],$amount]);
 $chargeId=(int)$db->lastInsertId();
 qsReplace($db,$q,$chargeId,['ingenieria']);
 $db->prepare("UPDATE projects SET status='aprobado' WHERE id=?")->execute([(int)$q['project_id']]);
}else if($status==='aprobado_definitivo'){
 $matFactor=($q['materials_tax_mode']??'sin_iva')==='mas_iva'?1+(float)$q['materials_vat_rate']/100:1;
 $laborFactor=($q['labor_tax_mode']??'sin_iva')==='mas_iva'?1+(float)$q['labor_vat_rate']/100:1;
 $materials=round((float)$q['materials_amount']*$matFactor,2);
 $labor=round((float)$q['labor_amount']*$laborFactor,2);
 $db->prepare("INSERT INTO charges(project_id,quote_id,charge_date,type,currency,description,amount) VALUES(?,?,?,'materiales','USD',?,?)")
    ->execute([(int)$q['project_id'],$id,$date,'Materiales · '.($q['proposal_name']?:'Presupuesto').' · v'.$q['version_no'],$materials]);
 $matId=(int)$db->lastInsertId();qsReplace($db,$q,$matId,['materiales']);
 if($labor>0){
  $db->prepare("INSERT INTO charges(project_id,quote_id,charge_date,type,currency,description,amount) VALUES(?,?,?,'mano_obra','USD',?,?)")
     ->execute([(int)$q['project_id'],$id,$date,'Mano de obra · '.($q['proposal_name']?:'Presupuesto').' · v'.$q['version_no'],$labor]);
  $labId=(int)$db->lastInsertId();qsReplace($db,$q,$labId,['ingenieria','mano_obra']);
 }
 $db->prepare("UPDATE projects SET status='en_obra' WHERE id=?")->execute([(int)$q['project_id']]);
}
try{
 $event=$status==='rechazado'?'rechazado':'aprobado';
 $db->prepare('INSERT INTO quote_followup_history(quote_id,user_id,event_type,next_contact_date,notes) VALUES(?,?,?,NULL,?)')
    ->execute([$id,(int)$_SESSION['user']['id'],$event,$status==='aprobado_inicial'?'Aceptado inicial':'Aceptado definitivo']);
}catch(Throwable $ignored){}
$db->commit();
$_SESSION['msg']=$status==='aprobado_inicial'?'Presupuesto marcado como Aceptado inicial.':($status==='aprobado_definitivo'?'Presupuesto marcado como Aceptado definitivo.':'Presupuesto rechazado.');
header('Location:index.php?a=quotes');
exit;
