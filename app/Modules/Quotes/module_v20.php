<?php
declare(strict_types=1);
$a=$_GET['a']??'new_quote';$root=dirname(__DIR__,3);$qid=(int)($_POST['quote_id']??$_GET['id']??0);
// Antes de que el módulo base procese el POST convertimos los conceptos en ítems manuales de valor cero.
// La marca __CONCEPT__ permite persistirlos sin alterar cálculos ni productos existentes.
if(in_array($a,['save_quote','update_quote'],true)){
 foreach($_POST['items']??[] as $k=>$r){
  if(empty($r['is_concept']))continue;
  $_POST['items'][$k]['is_manual']='1';$_POST['items'][$k]['sku']='__CONCEPT__';$_POST['items'][$k]['unit']='concepto';
  $_POST['items'][$k]['quantity']='1';$_POST['items'][$k]['unit_price']='0';
 }
}
ob_start();require __DIR__.'/module_v19.php';$html=ob_get_clean();
// Los conceptos guardados se reconocen por SKU reservado. El editor base los trae como manuales;
// en pantalla los transformamos a la fila compacta Nº + Descripción.
$inject=<<<'HTML'
<style>
tr[data-concept="1"] td{vertical-align:middle}tr[data-concept="1"] .concept-no{font-weight:800;text-align:center;display:block}tr[data-concept="1"] td:nth-child(n+3):nth-child(-n+6){display:none}
.concept-total-box{margin-top:10px;display:grid;grid-template-columns:1fr 220px;gap:10px;align-items:end}.concept-total-box label{font-weight:700}.concept-total-box input{width:100%}
</style>
<script>
(function(){
 function renumber(block){[...block.querySelectorAll('tr[data-concept="1"]')].forEach((r,n)=>{const x=r.querySelector('.concept-no');if(x)x.textContent=String(n+1);});}
 window.addConceptItem=function(block,data={}){
   const i=itemIndex++,tr=document.createElement('tr');tr.dataset.manual='1';tr.dataset.concept='1';
   tr.innerHTML=`<td><input type="hidden" name="items[${i}][is_manual]" value="1"><input type="hidden" name="items[${i}][is_concept]" value="1"><input type="hidden" name="items[${i}][sku]" value="__CONCEPT__"><input type="hidden" name="items[${i}][unit]" value="concepto"><input type="hidden" name="items[${i}][quantity]" value="1"><input type="hidden" name="items[${i}][unit_price]" value="0"><input class="section-title-hidden" type="hidden" name="items[${i}][section_title]"><input class="block-order-hidden" type="hidden" name="items[${i}][block_order]"><span class="concept-no"></span></td><td colspan="5"><input class="desc-input" name="items[${i}][description]" value="${String(data.description||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}" placeholder="Descripción del concepto" style="width:100%"></td><td><button type="button" class="danger" onclick="const b=this.closest('.material-block');this.closest('tr').remove();renumber(b)">×</button></td>`;
   const body=block.querySelector('tbody');body.appendChild(tr);if(typeof syncBlock==='function')syncBlock(block);renumber(block);
 };
 function enhanceBlock(block){
   if(block.dataset.conceptEnhanced)return;block.dataset.conceptEnhanced='1';
   const addManual=[...block.querySelectorAll('button')].find(b=>/ítem manual/i.test(b.textContent));
   if(addManual){const b=document.createElement('button');b.type='button';b.className=addManual.className;b.textContent='+ Ítem concepto';b.addEventListener('click',()=>addConceptItem(block));addManual.insertAdjacentElement('afterend',b);}
   [...block.querySelectorAll('tbody tr')].forEach(r=>{const sku=r.querySelector('.sku-input,input[name$="[sku]"]');if(sku&&sku.value==='__CONCEPT__'){const desc=r.querySelector('.desc-input,input[name$="[description]"]');const d=desc?desc.value:'';r.remove();addConceptItem(block,{description:d});}});
   renumber(block);
 }
 function run(){document.querySelectorAll('.material-block').forEach(enhanceBlock);}
 run();new MutationObserver(run).observe(document.body,{childList:true,subtree:true});
})();
</script>
HTML;
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
