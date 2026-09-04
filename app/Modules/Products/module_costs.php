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
$_SESSION['csrf']??=bin2hex(random_bytes(24));
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function go(string $u):never{header('Location:'.$u);exit;}
function csrf():void{if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Solicitud vencida.');}}
function canManage():bool{
 if(($_SESSION['user']['role']??'')==='admin')return true;
 return !empty($_SESSION['user']['permissions']['products']['manage']);
}
function usd($n):string{return 'US$ '.number_format((float)$n,2,',','.');}
function sale(float $cost,float $markup):float{return round($cost*(1+$markup/100),2);}
function top(string $title):void{$msg=$_SESSION['msg']??null;unset($_SESSION['msg']);?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($title)?> · Punto ERP</title><style>
:root{--o:#ff6702;--dark:#171b20;--ink:#27303b;--muted:#6e7781;--line:#e1e5e9;--bg:#f4f6f8;--sidebar:270px}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.45 system-ui,-apple-system,Segoe UI,sans-serif}
.sidebar{position:fixed;inset:0 auto 0 0;width:var(--sidebar);padding:28px 18px;background:var(--dark);color:#fff}.brand{font-size:22px;font-weight:800;padding:0 12px 24px;border-bottom:1px solid #ffffff18}.brand i{color:var(--o);font-style:normal}
.nav{display:flex;flex-direction:column;gap:5px;padding:22px 0}.nav a{padding:11px 13px;border-radius:9px;color:#d8dde3;text-decoration:none;font-weight:600}.nav a:hover,.nav a.active{background:var(--o);color:#fff}.main{margin-left:var(--sidebar);padding:32px 4%}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:22px;margin-bottom:18px;box-shadow:0 4px 16px #00000008}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:16px}.c3{grid-column:span 3}.c4{grid-column:span 4}.c6{grid-column:span 6}.c8{grid-column:span 8}.c12{grid-column:span 12}
label{display:block;font-weight:650;margin-bottom:6px}input,select,textarea{width:100%;padding:10px 11px;border:1px solid #cbd1d7;border-radius:8px;background:#fff;font:inherit}input[type=checkbox]{width:auto}textarea{min-height:88px}.btn{display:inline-block;border:0;border-radius:8px;padding:10px 15px;background:var(--o);color:#fff;text-decoration:none;font-weight:700;cursor:pointer}.btn.light{background:#edf0f3;color:var(--ink)}.actions{display:flex;gap:9px;align-items:center;flex-wrap:wrap}.muted{color:var(--muted)}.flash{padding:12px 16px;background:#fff0e5;border:1px solid #ffd2b1;border-radius:9px;margin-bottom:18px}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:11px 9px;border-bottom:1px solid var(--line);vertical-align:top}th{font-size:12px;text-transform:uppercase;color:var(--muted)}.right{text-align:right}.pill{display:inline-block;padding:4px 9px;border-radius:20px;background:#edf0f3;font-size:12px}.kpi{font-size:25px;font-weight:800}.scroll{overflow:auto}
@media(max-width:900px){.sidebar{display:none}.main{margin-left:0;padding:22px}.c3,.c4,.c6,.c8{grid-column:span 12}}
</style></head><body><aside class="sidebar"><div class="brand"><i>●</i> Punto ERP</div><div class="nav">
<a href="index.php">Panel general</a><a href="?a=clients">Clientes</a><a class="active" href="?a=products">Productos</a><a href="?a=product_categories">Categorías</a><a href="?a=price_lists">Listas de precios</a><a href="?a=projects">Proyectos</a><a href="?a=quotes">Presupuestos</a><a href="?a=receipts">Recibos y pagos</a>
</div></aside><main class="main"><?php if($msg):?><div class="flash"><?=e($msg)?></div><?php endif;?>
<?php }
function bottom():void{?></main></body></html><?php }

$a=$_GET['a']??'products';
try{
 if($a==='save_product'||$a==='update_product'){
  if(!canManage())throw new Exception('Tu perfil no permite modificar productos.'); csrf();
  $id=(int)($_POST['product_id']??0);$sku=trim((string)($_POST['sku']??''));$description=trim((string)($_POST['description']??''));$category=(int)($_POST['category_id']??0)?:null;$unit=trim((string)($_POST['unit']??'Unidad'))?:'Unidad';$cost=(float)($_POST['cost_usd']??0);$track=!empty($_POST['track_stock'])?1:0;$active=!empty($_POST['active'])?1:0;$initial=max(0,(float)($_POST['initial_stock']??0));
  if($sku===''||$description==='')throw new Exception('Completá SKU y descripción.');if($cost<0)throw new Exception('El costo no puede ser negativo.');
  if($a==='save_product'){
   $db->beginTransaction();$db->prepare('INSERT INTO products(sku,description,category_id,unit,cost_usd,track_stock,stock_quantity,active) VALUES(?,?,?,?,?,?,?,?)')->execute([$sku,$description,$category,$unit,$cost,$track,$track?$initial:0,$active]);$id=(int)$db->lastInsertId();
   if($track&&$initial>0)$db->prepare("INSERT INTO stock_movements(product_id,movement_date,movement_type,quantity,reference,notes,user_id) VALUES(?,CURDATE(),'inicial',?,'Alta del producto','Stock inicial',?)")->execute([$id,$initial,$_SESSION['user']['id']??null]);
   $db->commit();$_SESSION['msg']='Producto creado.';
  }else{
   $db->prepare('UPDATE products SET sku=?,description=?,category_id=?,unit=?,cost_usd=?,track_stock=?,active=? WHERE id=?')->execute([$sku,$description,$category,$unit,$cost,$track,$active,$id]);$_SESSION['msg']='Producto actualizado.';
  }
  go('index.php?a=products');
 }
 if($a==='save_price_list'||$a==='update_price_list'){
  if(!canManage())throw new Exception('Tu perfil no permite modificar listas.'); csrf();
  $id=(int)($_POST['price_list_id']??0);$name=trim((string)($_POST['name']??''));$markup=(float)($_POST['markup_percentage']??0);$active=!empty($_POST['active'])?1:0;
  if($name==='')throw new Exception('Ingresá el nombre de la lista.');if($markup<0)throw new Exception('El porcentaje no puede ser negativo.');
  if($a==='save_price_list'){$db->prepare('INSERT INTO price_lists(name,markup_percentage,active) VALUES(?,?,1)')->execute([$name,$markup]);$_SESSION['msg']='Lista creada.';}
  else{$db->prepare('UPDATE price_lists SET name=?,markup_percentage=?,active=? WHERE id=?')->execute([$name,$markup,$active,$id]);$_SESSION['msg']='Lista actualizada. Los precios se recalculan automáticamente.';}
  go('index.php?a=price_lists');
 }
 if($a==='save_product_category'||$a==='update_product_category'){
  if(!canManage())throw new Exception('Tu perfil no permite modificar categorías.'); csrf();$name=trim((string)($_POST['name']??''));if($name==='')throw new Exception('Ingresá el nombre de la categoría.');
  if($a==='save_product_category')$db->prepare('INSERT INTO product_categories(name,active) VALUES(?,1)')->execute([$name]);
  else $db->prepare('UPDATE product_categories SET name=?,active=? WHERE id=?')->execute([$name,!empty($_POST['active'])?1:0,(int)$_POST['category_id']]);
  $_SESSION['msg']='Categoría guardada.';go('index.php?a=product_categories');
 }
 if($a==='save_stock_movement'){
  if(!canManage())throw new Exception('Tu perfil no permite modificar stock.');csrf();$pid=(int)($_POST['product_id']??0);$type=$_POST['movement_type']??'';$qty=(float)($_POST['quantity']??0);if(!in_array($type,['entrada','salida','ajuste'],true)||$qty<0)throw new Exception('Revisá el movimiento.');
  $db->beginTransaction();$s=$db->prepare('SELECT stock_quantity,track_stock FROM products WHERE id=? FOR UPDATE');$s->execute([$pid]);$p=$s->fetch();if(!$p||!$p['track_stock'])throw new Exception('Producto inválido o sin control de stock.');
  $current=(float)$p['stock_quantity'];if($type==='ajuste'){$delta=$qty-$current;$new=$qty;}else{$delta=$type==='salida'?-abs($qty):abs($qty);$new=$current+$delta;}if($new<0)throw new Exception('El movimiento dejaría stock negativo.');
  $db->prepare('INSERT INTO stock_movements(product_id,movement_date,movement_type,quantity,reference,notes,user_id) VALUES(?,?,?,?,?,?,?)')->execute([$pid,$_POST['movement_date'],$type,$delta,trim((string)($_POST['reference']??'')),trim((string)($_POST['notes']??'')),$_SESSION['user']['id']??null]);
  $db->prepare('UPDATE products SET stock_quantity=? WHERE id=?')->execute([$new,$pid]);$db->commit();$_SESSION['msg']='Stock actualizado.';go('index.php?a=products');
 }
}catch(Throwable $ex){if($db->inTransaction())$db->rollBack();$_SESSION['msg']='No se pudo guardar: '.$ex->getMessage();go($_SERVER['HTTP_REFERER']??'index.php?a=products');}

if($a==='products'){
 $q=trim((string)($_GET['q']??''));$cat=(int)($_GET['category_id']??0);$listId=(int)($_GET['price_list_id']??0);
 $lists=$db->query('SELECT * FROM price_lists WHERE active=1 ORDER BY id')->fetchAll();if(!$listId&&$lists)$listId=(int)$lists[0]['id'];$selected=null;foreach($lists as $l)if((int)$l['id']===$listId)$selected=$l;
 $cats=$db->query('SELECT * FROM product_categories WHERE active=1 ORDER BY name')->fetchAll();$where=['1=1'];$params=[];if($q!==''){$where[]='(p.sku LIKE ? OR p.description LIKE ?)';$params[]="%$q%";$params[]="%$q%";}if($cat){$where[]='p.category_id=?';$params[]=$cat;}
 $s=$db->prepare("SELECT p.*,pc.name category_name FROM products p LEFT JOIN product_categories pc ON pc.id=p.category_id WHERE ".implode(' AND ',$where)." ORDER BY p.description");$s->execute($params);$rows=$s->fetchAll();top('Productos');?>
 <div class="actions"><div style="flex:1"><span class="muted">Ventas e inventario</span><h1>Productos</h1><p class="muted"><?=count($rows)?> productos. El precio de venta se calcula siempre desde el costo USD y el porcentaje de la lista.</p></div><?php if(canManage()):?><a class="btn light" href="?a=price_lists">Listas de precios</a><a class="btn" href="?a=new_product">+ Producto</a><?php endif;?></div>
 <div class="card"><form method="get"><input type="hidden" name="a" value="products"><div class="grid"><p class="c4"><label>Buscar</label><input name="q" value="<?=e($q)?>" placeholder="SKU o descripción"></p><p class="c4"><label>Categoría</label><select name="category_id"><option value="0">Todas</option><?php foreach($cats as $c):?><option value="<?=$c['id']?>" <?=$cat==(int)$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select></p><p class="c4"><label>Lista para visualizar</label><select name="price_list_id"><?php foreach($lists as $l):?><option value="<?=$l['id']?>" <?=$listId==(int)$l['id']?'selected':''?>><?=e($l['name'])?> (+<?=e($l['markup_percentage'])?>%)</option><?php endforeach;?></select></p></div><button class="btn light">Aplicar</button></form></div>
 <div class="card scroll"><table><tr><th>SKU</th><th>Descripción</th><th>Categoría</th><th class="right">Costo USD</th><th>Lista</th><th class="right">Precio venta</th><th class="right">Stock</th><th>Acciones</th></tr>
 <?php foreach($rows as $r):$sp=$selected?sale((float)$r['cost_usd'],(float)$selected['markup_percentage']):null;?><tr style="<?=$r['active']?'':'opacity:.5'?>"><td><b><?=e($r['sku'])?></b></td><td><?=e($r['description'])?></td><td><?=e($r['category_name']??'Sin categoría')?></td><td class="right"><?=usd($r['cost_usd'])?></td><td><?=$selected?e($selected['name']).' <span class="muted">+'.e($selected['markup_percentage']).'%</span>':'—'?></td><td class="right"><b><?=$sp===null?'—':usd($sp)?></b></td><td class="right"><?=$r['track_stock']?e(number_format((float)$r['stock_quantity'],2,',','.')):'No controla'?></td><td><div class="actions"><?php if(canManage()):?><a class="btn light" href="?a=edit_product&id=<?=$r['id']?>">Editar</a><?php if($r['track_stock']):?><a class="btn light" href="?a=stock_movement&id=<?=$r['id']?>">Stock</a><?php endif;?><?php endif;?></div></td></tr><?php endforeach;?></table></div>
 <?php bottom();exit;
}
if(in_array($a,['new_product','edit_product'],true)){
 $p=['id'=>0,'sku'=>'','description'=>'','category_id'=>null,'unit'=>'Unidad','cost_usd'=>0,'track_stock'=>1,'stock_quantity'=>0,'active'=>1];if($a==='edit_product'){$s=$db->prepare('SELECT * FROM products WHERE id=?');$s->execute([(int)($_GET['id']??0)]);$p=$s->fetch();if(!$p)exit('Producto inexistente.');}
 $cats=$db->query('SELECT * FROM product_categories WHERE active=1 ORDER BY name')->fetchAll();top($p['id']?'Editar producto':'Nuevo producto');?>
 <div class="card"><div class="actions"><div style="flex:1"><h1><?=$p['id']?'Editar producto':'Nuevo producto'?></h1><p class="muted">El precio de venta no se carga aquí: se calcula desde el costo según cada lista.</p></div><a class="btn light" href="?a=products">Volver</a></div>
 <form method="post" action="?a=<?=$p['id']?'update_product':'save_product'?>"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><?php if($p['id']):?><input type="hidden" name="product_id" value="<?=$p['id']?>"><?php endif;?><div class="grid">
 <p class="c4"><label>SKU / Referencia interna</label><input name="sku" value="<?=e($p['sku'])?>" required></p><p class="c8"><label>Descripción</label><input name="description" value="<?=e($p['description'])?>" required></p>
 <p class="c4"><label>Costo USD</label><input type="number" min="0" step=".01" name="cost_usd" value="<?=e($p['cost_usd'])?>" required></p><p class="c4"><label>Categoría</label><select name="category_id"><option value="">Sin categoría</option><?php foreach($cats as $c):?><option value="<?=$c['id']?>" <?=$p['category_id']==$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select></p><p class="c4"><label>Unidad</label><input name="unit" value="<?=e($p['unit'])?>"></p>
 <?php if(!$p['id']):?><p class="c4"><label>Stock inicial</label><input type="number" min="0" step=".001" name="initial_stock" value="0"></p><?php endif;?>
 <p class="c12"><label style="font-weight:400"><input type="checkbox" name="track_stock" value="1" <?=$p['track_stock']?'checked':''?>> Controlar stock</label><label style="font-weight:400"><input type="checkbox" name="active" value="1" <?=$p['active']?'checked':''?>> Activo</label></p>
 </div><button class="btn">Guardar producto</button></form></div><?php bottom();exit;
}
if($a==='price_lists'){
 $lists=$db->query('SELECT * FROM price_lists ORDER BY id')->fetchAll();top('Listas de precios');?>
 <div class="actions"><div style="flex:1"><h1>Listas de precios</h1><p class="muted">Cada lista aplica un porcentaje directo sobre el costo USD. Ejemplo: costo 100 + 50% = precio 150.</p></div><a class="btn light" href="?a=products">Volver</a></div>
 <div class="grid"><div class="card c8"><?php foreach($lists as $l):?><form method="post" action="?a=update_price_list" class="card" style="box-shadow:none;background:#fafbfc"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="price_list_id" value="<?=$l['id']?>"><div class="grid"><p class="c6"><label>Nombre</label><input name="name" value="<?=e($l['name'])?>" required></p><p class="c3"><label>% sobre costo</label><input type="number" min="0" step=".001" name="markup_percentage" value="<?=e($l['markup_percentage'])?>" required></p><p class="c3" style="align-self:end"><label style="font-weight:400"><input type="checkbox" name="active" value="1" <?=$l['active']?'checked':''?>> Activa</label><button class="btn">Guardar</button></p></div></form><?php endforeach;?></div>
 <div class="card c4"><h2>Nueva lista</h2><form method="post" action="?a=save_price_list"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><p><label>Nombre</label><input name="name" required></p><p><label>% sobre costo</label><input type="number" min="0" step=".001" name="markup_percentage" value="0" required></p><button class="btn">Crear lista</button></form></div></div><?php bottom();exit;
}
if($a==='product_categories'){
 $cats=$db->query('SELECT pc.*,COUNT(p.id) product_count FROM product_categories pc LEFT JOIN products p ON p.category_id=pc.id GROUP BY pc.id ORDER BY pc.name')->fetchAll();top('Categorías');?>
 <div class="actions"><div style="flex:1"><h1>Categorías</h1></div><a class="btn light" href="?a=products">Volver</a></div><div class="grid"><div class="card c8"><table><tr><th>Nombre</th><th>Productos</th><th>Estado</th><th></th></tr><?php foreach($cats as $c):?><tr><form method="post" action="?a=update_product_category"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="category_id" value="<?=$c['id']?>"><td><input name="name" value="<?=e($c['name'])?>"></td><td><?=$c['product_count']?></td><td><label style="font-weight:400"><input type="checkbox" name="active" value="1" <?=$c['active']?'checked':''?>> Activa</label></td><td><button class="btn light">Guardar</button></td></form></tr><?php endforeach;?></table></div><div class="card c4"><h2>Nueva categoría</h2><form method="post" action="?a=save_product_category"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><p><label>Nombre</label><input name="name" required></p><button class="btn">Crear</button></form></div></div><?php bottom();exit;
}
if($a==='stock_movement'){
 $s=$db->prepare('SELECT * FROM products WHERE id=?');$s->execute([(int)($_GET['id']??0)]);$p=$s->fetch();if(!$p)exit('Producto inexistente.');$s=$db->prepare('SELECT sm.*,u.name user_name FROM stock_movements sm LEFT JOIN users u ON u.id=sm.user_id WHERE sm.product_id=? ORDER BY sm.id DESC LIMIT 40');$s->execute([$p['id']]);$moves=$s->fetchAll();top('Stock');?>
 <div class="actions"><div style="flex:1"><h1><?=e($p['description'])?></h1><div class="kpi"><?=e(number_format((float)$p['stock_quantity'],2,',','.'))?> <?=e($p['unit'])?></div><span class="muted"><?=e($p['sku'])?></span></div><a class="btn light" href="?a=products">Volver</a></div><div class="grid"><div class="card c4"><h2>Movimiento</h2><form method="post" action="?a=save_stock_movement"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="product_id" value="<?=$p['id']?>"><p><label>Fecha</label><input type="date" name="movement_date" value="<?=date('Y-m-d')?>"></p><p><label>Tipo</label><select name="movement_type"><option value="entrada">Entrada</option><option value="salida">Salida</option><option value="ajuste">Ajustar stock a</option></select></p><p><label>Cantidad</label><input type="number" min="0" step=".001" name="quantity" required></p><p><label>Referencia</label><input name="reference"></p><p><label>Notas</label><textarea name="notes"></textarea></p><button class="btn">Guardar</button></form></div><div class="card c8 scroll"><table><tr><th>Fecha</th><th>Tipo</th><th class="right">Variación</th><th>Referencia</th><th>Usuario</th></tr><?php foreach($moves as $m):?><tr><td><?=e($m['movement_date'])?></td><td><?=e($m['movement_type'])?></td><td class="right"><?=e(number_format((float)$m['quantity'],3,',','.'))?></td><td><?=e($m['reference'])?></td><td><?=e($m['user_name']??'—')?></td></tr><?php endforeach;?></table></div></div><?php bottom();exit;
}
go('index.php?a=products');
