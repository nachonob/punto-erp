<?php
declare(strict_types=1);

$a=$_GET['a']??'new_quote';
$root=dirname(__DIR__,3);

if(in_array($a,['save_quote','update_quote'],true)){
    session_start();
    $cfg=require $root.'/config.php';
    date_default_timezone_set($cfg['timezone']??'America/Argentina/Buenos_Aires');
    if(empty($_SESSION['user'])){header('Location:index.php');exit;}
    if(($_SESSION['user']['role']??'')!=='admin'){http_response_code(403);exit('No autorizado.');}
    if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Solicitud vencida.');}
    $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    try{
        $manualReady=(bool)$db->query("SHOW COLUMNS FROM quote_items LIKE 'is_manual'")->fetch();
        if(!$manualReady)throw new RuntimeException('Falta ejecutar la migración 2026_09_09_presupuestos_items_manuales.sql.');
        $qid=(int)($_POST['quote_id']??0);$pid=(int)($_POST['project_id']??0);$clientId=(int)($_POST['client_id']??0);$listId=(int)($_POST['price_list_id']??0);
        $s=$db->prepare('SELECT p.*,c.id client_id FROM projects p JOIN clients c ON c.id=p.client_id WHERE p.id=? AND c.id=?');$s->execute([$pid,$clientId]);$project=$s->fetch();if(!$project)throw new Exception('Seleccioná un proyecto válido.');
        $families=array_values(array_intersect(['lifesmart','control4','shelly'],$_POST['quote_families']??[]));if(!$families)$families=['lifesmart'];$template=$_POST['quote_template_family']??'';if(!in_array($template,$families,true))$template=$families[0];
        $s=$db->prepare('SELECT id,name,markup_percentage FROM price_lists WHERE id=? AND active=1');$s->execute([$listId]);$list=$s->fetch();if(!$list)throw new Exception('Seleccioná una lista de precios.');$defaultMarkup=(float)$list['markup_percentage'];
        $hasOverrides=true;try{$db->query('SELECT 1 FROM product_price_overrides LIMIT 1');}catch(Throwable $x){$hasOverrides=false;}
        $prodQ=$db->prepare("SELECT p.id,p.sku,p.description,p.unit,p.cost_usd,COALESCE(pc.name,'Otros') category FROM products p LEFT JOIN product_categories pc ON pc.id=p.category_id WHERE p.id=? AND p.active=1");$ovQ=$hasOverrides?$db->prepare('SELECT markup_percentage FROM product_price_overrides WHERE product_id=? AND price_list_id=?'):null;
        $items=[];$materials=0;$sort=0;
        foreach($_POST['items']??[] as $r){
            $qty=max(1,(int)round((float)($r['quantity']??0)));$posted=$r['unit_price']??null;$unit=(is_numeric($posted)&&(float)$posted>=0)?round((float)$posted,2):0;$section=trim((string)($r['section_title']??'Materiales'))?:'Materiales';$order=(int)($r['block_order']??0);$manual=!empty($r['is_manual']);
            if($manual){$sku=trim((string)($r['sku']??''));$desc=trim((string)($r['description']??''));$u=trim((string)($r['unit']??'unidad'))?:'unidad';if($sku===''&&$desc==='')continue;if($desc==='')$desc='Ítem manual';$sub=round($qty*$unit,2);$materials+=$sub;$items[]=[null,1,'Electricidad','Otro',$section,$order,$sku,$desc,$u,$qty,$unit,null,$sub,$sort++];continue;}
            $productId=(int)($r['product_id']??0);if(!$productId)continue;$prodQ->execute([$productId]);$p=$prodQ->fetch();if(!$p)continue;$markup=$defaultMarkup;if($ovQ){$ovQ->execute([$productId,$listId]);$ov=$ovQ->fetchColumn();if($ov!==false)$markup=(float)$ov;}$defaultUnit=round((float)$p['cost_usd']*(1+$markup/100),2);if(!is_numeric($posted))$unit=$defaultUnit;$sub=round($qty*$unit,2);$materials+=$sub;$items[]=[$productId,0,$p['category'],'Otro',$section,$order,$p['sku'],$p['description'],$p['unit'],$qty,$unit,$listId,$sub,$sort++];
        }
        if(!$items)throw new Exception('Agregá al menos un producto o un ítem manual.');
        $laborBlocks=[];$labor=0;$labTotal=0;foreach($_POST['labor_blocks']??[] as $r){$amount=max(0,(float)($r['amount']??0));$title=trim((string)($r['title']??'Mano de obra'))?:'Mano de obra';$desc=trim((string)($r['description']??''))?:'Configuración, montaje y diseño de escenas';$mode=in_array(($r['tax_mode']??'sin_iva'),['sin_iva','mas_iva','iva_incluido'],true)?$r['tax_mode']:'sin_iva';$vat=(float)($r['vat_rate']??21);$order=(int)($r['block_order']??0);if($amount<=0)continue;$labor+=$amount;$labTotal+=($mode==='mas_iva'?round($amount*(1+$vat/100),2):$amount);$laborBlocks[]=[$title,$desc,$amount,$mode,$vat,$order];}
        $matMode=in_array(($_POST['materials_tax_mode']??'mas_iva'),['sin_iva','mas_iva','iva_incluido'],true)?$_POST['materials_tax_mode']:'mas_iva';$matVat=(float)($_POST['materials_vat_rate']??21);$matTotal=$matMode==='mas_iva'?round($materials*(1+$matVat/100),2):$materials;$total=round($matTotal+$labTotal,2);
        $version=max(1,(int)($_POST['version_no']??1));$date=$_POST['quote_date']??date('Y-m-d');$status=$_POST['status']??'borrador';$category=$_POST['quote_category']??'general';$notes=trim((string)($_POST['notes']??''));$laborDesc=count($laborBlocks)===1?$laborBlocks[0][1]:(count($laborBlocks)>1?'Mano de obra por sectores':'');
        $db->beginTransaction();
        if($a==='update_quote'){if(!$qid)throw new Exception('Presupuesto inválido.');$db->prepare('UPDATE quotes SET project_id=?,version_no=?,quote_category=?,currency="USD",quote_date=?,quote_families=?,quote_template_family=?,price_list_id=?,materials_amount=?,labor_amount=?,labor_description=?,subtotal=?,tax_mode="sin_iva",vat_rate=21,materials_tax_mode=?,materials_vat_rate=?,labor_tax_mode="sin_iva",labor_vat_rate=21,total=?,status=?,notes=? WHERE id=?')->execute([$pid,$version,$category,$date,implode(',',$families),$template,$listId,$materials,$labor,$laborDesc,$materials+$labor,$matMode,$matVat,$total,$status,$notes,$qid]);$db->prepare('DELETE FROM quote_items WHERE quote_id=?')->execute([$qid]);$db->prepare('DELETE FROM quote_labor_items WHERE quote_id=?')->execute([$qid]);}
        else{$db->prepare('INSERT INTO quotes(project_id,version_no,quote_category,currency,quote_date,quote_families,quote_template_family,price_list_id,materials_amount,labor_amount,labor_description,subtotal,tax_mode,vat_rate,materials_tax_mode,materials_vat_rate,labor_tax_mode,labor_vat_rate,total,status,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$pid,$version,$category,'USD',$date,implode(',',$families),$template,$listId,$materials,$labor,$laborDesc,$materials+$labor,'sin_iva',21,$matMode,$matVat,'sin_iva',21,$total,$status,$notes]);$qid=(int)$db->lastInsertId();}
        $ins=$db->prepare('INSERT INTO quote_items(quote_id,product_id,is_manual,category,brand,section_title,block_order,sku,description,unit,quantity,unit_price,price_list_id,subtotal,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');foreach($items as $r)$ins->execute(array_merge([$qid],$r));
        $li=$db->prepare('INSERT INTO quote_labor_items(quote_id,title,description,amount,tax_mode,vat_rate,block_order) VALUES(?,?,?,?,?,?,?)');foreach($laborBlocks as $r)$li->execute(array_merge([$qid],$r));
        $db->commit();$_SESSION['msg']=$a==='update_quote'?'Presupuesto actualizado.':'Presupuesto creado.';header('Location:index.php?a=quote_view&id='.$qid);exit;
    }catch(Throwable $ex){if(isset($db)&&$db->inTransaction())$db->rollBack();$_SESSION['msg']='No se pudo guardar: '.$ex->getMessage();header('Location:index.php?a='.($a==='update_quote'?'edit_quote&id='.$qid:'new_quote'));exit;}
}

$manualRows=[];
if($a==='edit_quote'){
    try{$cfg=require $root.'/config.php';$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$qid=(int)($_GET['id']??0);$s=$db->prepare('SELECT block_order,section_title,sku,description,unit,quantity,unit_price,sort_order FROM quote_items WHERE quote_id=? AND (is_manual=1 OR product_id IS NULL) ORDER BY block_order,sort_order,id');$s->execute([$qid]);$manualRows=$s->fetchAll();}catch(Throwable $e){$manualRows=[];}
}

ob_start();require __DIR__.'/module_v10.php';$html=ob_get_clean();
$manualJson=json_encode($manualRows,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$inject=<<<HTML
<script>
(function(){
  const rubro=document.querySelector('select[name="quote_category"]');
  if(rubro && ![...rubro.options].some(o=>o.value==='electricidad')){
    const o=document.createElement('option');o.value='electricidad';o.textContent='Electricidad';rubro.insertBefore(o,rubro.querySelector('option[value="general"]')||null);
    if('$a'==='edit_quote' && <?=json_encode((string)($_GET['id']??''))?>){ /* selección la conserva PHP si ya existe */ }
  }
  function escm(s){return String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}
  window.addManualItem=function(block,data={}){
    const i=itemIndex++,tr=document.createElement('tr');tr.dataset.manual='1';
    tr.innerHTML=`<td><input type="hidden" name="items[\${i}][is_manual]" value="1"><input class="section-title-hidden" type="hidden" name="items[\${i}][section_title]"><input class="block-order-hidden" type="hidden" name="items[\${i}][block_order]"><input class="sku-input" name="items[\${i}][sku]" value="\${escm(data.sku||'')}" placeholder="Ej.: 01"></td><td><input class="desc-input" name="items[\${i}][description]" value="\${escm(data.description||'')}" placeholder="Ej.: Cambio de plafones de iluminación"></td><td><input name="items[\${i}][unit]" value="\${escm(data.unit||'unidad')}" placeholder="unidad" style="min-width:90px"></td><td><input class="qty" type="number" min="1" step="1" name="items[\${i}][quantity]" value="\${Number(data.quantity||1)}" oninput="calcTotals()"></td><td class="right"><input class="unit-price" type="number" min="0" step=".01" name="items[\${i}][unit_price]" value="\${Number(data.unit_price||0).toFixed(2)}" oninput="calcTotals()"></td><td class="right sub">—</td><td><button class="btn danger" type="button" onclick="this.closest('tr').remove();calcTotals()">×</button></td>`;
    block.querySelector('.item-body').appendChild(tr);renumberBlocks();calcTotals();
  };
  document.querySelectorAll('.material-block').forEach(block=>{
    const btn=block.querySelector('.block-body > .btn.light');if(btn&&!block.querySelector('.manual-add')){const m=document.createElement('button');m.type='button';m.className='btn light manual-add';m.style.marginLeft='8px';m.textContent='+ Ítem manual';m.onclick=()=>addManualItem(block);btn.insertAdjacentElement('afterend',m);}
  });
  const oldAdd=window.addMaterialBlock;if(typeof oldAdd==='function')window.addMaterialBlock=function(){oldAdd();const b=document.querySelector('#blocks .material-block:last-of-type');const btn=b?.querySelector('.block-body > .btn.light');if(btn){const m=document.createElement('button');m.type='button';m.className='btn light manual-add';m.style.marginLeft='8px';m.textContent='+ Ítem manual';m.onclick=()=>addManualItem(b);btn.insertAdjacentElement('afterend',m);}};
  document.querySelectorAll('.material-block tr').forEach(tr=>{const pid=tr.querySelector('.product-id');if(pid && !pid.value && !tr.querySelector('input[name*="[is_manual]"]'))tr.remove();});
  const rows=$manualJson;
  rows.forEach(r=>{let block=[...document.querySelectorAll('.material-block')].find(b=>(b.querySelector('.block-title')?.value||'')===(r.section_title||''));if(!block)block=document.querySelector('.material-block');if(block)addManualItem(block,r);});
  const oldCalc=window.calcTotals;if(typeof oldCalc==='function')window.calcTotals=function(){let mat=0;document.querySelectorAll('.material-block tr').forEach(tr=>{const manual=tr.dataset.manual==='1',id=tr.dataset.product,qty=Number(tr.querySelector('.qty')?.value||0),price=Number(tr.querySelector('.unit-price')?.value||0),valid=manual||!!id,sub=valid?qty*price:0;const c=tr.querySelector('.sub');if(c)c.textContent=valid?money(sub):'—';mat+=sub});let labor=0,laborTaxed=0;document.querySelectorAll('.labor-block').forEach(b=>{const a=Number(b.querySelector('.labor-amount').value||0),m=b.querySelector('.labor-tax').value,v=Number(b.querySelector('.labor-vat').value||21);labor+=a;laborTaxed+=m==='mas_iva'?a*(1+v/100):a});const mm=document.getElementById('materialsTax').value,mv=Number(document.getElementById('materialsVat').value||21),matTaxed=mm==='mas_iva'?mat*(1+mv/100):mat;document.getElementById('materialsTotal').textContent=money(matTaxed);document.getElementById('laborTotal').textContent=money(laborTaxed);document.getElementById('grandTotal').textContent=money(matTaxed+laborTaxed)};
  calcTotals();
})();
</script>
HTML;
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
