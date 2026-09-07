<?php
declare(strict_types=1);

// Compatibilidad financiera: si un pago se registró antes de existir el cargo de ingeniería,
// lo imputa automáticamente cuando el cargo ya está disponible. Es la misma prioridad que
// usa el alta de pagos: ingeniería antes que mano de obra/materiales.
session_start();
$root=dirname(__DIR__,3);
$cfg=require $root.'/config.php';
if(empty($_SESSION['user'])){header('Location:index.php');exit;}
$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
]);
$projectId=(int)($_GET['id']??0);
if($projectId>0){
 try{
  $db->beginTransaction();
  $ps=$db->prepare("SELECT p.id,p.currency,p.amount-COALESCE((SELECT SUM(a.amount) FROM allocations a WHERE a.payment_id=p.id),0) pending FROM payments p WHERE p.project_id=? HAVING pending>0.009 ORDER BY p.payment_date,p.id");
  $ps->execute([$projectId]);
  $payments=$ps->fetchAll();
  foreach($payments as $pay){
   $remaining=(float)$pay['pending'];
   if($remaining<=0.009)continue;
   $cs=$db->prepare("SELECT c.id,c.amount-COALESCE((SELECT SUM(a.amount) FROM allocations a WHERE a.charge_id=c.id),0) due FROM charges c WHERE c.project_id=? AND c.currency=? AND c.active=1 AND c.type='ingenieria' HAVING due>0.009 ORDER BY c.charge_date,c.id");
   $cs->execute([$projectId,$pay['currency']]);
   foreach($cs as $charge){
    if($remaining<=0.009)break;
    $amount=min($remaining,(float)$charge['due']);
    if($amount<=0.009)continue;
    $db->prepare('INSERT INTO allocations(payment_id,charge_id,amount) VALUES(?,?,?) ON DUPLICATE KEY UPDATE amount=amount+VALUES(amount)')->execute([(int)$pay['id'],(int)$charge['id'],$amount]);
    $remaining-=$amount;
   }
  }
  $db->commit();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();}
}
session_write_close();
require __DIR__.'/detail_v2.php';
