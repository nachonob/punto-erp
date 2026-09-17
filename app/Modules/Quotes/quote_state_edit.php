<?php
declare(strict_types=1);

if(session_status()!==PHP_SESSION_ACTIVE)session_start();
$root=dirname(__DIR__,3);
$cfg=require $root.'/config.php';
date_default_timezone_set($cfg['timezone']??'America/Argentina/Buenos_Aires');
if(empty($_SESSION['user'])){header('Location:index.php');exit;}
require_once $root.'/app/Core/UnifiedSidebar.php';

function qse(string|int|float|null $value):string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function qseUsd(float $value):string{return 'US$ '.number_format(round($value,2),2,',','.');}

$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
]);
$id=(int)($_GET['id']??0);
$s=$db->prepare("SELECT q.*,p.project_number,p.name project_name,c.business_name
 FROM quotes q JOIN projects p ON p.id=q.project_id JOIN clients c ON c.id=p.client_id WHERE q.id=?");
$s->execute([$id]);
$q=$s->fetch();
if(!$q){http_response_code(404);exit('Presupuesto inexistente.');}
if($q['status']==='borrador'){header('Location:index.php?a=edit_quote&id='.$id);exit;}
if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));
$msg=$_SESSION['msg']??null;unset($_SESSION['msg']);
$labels=['enviado'=>'Enviado','aprobado_inicial'=>'Aceptado inicial','aprobado_definitivo'=>'Aceptado definitivo','final'=>'Aceptado definitivo','rechazado'=>'Rechazado'];
$actions=[];
if($q['status']==='enviado')$actions=['aprobado_inicial'=>'Aceptar inicial','aprobado_definitivo'=>'Aceptar definitivo','rechazado'=>'Marcar rechazado'];
elseif($q['status']==='aprobado_inicial')$actions=['aprobado_definitivo'=>'Aceptar definitivo','rechazado'=>'Marcar rechazado'];
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Estado del presupuesto · Punto ERP</title>
<style><?=erpSidebarCss()?>body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.45 system-ui,-apple-system,Segoe UI,sans-serif}.main{margin-left:var(--sidebar);padding:32px 4%;max-width:1200px}.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:22px;margin-bottom:18px}.row{display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap}.actions{display:flex;gap:9px;align-items:center;flex-wrap:wrap}.actions form{margin:0}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:8px;padding:10px 14px;background:var(--o);color:#fff;text-decoration:none;font-weight:700;cursor:pointer}.btn.gray{background:#edf0f3;color:#27303b}.btn.dark{background:#252b32;color:#fff}.btn.danger{background:#a92727}.muted{color:var(--muted)}.pill{display:inline-block;padding:5px 10px;border-radius:20px;background:#edf0f3;font-size:13px;font-weight:700}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 24px}.field small{display:block;color:var(--muted);text-transform:uppercase;font-size:11px;font-weight:700;margin-bottom:3px}.lock{padding:14px 16px;background:#fff5ed;border:1px solid #ffd2b1;border-radius:10px}.flash{padding:12px 15px;background:#edf8ef;border:1px solid #bfe0c4;border-radius:9px;margin-bottom:18px}@media(max-width:900px){.main{margin-left:0;padding:22px 18px}.grid{grid-template-columns:1fr}}</style>
</head><body><?php erpSidebar('quote_state_edit');?><main class="main">
<?php if($msg):?><div class="flash"><?=qse($msg)?></div><?php endif;?>
<div class="row"><div><a class="muted" href="?a=quotes">← Volver a presupuestos</a><h1 style="margin-bottom:5px"><?=qse($q['proposal_name']?:'Presupuesto')?></h1><span class="pill"><?=qse($labels[$q['status']]??$q['status'])?></span></div><div class="actions"><a class="btn gray" href="?a=quote_view&id=<?=$id?>">Ver presupuesto / PDF</a><form method="post" action="?a=duplicate_quote" onsubmit="return confirm('¿Crear la siguiente versión editable de este presupuesto?')"><input type="hidden" name="csrf" value="<?=qse($_SESSION['csrf'])?>"><input type="hidden" name="quote_id" value="<?=$id?>"><button class="btn dark" type="submit">Duplicar versión</button></form></div></div>
<div class="card" style="margin-top:20px"><div class="grid"><div class="field"><small>Cliente</small><b><?=qse($q['business_name'])?></b></div><div class="field"><small>Proyecto</small><b><?=qse($q['project_number'].' · '.$q['project_name'])?></b></div><div class="field"><small>Rubro</small><b><?=qse($q['quote_category']?:'General')?></b></div><div class="field"><small>Versión</small><b>v<?=qse($q['version_no'])?></b></div><div class="field"><small>Total</small><b><?=qseUsd((float)$q['total'])?></b></div><div class="field"><small>Enviado</small><b><?=!empty($q['sent_at'])?qse(date('d/m/Y',strtotime($q['sent_at']))):'—'?></b></div></div></div>
<div class="lock"><b>Materiales bloqueados</b><br><span class="muted">Como este presupuesto ya fue enviado, sus productos, cantidades y precios no se pueden modificar. Para cambiar el contenido, duplicalo y trabajá sobre la nueva versión.</span></div>
<div class="card" style="margin-top:18px"><h2 style="margin-top:0">Cambiar estado</h2>
<?php if($actions):?><p class="muted">Elegí el próximo estado comercial del presupuesto.</p><div class="actions"><?php foreach($actions as $value=>$label):?><form method="post" action="?a=quote_status" onsubmit="return confirm('¿Confirmar el cambio de estado?')"><input type="hidden" name="csrf" value="<?=qse($_SESSION['csrf'])?>"><input type="hidden" name="quote_id" value="<?=$id?>"><input type="hidden" name="status" value="<?=qse($value)?>"><button class="btn <?=$value==='rechazado'?'danger':''?>" type="submit"><?=qse($label)?></button></form><?php endforeach;?></div>
<?php else:?><p class="muted">Este presupuesto está en estado <b><?=qse($labels[$q['status']]??$q['status'])?></b>. Podés verlo o duplicarlo para crear una nueva versión.</p><?php endif;?>
</div></main></body></html>
