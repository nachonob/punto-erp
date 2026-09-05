<?php
declare(strict_types=1);

$a=$_GET['a']??'new_quote';
$root=dirname(__DIR__,3);

if(in_array($a,['save_quote','update_quote'],true)){
 session_start();
 $cfg=require $root.'/config.php';
 date_default_timezone_set($cfg['timezone']??'America/Argentina/Buenos_Aires');
 $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 if(empty($_SESSION['user'])){header('Location:index.php');exit;}
 if(($_SESSION['user']['role']??'')!=='admin'){http_response_code(403);exit('No autorizado.');}
 if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Solicitud vencida.');}
 $go=static function(string $u):never{header('Location:'.$u);exit;};
 $brand=static function(string $cat):string{$x=mb_strtolower($cat);if(str_contains($x,'control4')||str_contains($x,'c4'))return 'Control4';if(str_contains($x,'shelly'))return 'Shelly';if(str_contains($x,'lifesmart')||str_contains($x,'domot'))return 'LifeSmart';return 'Otro';};
 $replaceCharge=static function(PDO $db,int $projectId,int $newChargeId,array $types):void{$m=implode(',',array_fill(0,count($types),'?'));$s=$db->prepare("SELECT id FROM charges WHERE project_id=? AND active=1 AND id<>? AND type IN ($m)");$s->execute(array_merge([$projectId,$newChargeId],$types));foreach($s as $old){$als=$db->prepare('SELECT payment_id,amount FROM allocations WHERE charge_id=?');$als->execute([$old['id']]);foreach($als as $al)$db->prepare('INSERT INTO allocations(payment_id,charge_id,amount) VALUES(?,?,?) ON DUPLICATE KEY UPDATE amount=amount+VALUES(amount)')->execute([$al['payment_id'],$newChargeId,$al['amount']]);$db->prepare('DELETE FROM allocations WHERE charge_id=?')->execute([$old['id']]);$db->prepare('UPDATE charges SET active=0 WHERE id=?')->execute([$old['id']]);}};
 try{
  $qid=(int)($_POST['quote_id']??0);$pid=(int)($_POST['project_id']??0);$clientId=(int)($_POST['client_id']??0);$listId=(int)($_POST['price_list_id']??0);
  $s=$db->prepare('SELECT p.*,c.id client_id FROM projects p JOIN clients c ON c.id=p.client_id WHERE p.id=? AND c.id=?');$s->execute([$pid,$clientId]);$project=$s->fetch();if(!$project)throw new Exception('Seleccioná un proyecto válido.');
  $families=array_values(array_intersect(['lifesmart','control4','shelly'],$_POST['quote_families']??[]));if(!$families)throw new Exception('Seleccioná al menos una familia.');$template=$_POST['quote_template_family']??'';if(!in_array($template,$families,true))$template=$families[0];
  $s=$db->prepare('SELECT id,name,markup_percentage FROM price_lists WHERE id=? AND active=1');$s->execute([$listId]);$list=$s->fetch();if(!$list)throw new Exception('Seleccioná una lista de precios.');$markup=(float)$list['markup_percentage'];
  $prodQ=$db->prepare("SELECT p.id,p.sku,p.description,p.unit,p.cost_usd,COALESCE(pc.name,'Otros') category FROM products p LEFT JOIN product_categories pc ON pc.id=p.category_id WHERE p.id=? AND p.active=1");
  $items=[];$materials=0;$sort=0;
  foreach($_POST['items']??[] as $r){
   $productId=(int)($r['product_id']??0);$qty=(float)($r['quantity']??0);if(!$productId||$qty<=0)continue;
   $prodQ->execute([$productId]);$p=$prodQ->fetch();if(!$p)continue;
   $defaultUnit=round((float)$p['cost_usd']*(1+$markup/100),2);
   $posted=$r['unit_price']??null;
   $unit=(is_numeric($posted)&&(float)$posted>=0)?round((float)$posted,2):$defaultUnit;
   $sub=round($qty*$unit,2);$materials+=$sub;
   $items[]=[$productId,$p['category'],$brand($p['category']),$p['sku'],$p['description'],$p['unit'],$qty,$unit,$listId,$sub,$sort++];
  }
  if(!$items)throw new Exception('Agregá al menos un producto.');
  $labor=max(0,(float)($_POST['labor_amount']??0));$laborDescription=trim((string)($_POST['labor_description']??''));$matMode=$_POST['materials_tax_mode']??'sin_iva';$matVat=(float)($_POST['materials_vat_rate']??21);$labMode=$_POST['labor_tax_mode']??'sin_iva';$labVat=(float)($_POST['labor_vat_rate']??21);$factor=static fn(string $m,float $v):float=>$m==='mas_iva'?1+$v/100:1;$matTotal=round($materials*$factor($matMode,$matVat),2);$labTotal=round($labor*$factor($labMode,$labVat),2);$total=$matTotal+$labTotal;$version=max(1,(int)($_POST['version_no']??1));$date=$_POST['quote_date']??date('Y-m-d');$status=$_POST['status']??'borrador';$category=$_POST['quote_category']??'general';$notes=trim((string)($_POST['notes']??''));
  $db->beginTransaction();
  if($a==='update_quote'){
   if(!$qid)throw new Exception('Presupuesto inválido.');
   $db->prepare('UPDATE quotes SET project_id=?,version_no=?,quote_category=?,currency="USD",quote_date=?,quote_families=?,quote_template_family=?,price_list_id=?,materials_amount=?,labor_amount=?,labor_description=?,subtotal=?,tax_mode="sin_iva",vat_rate=21,materials_tax_mode=?,materials_vat_rate=?,labor_tax_mode=?,labor_vat_rate=?,total=?,status=?,notes=? WHERE id=?')->execute([$pid,$version,$category,$date,implode(',',$families),$template,$listId,$materials,$labor,$laborDescription,$materials+$labor,$matMode,$matVat,$labMode,$labVat,$total,$status,$notes,$qid]);
   $db->prepare('DELETE FROM quote_items WHERE quote_id=?')->execute([$qid]);
  }else{
   $db->prepare('INSERT INTO quotes(project_id,version_no,quote_category,currency,quote_date,quote_families,quote_template_family,price_list_id,materials_amount,labor_amount,labor_description,subtotal,tax_mode,vat_rate,materials_tax_mode,materials_vat_rate,labor_tax_mode,labor_vat_rate,total,status,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$pid,$version,$category,'USD',$date,implode(',',$families),$template,$listId,$materials,$labor,$laborDescription,$materials+$labor,'sin_iva',21,$matMode,$matVat,$labMode,$labVat,$total,$status,$notes]);
   $qid=(int)$db->lastInsertId();
  }
  $ins=$db->prepare('INSERT INTO quote_items(quote_id,product_id,category,brand,sku,description,unit,quantity,unit_price,price_list_id,subtotal,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');foreach($items as $r)$ins->execute(array_merge([$qid],$r));
  if($status==='aprobado_inicial'){$pct=(float)$project['engineering_pct'];$amt=round($total*$pct/100,2);$db->prepare("INSERT INTO charges(project_id,quote_id,charge_date,type,currency,description,amount) VALUES(?,?,?,'ingenieria','USD',?,?)")->execute([$pid,$qid,$date,'Adelanto de ingeniería '.$pct.'% · v'.$version,$amt]);$eng=(int)$db->lastInsertId();$replaceCharge($db,$pid,$eng,['ingenieria']);$db->prepare("UPDATE projects SET status='aprobado' WHERE id=?")->execute([$pid]);}
  if($status==='final'){$db->prepare("INSERT INTO charges(project_id,quote_id,charge_date,type,currency,description,amount) VALUES(?,?,?,'materiales','USD',?,?)")->execute([$pid,$qid,$date,'Materiales · presupuesto final v'.$version,$matTotal]);$mat=(int)$db->lastInsertId();$replaceCharge($db,$pid,$mat,['materiales']);if($labTotal>0){$desc=$laborDescription!==''?$laborDescription:'Mano de obra';$db->prepare("INSERT INTO charges(project_id,quote_id,charge_date,type,currency,description,amount) VALUES(?,?,?,'mano_obra','USD',?,?)")->execute([$pid,$qid,$date,$desc.' · presupuesto final v'.$version,$labTotal]);$lab=(int)$db->lastInsertId();$replaceCharge($db,$pid,$lab,['ingenieria','mano_obra']);}$db->prepare("UPDATE projects SET status='en_obra' WHERE id=?")->execute([$pid]);}
  $db->commit();$_SESSION['msg']=$a==='update_quote'?'Presupuesto actualizado.':'Presupuesto creado.';$go('index.php?a=quote_view&id='.$qid);
 }catch(Throwable $ex){if($db->inTransaction())$db->rollBack();$_SESSION['msg']='No se pudo guardar: '.$ex->getMessage();$go('index.php?a='.($a==='update_quote'?'edit_quote&id='.$qid:'new_quote'));}
}

$existingPrices=[];
if($a==='edit_quote'){
 $cfg=require $root.'/config.php';
 $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $id=(int)($_GET['id']??0);if($id){$s=$db->prepare('SELECT unit_price FROM quote_items WHERE quote_id=? ORDER BY sort_order,id');$s->execute([$id]);$existingPrices=array_map(static fn($r)=>(float)$r['unit_price'],$s->fetchAll());}
}

ob_start();
require __DIR__.'/module_v4.php';
$html=ob_get_clean();
$pricesJson=json_encode($existingPrices,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$inject=<<<HTML
<style>
#items .unit-price{width:120px;text-align:right;padding:10px 9px;border:1px solid #cbd1d7;border-radius:8px;font:inherit;background:#fff}
#items .unit-price:focus{outline:2px solid #ff670233;border-color:#ff6702}
</style>
<script>
(function(){
 const existingPrices=$pricesJson;
 function priceName(tr){const p=tr.querySelector('.product-id');return p&&p.name?p.name.replace('[product_id]','[unit_price]'):''}
 function ensurePriceInput(tr,index){
  const td=tr.querySelector('.price');if(!td||td.querySelector('.unit-price'))return;
  const input=document.createElement('input');input.type='number';input.min='0';input.step='0.01';input.className='unit-price';input.name=priceName(tr);
  const id=tr.dataset.product;const fallback=id&&typeof productPrice==='function'?productPrice(id):0;
  input.value=(existingPrices[index]!==undefined?Number(existingPrices[index]):Number(fallback||0)).toFixed(2);
  input.addEventListener('input',function(){if(typeof calc==='function')calc()});
  td.textContent='';td.appendChild(input);
 }
 function prepareAll(){document.querySelectorAll('#items tr').forEach((tr,i)=>ensurePriceInput(tr,i))}
 const originalSelect=window.selectProduct;
 if(typeof originalSelect==='function')window.selectProduct=function(tr,id){originalSelect(tr,id);ensurePriceInput(tr,[...tr.parentNode.children].indexOf(tr));const inp=tr.querySelector('.unit-price');if(inp&&typeof productPrice==='function')inp.value=Number(productPrice(id)||0).toFixed(2);window.calc()};
 window.calc=function(){let total=0;document.querySelectorAll('#items tr').forEach(tr=>{const id=tr.dataset.product,qty=Number(tr.querySelector('.qty')?.value||0),inp=tr.querySelector('.unit-price');const price=inp?Number(inp.value||0):(id&&typeof productPrice==='function'?productPrice(id):0),sub=price*qty;const subCell=tr.querySelector('.sub');if(subCell)subCell.textContent=id?money(sub):'—';if(id)total+=sub});const m=document.getElementById('materials');if(m)m.textContent=money(total)};
 const items=document.getElementById('items');if(items){prepareAll();new MutationObserver(()=>prepareAll()).observe(items,{childList:true});}
 const list=document.getElementById('priceList');if(list)list.addEventListener('change',function(){document.querySelectorAll('#items tr').forEach(tr=>{const id=tr.dataset.product,inp=tr.querySelector('.unit-price');if(id&&inp&&typeof productPrice==='function')inp.value=Number(productPrice(id)||0).toFixed(2)});window.calc()});
 prepareAll();window.calc();
})();
</script>
HTML;
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
