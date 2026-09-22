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
$items=[];
if($q){
 $itemsStmt=$db->prepare("SELECT sku,category,brand,description,quantity,unit,unit_price,subtotal FROM quote_items WHERE quote_id=? AND COALESCE(sku,'') NOT IN ('__CONCEPT__','__CONCEPT_TOTAL__') ORDER BY block_order,sort_order,id");
 $itemsStmt->execute([$id]);
 $items=$itemsStmt->fetchAll();
}
if(!$q){http_response_code(404);exit('Presupuesto inexistente.');}
if($q['status']==='borrador'){header('Location:index.php?a=edit_quote&id='.$id);exit;}
if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));
$msg=$_SESSION['msg']??null;unset($_SESSION['msg']);
$labels=['enviado'=>'Enviado','aprobado_inicial'=>'Aceptado inicial','aprobado_definitivo'=>'Aceptado definitivo','final'=>'Aceptado definitivo','rechazado'=>'Rechazado'];
$rubros=$db->query('SELECT name FROM quote_rubros WHERE active=1 ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
$templates=['lifesmart'=>'LifeSmart','control4'=>'Control4','shelly'=>'Shelly'];
$permissions=$_SESSION['user']['permissions']??[];
$canRegisterPayment=(($_SESSION['user']['role']??'')==='admin')||!empty($permissions['payments']['manage'])||!empty($permissions['receipts']['manage'])||!empty($permissions['projects']['manage']);
$canUnlock=in_array($q['status'],['enviado','aprobado_inicial'],true);
$actions=[];
if($q['status']==='enviado')$actions=['aprobado_inicial'=>'Aceptar inicial','aprobado_definitivo'=>'Aceptar definitivo','rechazado'=>'Marcar rechazado'];
elseif($q['status']==='aprobado_inicial')$actions=['aprobado_definitivo'=>'Aceptar definitivo','rechazado'=>'Marcar rechazado'];
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Estado del presupuesto · Punto ERP</title>
<style><?=erpSidebarCss()?>body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.45 system-ui,-apple-system,Segoe UI,sans-serif}.main{margin-left:var(--sidebar);padding:32px 4%;max-width:1200px}.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:22px;margin-bottom:18px}.row{display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap}.actions{display:flex;gap:9px;align-items:center;flex-wrap:wrap}.actions form{margin:0}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:8px;padding:10px 14px;background:var(--o);color:#fff;text-decoration:none;font-weight:700;cursor:pointer}.btn.gray{background:#edf0f3;color:#27303b}.btn.dark{background:#252b32;color:#fff}.btn.danger{background:#a92727}.muted{color:var(--muted)}.pill{display:inline-block;padding:5px 10px;border-radius:20px;background:#edf0f3;font-size:13px;font-weight:700}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 24px}.field small{display:block;color:var(--muted);text-transform:uppercase;font-size:11px;font-weight:700;margin-bottom:3px}.lock{padding:14px 16px;background:#fff5ed;border:1px solid #ffd2b1;border-radius:10px}.flash{padding:12px 15px;background:#edf8ef;border:1px solid #bfe0c4;border-radius:9px;margin-bottom:18px}.edit-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 18px}.edit-grid .wide{grid-column:1/-1}.edit-grid label{display:block;margin-bottom:5px;font-weight:700}.edit-grid input,.edit-grid select,.edit-grid textarea{width:100%;padding:10px 11px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--ink);font:inherit}.edit-grid textarea{min-height:100px;resize:vertical}.readonly-note{margin:12px 0 0;color:var(--muted);font-size:13px}.table-wrap{overflow:auto}.products{width:100%;border-collapse:collapse}.products th,.products td{padding:11px 9px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}.products th{font-size:11px;text-transform:uppercase;color:var(--muted);white-space:nowrap}.products .num{text-align:right;white-space:nowrap}.products tbody tr:last-child td{border-bottom:0}.product-name{font-weight:700}.product-meta{font-size:12px;color:var(--muted);margin-top:3px}@media(max-width:900px){.main{margin-left:0;padding:22px 18px}.grid,.edit-grid{grid-template-columns:1fr}.edit-grid .wide{grid-column:auto}}</style>
</head><body><?php erpSidebar('quote_state_edit');?><main class="main">
<?php if($msg):?><div class="flash"><?=qse($msg)?></div><?php endif;?>
<div class="row"><div><a class="muted" href="?a=quotes">← Volver a presupuestos</a><h1 style="margin-bottom:5px"><?=qse($q['proposal_name']?:'Presupuesto')?></h1><span class="pill"><?=qse($labels[$q['status']]??$q['status'])?></span></div><div class="actions"><a class="btn gray" href="?a=quote_view&id=<?=$id?>">Ver presupuesto / PDF</a><?php if($canRegisterPayment&&$q['status']==='enviado'):?><form method="post" action="?a=quote_status" onsubmit="return confirm('¿Aceptar inicialmente este presupuesto y registrar el pago?')"><input type="hidden" name="csrf" value="<?=qse($_SESSION['csrf'])?>"><input type="hidden" name="quote_id" value="<?=$id?>"><input type="hidden" name="status" value="aprobado_inicial"><input type="hidden" name="redirect_payment" value="1"><button class="btn" type="submit">Aceptar inicial y registrar pago</button></form><?php elseif($canRegisterPayment&&in_array($q['status'],['aprobado_inicial','aprobado_definitivo','final'],true)):?><a class="btn" href="?a=new_payment&project_id=<?=(int)$q['project_id']?>">Registrar pago</a><?php endif;?><?php if($canUnlock):?><form method="post" action="?a=quote_unlock" onsubmit="return confirm('Esta misma versión volverá a Borrador y podrás modificar materiales, cantidades, precios y mano de obra. Los pagos registrados se conservarán. ¿Querés continuar?')"><input type="hidden" name="csrf" value="<?=qse($_SESSION['csrf'])?>"><input type="hidden" name="quote_id" value="<?=$id?>"><button class="btn gray" type="submit">Desbloquear para editar</button></form><?php endif;?><form method="post" action="?a=duplicate_quote" onsubmit="return confirm('¿Crear la siguiente versión editable de este presupuesto?')"><input type="hidden" name="csrf" value="<?=qse($_SESSION['csrf'])?>"><input type="hidden" name="quote_id" value="<?=$id?>"><button class="btn dark" type="submit">Duplicar versión</button></form></div></div>
<div class="card" style="margin-top:20px"><div class="grid"><div class="field"><small>Cliente</small><b><?=qse($q['business_name'])?></b></div><div class="field"><small>Proyecto</small><b><?=qse($q['project_number'].' · '.$q['project_name'])?></b></div><div class="field"><small>Rubro</small><b><?=qse($q['quote_category']?:'General')?></b></div><div class="field"><small>Versión</small><b>v<?=qse($q['version_no'])?></b></div><div class="field"><small>Total</small><b><?=qseUsd((float)$q['total'])?></b></div><div class="field"><small>Enviado</small><b><?=!empty($q['sent_at'])?qse(date('d/m/Y',strtotime($q['sent_at']))):'—'?></b></div></div></div>
<div class="card"><h2 style="margin-top:0">Editar datos generales</h2><p class="muted">Podés modificar la identificación y presentación del presupuesto. Los productos, cantidades, precios, totales, versión y estado permanecen protegidos.</p>
<form method="post" action="?a=quote_metadata_update"><input type="hidden" name="csrf" value="<?=qse($_SESSION['csrf'])?>"><input type="hidden" name="quote_id" value="<?=$id?>">
<div class="edit-grid"><div class="wide"><label>Nombre del presupuesto</label><input type="text" name="proposal_name" value="<?=qse($q['proposal_name']??'')?>" required maxlength="190"></div>
<div><label>Rubro</label><select name="quote_category" required><?php foreach($rubros as $rubro):?><option value="<?=qse($rubro)?>" <?=$rubro===$q['quote_category']?'selected':''?>><?=qse($rubro)?></option><?php endforeach;?></select></div>
<div><label>Plantilla del PDF</label><select name="quote_template_family"><?php foreach($templates as $value=>$label):?><option value="<?=qse($value)?>" <?=$value===($q['quote_template_family']??'')?'selected':''?>><?=qse($label)?></option><?php endforeach;?></select></div>
<div><label>Fecha del presupuesto</label><input type="date" name="quote_date" value="<?=qse($q['quote_date'])?>" required></div>
<div><label>Fecha de envío</label><input type="date" name="sent_at" value="<?=qse($q['sent_at']??'')?>"></div>
<div class="wide"><label>Notas y condiciones</label><textarea name="notes"><?=qse($q['notes']??'')?></textarea></div></div>
<p class="readonly-note">Cliente, proyecto, versión, estado, materiales y valores no se modifican desde esta sección.</p><div class="actions" style="margin-top:16px"><button class="btn" type="submit">Guardar datos generales</button></div></form></div>
<div class="lock"><b>Materiales bloqueados</b><br><span class="muted"><?php if($canUnlock):?>Podés usar <b>Desbloquear para editar</b> para modificar esta misma versión. Volverá a Borrador y conservará los pagos registrados. También podés duplicarla si preferís mantener intacto lo enviado al cliente.<?php else:?>Este presupuesto ya fue aceptado definitivamente y puede tener movimientos de entrega o stock. Para cambiar el contenido, duplicalo y trabajá sobre la nueva versión.<?php endif;?></span></div>
<div class="card" style="margin-top:18px"><div class="row"><div><h2 style="margin:0">Productos y materiales</h2><p class="muted" style="margin:5px 0 0">Detalle enviado al cliente · solo lectura</p></div><span class="pill"><?=count($items)?> ítem<?=count($items)===1?'':'s'?></span></div>
<?php if($items):?><div class="table-wrap" style="margin-top:15px"><table class="products"><thead><tr><th>Código</th><th>Producto</th><th>Categoría / marca</th><th class="num">Cantidad</th><th class="num">Precio unitario</th><th class="num">Subtotal</th></tr></thead><tbody>
<?php foreach($items as $item):?><tr><td><?=qse($item['sku']?:'—')?></td><td><div class="product-name"><?=qse($item['description'])?></div></td><td><?=qse($item['category']?:'—')?><?php if(!empty($item['brand'])):?><div class="product-meta"><?=qse($item['brand'])?></div><?php endif;?></td><td class="num"><?=qse(number_format((float)$item['quantity'],2,',','.'))?> <?=qse($item['unit']??'')?></td><td class="num"><?=qseUsd((float)$item['unit_price'])?></td><td class="num"><b><?=qseUsd((float)$item['subtotal'])?></b></td></tr><?php endforeach;?>
</tbody></table></div><?php else:?><p class="muted" style="margin-bottom:0">Este presupuesto no tiene productos cargados.</p><?php endif;?></div>
<?php if(in_array($q['status'],['aprobado_definitivo','final'],true)):?><div class="card" style="margin-top:18px"><div class="row"><div><h2 style="margin:0">Entrega de materiales</h2><p class="muted" style="margin:5px 0 0">Prepará un retiro parcial o total, generá el remito y descontá el stock al confirmar.</p></div><a class="btn" href="?a=new_delivery&quote_id=<?=$id?>">Preparar entrega / remito</a></div></div><?php endif;?>
<div class="card" style="margin-top:18px"><h2 style="margin-top:0">Cambiar estado</h2>
<?php if($actions):?><p class="muted">Elegí el próximo estado comercial del presupuesto.</p><div class="actions"><?php foreach($actions as $value=>$label):?><form method="post" action="?a=quote_status" onsubmit="return confirm('¿Confirmar el cambio de estado?')"><input type="hidden" name="csrf" value="<?=qse($_SESSION['csrf'])?>"><input type="hidden" name="quote_id" value="<?=$id?>"><input type="hidden" name="status" value="<?=qse($value)?>"><button class="btn <?=$value==='rechazado'?'danger':''?>" type="submit"><?=qse($label)?></button></form><?php endforeach;?></div>
<?php else:?><p class="muted">Este presupuesto está en estado <b><?=qse($labels[$q['status']]??$q['status'])?></b>. Podés verlo o duplicarlo para crear una nueva versión.</p><?php endif;?>
</div></main></body></html>
