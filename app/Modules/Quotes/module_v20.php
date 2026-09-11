<?php
declare(strict_types=1);
$a=$_GET['a']??'new_quote';$root=dirname(__DIR__,3);$qid=(int)($_POST['quote_id']??$_GET['id']??0);
$conceptRows=[];
// Cargamos los conceptos directamente de la base. No dependemos de cómo module_v12 reconstruye
// los ítems manuales, porque varios conceptos consecutivos podían terminar colapsados en edición.
if($a==='edit_quote'&&$qid>0){
 try{$cfg=require $root.'/config.php';$db20=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$s=$db20->prepare("SELECT section_title,block_order,description,sort_order FROM quote_items WHERE quote_id=? AND sku='__CONCEPT__' ORDER BY block_order,sort_order,id");$s->execute([$qid]);$conceptRows=$s->fetchAll();}catch(Throwable $e){}
}
if(in_array($a,['save_quote','update_quote'],true)){
 foreach($_POST['items']??[] as $k=>$r){
  if(empty($r['is_concept']))continue;
  $_POST['items'][$k]['is_manual']='1';$_POST['items'][$k]['sku']='__CONCEPT__';$_POST['items'][$k]['unit']='concepto';
  $_POST['items'][$k]['quantity']='1';$_POST['items'][$k]['unit_price']='0';
 }
}
ob_start();require __DIR__.'/module_v19.php';$html=ob_get_clean();
$conceptJson=json_encode($conceptRows,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$inject=<<<HTML
<style>
tr[data-concept="1"] td{vertical-align:middle}tr[data-concept="1"] .concept-no{font-weight:800;text-align:center;display:block}tr[data-concept="1"] td:nth-child(n+3):nth-child(-n+6){display:none}
</style>
<script>
(function(){
 const savedConcepts=$conceptJson;
 function esc(s){return String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
 function renumber(block){[...block.querySelectorAll('tr[data-concept="1"]')].forEach((r,n)=>{const x=r.querySelector('.concept-no');if(x)x.textContent=String(n+1);});}
 window.addConceptItem=function(block,data={}){
   const i=itemIndex++,tr=document.createElement('tr');tr.dataset.manual='1';tr.dataset.concept='1';
   tr.innerHTML=`<td><input type="hidden" name="items[\${i}][is_manual]" value="1"><input type="hidden" name="items[\${i}][is_concept]" value="1"><input type="hidden" name="items[\${i}][sku]" value="__CONCEPT__"><input type="hidden" name="items[\${i}][unit]" value="concepto"><input type="hidden" name="items[\${i}][quantity]" value="1"><input type="hidden" name="items[\${i}][unit_price]" value="0"><input class="section-title-hidden" type="hidden" name="items[\${i}][section_title]" value="\${esc(data.section_title||block.querySelector('.block-title')?.value||'Materiales')}"><input class="block-order-hidden" type="hidden" name="items[\${i}][block_order]" value="\${Number(data.block_order||0)}"><span class="concept-no"></span></td><td colspan="5"><input class="desc-input" name="items[\${i}][description]" value="\${esc(data.description||'')}" placeholder="Descripción del concepto" style="width:100%"></td><td><button type="button" class="danger">×</button></td>`;
   tr.querySelector('button').onclick=()=>{tr.remove();renumber(block);};
   (block.querySelector('.item-body')||block.querySelector('tbody')).appendChild(tr);renumber(block);
 };
 function targetBlock(data){const blocks=[...document.querySelectorAll('.material-block')];return blocks.find(b=>(b.querySelector('.block-title')?.value||'')===(data.section_title||''))||blocks.find(b=>Number(b.dataset.order||b.querySelector('.block-order-hidden')?.value||0)===Number(data.block_order||0))||blocks[0];}
 function setup(){
   const blocks=[...document.querySelectorAll('.material-block')];if(!blocks.length)return false;
   blocks.forEach(block=>{if(!block.querySelector('.concept-add')){const manual=[...block.querySelectorAll('button')].find(b=>/ítem manual/i.test(b.textContent));if(manual){const b=document.createElement('button');b.type='button';b.className=manual.className+' concept-add';b.textContent='+ Ítem concepto';b.onclick=()=>addConceptItem(block);manual.insertAdjacentElement('afterend',b);}}});
   // Eliminamos todas las representaciones manuales que module_v12 haya creado para __CONCEPT__
   // y reconstruimos exactamente las filas persistidas, una por una y en su orden original.
   document.querySelectorAll('.material-block tbody tr').forEach(r=>{const sku=r.querySelector('.sku-input,input[name$="[sku]"]');if(sku&&sku.value==='__CONCEPT__')r.remove();});
   savedConcepts.forEach(c=>{const b=targetBlock(c);if(b)addConceptItem(b,c);});
   blocks.forEach(renumber);return true;
 }
 let done=false;function run(){if(done)return;if(setup())done=true;}
 run();if(!done){const mo=new MutationObserver(()=>{run();if(done)mo.disconnect();});mo.observe(document.body,{childList:true,subtree:true});}
})();
</script>
HTML;
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
