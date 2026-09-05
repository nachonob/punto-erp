<?php
declare(strict_types=1);
session_start();
$root=dirname(__DIR__,3);
$cfg=require $root.'/config.php';
date_default_timezone_set($cfg['timezone']??'America/Argentina/Buenos_Aires');
$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[
 PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
]);
if(empty($_SESSION['user'])){header('Location:index.php');exit;}
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function usd($n):string{return 'US$ '.number_format((float)$n,2,',','.');}
function imgData($data,$mime):string{return (!empty($data)&&!empty($mime))?'data:'.$mime.';base64,'.base64_encode($data):'';}
$id=(int)($_GET['id']??0);
$s=$db->prepare("SELECT q.*,p.project_number,p.name project_name,p.engineering_pct,c.business_name,c.contact_name,c.cuit,c.email,c.whatsapp,c.address,c.city,c.province,pp.business_name partner_name,pl.name price_list_name FROM quotes q JOIN projects p ON p.id=q.project_id JOIN clients c ON c.id=p.client_id LEFT JOIN project_partners pp ON pp.id=p.partner_id LEFT JOIN price_lists pl ON pl.id=q.price_list_id WHERE q.id=?");
$s->execute([$id]);$q=$s->fetch();if(!$q)exit('Presupuesto inexistente.');
$s=$db->prepare("SELECT qi.*,p.image_data,p.image_mime FROM quote_items qi LEFT JOIN products p ON p.id=qi.product_id WHERE qi.quote_id=? ORDER BY qi.sort_order,qi.id");
$s->execute([$id]);$items=$s->fetchAll();
$logo='https://puntodomotica.com/assets/images/cropped-logo-blanco-63892739ae.png';
$print=($_GET['a']??'')==='quote_print';
$families=array_filter(array_map('trim',explode(',',(string)($q['quote_families']??''))));
$template=(string)($q['quote_template_family']??($families[0]??''));
$familyLabel=['lifesmart'=>'LifeSmart','control4'=>'Control4','shelly'=>'Shelly'][$template]??ucfirst($template);
$mat=(float)$q['materials_amount'];
$lab=(float)$q['labor_amount'];
$total=(float)$q['total'];
$taxDelta=max(0,$total-$mat-$lab);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Presupuesto <?=e($q['project_number'])?></title>
<style>
@page{size:A4;margin:0}
*{box-sizing:border-box}
body{margin:0;background:#e8e8e8;color:#202124;font-family:Aptos,"Segoe UI","Helvetica Neue",Arial,sans-serif;font-size:12px}
.toolbar{width:210mm;margin:12px auto;display:flex;gap:8px;align-items:center}
.toolbar a,.toolbar button{border:0;border-radius:7px;padding:10px 14px;background:#ff6702;color:#fff;text-decoration:none;font-weight:700;cursor:pointer}
.toolbar .dark{background:#242122}
.toolbar .light{background:#fff;color:#242122;border:1px solid #d4d4d4}
.sheet{position:relative;width:210mm;min-height:297mm;margin:0 auto 20px;background:#fff;overflow:hidden;box-shadow:0 12px 34px rgba(0,0,0,.14)}
.top-orange{position:absolute;left:0;top:0;width:60mm;height:28mm;background:#ff6702;clip-path:polygon(0 0,100% 0,49% 100%,0 100%)}
.top-black{position:absolute;left:0;top:0;width:139mm;height:44mm;background:#242122;clip-path:polygon(35% 0,100% 0,0 100%,0 64%)}
.top-gray{position:absolute;left:0;top:42mm;width:64mm;height:25mm;background:#f1f2f2;clip-path:polygon(0 0,100% 0,0 100%)}
.header{position:relative;z-index:3;height:66mm;padding:0 16mm}
.logo{position:absolute;left:14mm;top:10mm;width:54mm;height:auto;display:block}
.titlebox{position:absolute;right:16mm;top:14mm;text-align:right}
.titlebox h1{margin:0;color:#ff6702;font-size:30px;line-height:1;font-weight:500;letter-spacing:.15px}
.titlebox .n{margin-top:9mm;font-size:10.5px;color:#4c5662;line-height:1.45}
.titlebox .n b{display:inline-block;min-width:23mm;text-align:left;color:#222;font-weight:700}
.info{display:grid;grid-template-columns:1.14fr .86fr;gap:18mm;padding:0 16mm 11mm}
.client{padding-top:1mm}.client h3{font-size:14px;margin:0 0 3px;letter-spacing:.1px}.client .name{font-size:13.5px;font-weight:700;margin-bottom:2px}.client p{margin:1px 0;color:#5b6570;font-size:11.5px}
.projectmeta{padding-top:1mm}.projectmeta .row{display:grid;grid-template-columns:28mm 1fr;gap:4mm;margin:5px 0;font-size:11.5px}.projectmeta .row b{font-size:11px}.projectmeta .row span{text-align:right;color:#39434d}
.tablewrap{padding:0 15mm}
.equipment{width:100%;border-collapse:collapse;table-layout:fixed}
.equipment th{background:#ff6702;color:#fff;height:12mm;padding:0 4mm;text-align:left;font-size:10.5px;font-weight:700;letter-spacing:.15px}
.equipment td{height:18.5mm;border-bottom:1px solid #d7d7d7;padding:2.6mm 4mm;vertical-align:middle;font-size:11.5px;line-height:1.32}
.equipment tbody tr:nth-child(even) td{background:#fbfbfb}
.equipment .pic{width:15mm;text-align:center;padding-left:2mm;padding-right:2mm}
.equipment .pic img{max-width:12.5mm;max-height:13.5mm;object-fit:contain;display:block;margin:auto}
.placeholder{width:11.5mm;height:11.5mm;border:1px solid #e4e4e4;border-radius:2px;background:#fff;margin:auto}
.equipment .sku{width:27mm;font-weight:700;font-size:10px}
.equipment .desc{width:auto;font-size:11.7px}
.equipment .qty{width:16mm;text-align:center;font-size:11.5px}
.equipment .price{width:25mm;text-align:right;white-space:nowrap;font-size:11.5px}
.equipment .subtotal{width:27mm;text-align:right;font-weight:700;white-space:nowrap;font-size:11.5px}
.labor{margin:11mm 16mm 0}.labor h3{margin:0 0 4mm;color:#ff6702;font-size:13.5px}.laborline{border-left:3px solid #ff6702;padding:2mm 0 2mm 4mm;min-height:10mm;font-size:11.5px}
.bottom{display:grid;grid-template-columns:1fr 67mm;gap:18mm;margin:10mm 16mm 27mm;align-items:start}.notes h3{margin:0 0 3mm;color:#ff6702;font-size:12.5px}.notes p{margin:0;white-space:pre-line;color:#4f565d;font-size:10.5px;line-height:1.5}.summary{font-size:11.5px}.summary .r{display:flex;justify-content:space-between;gap:8mm;padding:2.5mm 0}.summary .grand{border-top:1px solid #333;margin-top:3mm;padding-top:4mm;color:#ff6702;font-size:19px;font-weight:700}
.footerbrand{position:absolute;left:16mm;bottom:10mm;color:#ff6702;font-weight:700;font-size:11.5px}.footnote{position:absolute;right:16mm;bottom:10mm;text-align:right;font-size:9.5px;color:#4d4d4d}.bottom-black{position:absolute;right:0;bottom:0;width:82mm;height:25mm;background:#242122;clip-path:polygon(42% 100%,100% 16%,100% 100%)}.bottom-orange{position:absolute;right:0;bottom:0;width:29mm;height:16mm;background:#ff6702;clip-path:polygon(35% 100%,100% 12%,100% 100%)}
.screen-note{width:210mm;margin:0 auto 18px;background:#fff;border:1px solid #ddd;padding:10px 14px;font-size:12px}
@media print{body{background:#fff}.toolbar,.screen-note{display:none!important}.sheet{margin:0;box-shadow:none}}
</style>
</head>
<body>
<?php if(!$print):?>
<div class="toolbar">
 <a class="light" href="?a=quotes">← Presupuestos</a>
 <a class="dark" href="?a=edit_quote&id=<?=$id?>">Editar</a>
 <button onclick="window.print()">Imprimir / Guardar PDF</button>
</div>
<?php endif;?>
<section class="sheet">
 <div class="top-orange"></div><div class="top-black"></div><div class="top-gray"></div>
 <header class="header">
   <img class="logo" src="<?=$logo?>" alt="Punto Domótica">
   <div class="titlebox">
     <h1>PRESUPUESTO</h1>
     <div class="n"><b>Presupuesto</b> <?=e($q['project_number'])?> · v<?=e($q['version_no'])?><br><b>Fecha</b> <?=e(date('d / m / Y',strtotime($q['quote_date'])))?></div>
   </div>
 </header>
 <div class="info">
   <div class="client">
     <h3>Presupuesto para:</h3>
     <div class="name"><?=e($q['business_name'])?></div>
     <?php if(!empty($q['contact_name'])):?><p><?=e($q['contact_name'])?></p><?php endif;?>
     <?php if(!empty($q['city'])||!empty($q['province'])):?><p><?=e(trim(($q['city']??'').(empty($q['city'])||empty($q['province'])?'':' · ').($q['province']??'')))?></p><?php endif;?>
   </div>
   <div class="projectmeta">
     <div class="row"><b>Proyecto</b><span><?=e($q['project_number'])?></span></div>
     <div class="row"><b>Obra</b><span><?=e($q['project_name'])?></span></div>
     <div class="row"><b>Fecha</b><span><?=e(date('d/m/Y',strtotime($q['quote_date'])))?></span></div>
     <?php if(!empty($q['partner_name'])):?><div class="row"><b>Arquitecto</b><span><?=e($q['partner_name'])?></span></div><?php endif;?>
     <div class="row"><b>Tecnología</b><span><?=e($familyLabel)?></span></div>
   </div>
 </div>
 <div class="tablewrap"><table class="equipment">
   <thead><tr><th class="pic"></th><th class="sku">SKU</th><th class="desc">Descripción</th><th class="qty">Cant.</th><th class="price">Precio</th><th class="subtotal">Total</th></tr></thead>
   <tbody><?php foreach($items as $it):$src=imgData($it['image_data']??null,$it['image_mime']??null);?><tr>
     <td class="pic"><?php if($src):?><img src="<?=$src?>" alt=""><?php else:?><div class="placeholder"></div><?php endif;?></td>
     <td class="sku"><?=e($it['sku']?:'—')?></td><td class="desc"><?=e($it['description'])?></td><td class="qty"><?=e(rtrim(rtrim(number_format((float)$it['quantity'],2,'.',''),'0'),'.'))?></td><td class="price"><?=usd($it['unit_price'])?></td><td class="subtotal"><?=usd($it['subtotal'])?></td>
   </tr><?php endforeach;?></tbody>
 </table></div>
 <?php if($lab>0):?><div class="labor"><h3>Mano de obra</h3><div class="laborline"><?=e($q['labor_description']?:'Configuración, montaje y diseño de escenas')?></div></div><?php endif;?>
 <div class="bottom"><div class="notes"><?php if(trim((string)$q['notes'])!==''):?><h3>Observaciones</h3><p><?=e($q['notes'])?></p><?php endif;?></div><div class="summary"><div class="r"><span>Equipamiento</span><b><?=usd($mat)?></b></div><?php if($lab>0):?><div class="r"><span>Mano de obra</span><b><?=usd($lab)?></b></div><?php endif;?><?php if($taxDelta>0.005):?><div class="r"><span>IVA / impuestos</span><b><?=usd($taxDelta)?></b></div><?php endif;?><div class="r grand"><span>TOTAL</span><span><?=usd($total)?></span></div></div></div>
 <div class="footerbrand">Punto Domótica<div style="color:#444;font-weight:400;font-size:9.5px">Espacios inteligentes</div></div><div class="footnote">Valores expresados en dólares estadounidenses (USD)<br>Presupuesto <?=e($q['project_number'])?> · versión <?=e($q['version_no'])?></div><div class="bottom-black"></div><div class="bottom-orange"></div>
</section>
<?php if(!$print):?><div class="screen-note">Al generar el paquete final se usará el prólogo correspondiente a <b><?=e($familyLabel)?></b> antes de este presupuesto. La sección de forma de pago se anexará después del presupuesto.</div><?php endif;?>
<?php if($print):?><script>window.addEventListener('load',()=>setTimeout(()=>window.print(),350));</script><?php endif;?>
</body></html>
