<?php
declare(strict_types=1);

$a=$_GET['a']??'new_quote';
$root=dirname(__DIR__,3);
$quoteId=(int)($_POST['quote_id']??$_GET['id']??0);

// Persistencia de descuentos después de que el módulo base guarde el presupuesto.
if(in_array($a,['save_quote','update_quote'],true)){
    $posted=$_POST['discounts']??[];
    if(!is_array($posted))$posted=[];
    $projectId=(int)($_POST['project_id']??0);
    register_shutdown_function(function()use($root,$a,$quoteId,$projectId,$posted):void{
        try{
            $cfg=require $root.'/config.php';
            $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
            $id=$quoteId;
            if($a==='save_quote'&&$projectId>0){$s=$db->prepare('SELECT id FROM quotes WHERE project_id=? ORDER BY id DESC LIMIT 1');$s->execute([$projectId]);$id=(int)$s->fetchColumn();}
            if($id<1)return;
            $db->prepare('DELETE FROM quote_discounts WHERE quote_id=?')->execute([$id]);
            $ins=$db->prepare('INSERT INTO quote_discounts(quote_id,scope,item_type,item_id,discount_type,value,description,sort_order) VALUES(?,?,?,?,?,?,?,?)');
            foreach($posted as $i=>$d){
                if(!is_array($d))continue;
                $scope=in_array(($d['scope']??''),['general','materials','labor','item'],true)?$d['scope']:'general';
                $type=($d['discount_type']??'percentage')==='amount'?'amount':'percentage';
                $value=max(0,(float)($d['value']??0));if($value<=0)continue;
                if($type==='percentage')$value=min(100,$value);
                $itemType=in_array(($d['item_type']??''),['material','labor'],true)?$d['item_type']:null;
                $itemId=(int)($d['item_id']??0);if($itemId<1)$itemId=null;
                $ins->execute([$id,$scope,$itemType,$itemId,$type,$value,trim((string)($d['description']??'')),(int)$i]);
            }
        }catch(Throwable $e){}
    });
}

$existing=[];
if($a==='edit_quote'&&$quoteId>0){
    try{$cfg=require $root.'/config.php';$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$s=$db->prepare('SELECT * FROM quote_discounts WHERE quote_id=? ORDER BY sort_order,id');$s->execute([$quoteId]);$existing=$s->fetchAll();}catch(Throwable $e){}
}

ob_start();require __DIR__.'/module_v15.php';$html=ob_get_clean();
$existingJ=json_encode($existing,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$inject=<<<HTML
<style>
.discount-card{border:1px solid #dfe3e7;border-radius:12px;background:#fff;margin:0 0 18px;padding:18px}.discount-head{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap}.discount-row{display:grid;grid-template-columns:2fr 1.2fr 1fr 1fr auto;gap:10px;align-items:end;padding:12px 0;border-top:1px solid #e8ebee}.discount-row:first-child{border-top:0}.discount-row label{font-size:12px;color:#68727d}.discount-summary{margin-top:10px;padding-top:12px;border-top:1px solid #ddd;font-weight:700}@media(max-width:900px){.discount-row{grid-template-columns:1fr 1fr}.discount-row .discount-remove{grid-column:span 2}}
</style>
<script>
(function(){
 const form=document.getElementById('quoteForm');if(!form)return;
 const totalsCard=[...form.querySelectorAll('.card')].find(x=>x.querySelector('#grandTotal'));
 if(!totalsCard)return;
 const card=document.createElement('div');card.className='discount-card';card.innerHTML='<div class="discount-head"><div><h2 style="margin:0">Descuentos</h2><div class="muted">Aplicá descuentos generales, solo a materiales, solo a mano de obra o por ítem.</div></div><button type="button" class="btn dark" id="addDiscount">+ Descuento</button></div><div id="discountRows"></div><div class="discount-summary">Descuentos aplicados: <span id="discountTotal">US$ 0,00</span></div>';
 totalsCard.parentNode.insertBefore(card,totalsCard);
 const rows=card.querySelector('#discountRows');let seq=0;const existing=$existingJ;
 function add(d={}){const i=seq++,r=document.createElement('div');r.className='discount-row';r.innerHTML=`<p><label>Aplicar a</label><select name="discounts[\${i}][scope]" class="discount-scope"><option value="general">Todo el presupuesto</option><option value="materials">Todos los materiales</option><option value="labor">Toda la mano de obra</option><option value="item">Producto / ítem específico</option></select></p><p><label>Tipo</label><select name="discounts[\${i}][discount_type]" class="discount-type"><option value="percentage">Porcentaje %</option><option value="amount">Monto fijo USD</option></select></p><p><label>Valor</label><input name="discounts[\${i}][value]" class="discount-value" type="number" min="0" step=".01" value="\${Number(d.value||0)}"></p><p class="discount-item-wrap" style="display:none"><label>Ítem</label><select name="discounts[\${i}][item_id]" class="discount-item"><option value="">Elegir ítem</option></select><input type="hidden" name="discounts[\${i}][item_type]" class="discount-item-type"></p><button type="button" class="btn danger discount-remove">Eliminar</button><input type="hidden" name="discounts[\${i}][description]" value="">`;
 rows.appendChild(r);r.querySelector('.discount-scope').value=d.scope||'general';r.querySelector('.discount-type').value=d.discount_type||'percentage';
 function fillItems(){const s=r.querySelector('.discount-item'),cur=String(d.item_id||s.value||'');s.innerHTML='<option value="">Elegir ítem</option>';document.querySelectorAll('.material-block tr').forEach((tr,n)=>{const pid=tr.querySelector('.product-id')?.value;if(!pid)return;const sku=tr.querySelector('.sku-input')?.value||'Producto';const o=document.createElement('option');o.value=pid;o.dataset.type='material';o.textContent='Material · '+sku;s.appendChild(o)});document.querySelectorAll('.labor-block').forEach((b,n)=>{const o=document.createElement('option');o.value='labor-'+n;o.dataset.type='labor';o.textContent='Mano de obra · '+(b.querySelector('.labor-title')?.value||('Ítem '+(n+1)));s.appendChild(o)});if(cur)s.value=cur;updateType()}
 function updateType(){const s=r.querySelector('.discount-item'),o=s.selectedOptions[0];r.querySelector('.discount-item-type').value=o?.dataset.type||''}
 function scope(){const isItem=r.querySelector('.discount-scope').value==='item';r.querySelector('.discount-item-wrap').style.display=isItem?'block':'none';if(isItem)fillItems();calcDiscounts()}
 r.querySelector('.discount-scope').onchange=scope;r.querySelector('.discount-type').onchange=calcDiscounts;r.querySelector('.discount-value').oninput=calcDiscounts;r.querySelector('.discount-item').onchange=()=>{updateType();calcDiscounts()};r.querySelector('.discount-remove').onclick=()=>{r.remove();calcDiscounts()};scope();if(d.item_id)fillItems();}
 function bases(){let mat=0;document.querySelectorAll('.material-block tr').forEach(tr=>{const id=tr.dataset.product||tr.querySelector('.product-id')?.value,qty=Number(tr.querySelector('.qty')?.value||0),price=Number(tr.querySelector('.unit-price')?.value||0);if(id)mat+=qty*price});let labor=0;document.querySelectorAll('.labor-block').forEach(b=>labor+=Number(b.querySelector('.labor-amount')?.value||0));return{mat,labor,total:mat+labor}}
 window.calcDiscounts=function(){const b=bases();let total=0;rows.querySelectorAll('.discount-row').forEach(r=>{const scope=r.querySelector('.discount-scope').value,type=r.querySelector('.discount-type').value,val=Math.max(0,Number(r.querySelector('.discount-value').value||0));let base=scope==='materials'?b.mat:scope==='labor'?b.labor:b.total;if(scope==='item'){const s=r.querySelector('.discount-item'),o=s.selectedOptions[0];if(o?.dataset.type==='material'){const tr=[...document.querySelectorAll('.material-block tr')].find(x=>String(x.querySelector('.product-id')?.value)===s.value);base=tr?Number(tr.querySelector('.qty')?.value||0)*Number(tr.querySelector('.unit-price')?.value||0):0}else if(o?.dataset.type==='labor'){const n=Number(s.value.replace('labor-',''));const lb=document.querySelectorAll('.labor-block')[n];base=lb?Number(lb.querySelector('.labor-amount')?.value||0):0}else base=0}total+=type==='percentage'?base*Math.min(100,val)/100:Math.min(base,val)});card.querySelector('#discountTotal').textContent=money(total);const gt=document.getElementById('grandTotal');if(gt){const raw=b.total;gt.textContent=money(Math.max(0,raw-total))}}
 document.getElementById('addDiscount').onclick=()=>add();existing.forEach(add);const observer=new MutationObserver(()=>calcDiscounts());observer.observe(document.getElementById('blocks'),{childList:true,subtree:true});form.addEventListener('input',()=>setTimeout(calcDiscounts,0));setTimeout(calcDiscounts,50);
})();
</script>
HTML;
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;echo $html;
