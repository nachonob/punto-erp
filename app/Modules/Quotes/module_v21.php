<?php
declare(strict_types=1);
$a=$_GET['a']??'new_quote';$root=dirname(__DIR__,3);$qid=(int)($_POST['quote_id']??$_GET['id']??0);
$conceptTotal=0.0;$conceptLabel='Materiales';$savedStatus='borrador';
if($a==='edit_quote'&&$qid>0){
 try{
  $cfg=require $root.'/config.php';
  $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  $s=$db->prepare("SELECT description,unit_price FROM quote_items WHERE quote_id=? AND sku='__CONCEPT_TOTAL__' LIMIT 1");
  $s->execute([$qid]);
  if($r=$s->fetch()){$conceptLabel=trim((string)$r['description'])?:'Materiales';$conceptTotal=(float)$r['unit_price'];}
  $s=$db->prepare('SELECT status FROM quotes WHERE id=?');$s->execute([$qid]);$savedStatus=(string)($s->fetchColumn()?:'borrador');
 }catch(Throwable $e){}
}
if(in_array($a,['save_quote','update_quote'],true)){
 // Los presupuestos comerciales trabajan con importes enteros.
 foreach($_POST['items']??[] as $k=>$item)if(isset($item['unit_price']))$_POST['items'][$k]['unit_price']=(string)round((float)$item['unit_price']);
 foreach($_POST['labor_blocks']??[] as $k=>$labor)if(isset($labor['amount']))$_POST['labor_blocks'][$k]['amount']=(string)round((float)$labor['amount']);
 $_POST['concept_total_amount']=(string)round((float)($_POST['concept_total_amount']??0));
 $amount=max(0,(float)($_POST['concept_total_amount']??0));
 $label=trim((string)($_POST['concept_total_label']??'Materiales'))?:'Materiales';
 if($amount>0){
  $_POST['items']['concept_total']=['is_manual'=>'1','sku'=>'__CONCEPT_TOTAL__','description'=>$label,'unit'=>'total','quantity'=>'1','unit_price'=>(string)$amount,'section_title'=>'Materiales','block_order'=>'9999'];
 }
}
ob_start();require __DIR__.'/module_v20.php';$html=ob_get_clean();
$amountJ=json_encode($conceptTotal,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$labelJ=json_encode($conceptLabel,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$statusJ=json_encode($savedStatus,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$inject=<<<'HTML'
<style>.concept-price-panel{margin:16px 0;padding:14px;border:1px solid #ddd;border-radius:10px;background:#fafafa;display:grid;grid-template-columns:1fr 220px;gap:12px}.concept-price-panel label{font-weight:700;display:block;margin-bottom:5px}.concept-price-panel input{width:100%}.block-material-total{margin-left:auto;white-space:nowrap;font-size:18px;font-weight:800;color:#ff6702;padding:6px 10px}.material-block .block-head{gap:12px}@media(max-width:700px){.concept-price-panel{grid-template-columns:1fr}.block-material-total{width:100%;margin-left:0;text-align:right}}</style>
<script>
(function(){
 const savedAmount=__AMOUNT__,savedLabel=__LABEL__,savedStatus=__STATUS__;
 function esc(s){return String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
 function removeSynthetic(){document.querySelectorAll('tr').forEach(r=>{const sku=r.querySelector('.sku-input,input[name$="[sku]"]');if(sku&&sku.value==='__CONCEPT_TOTAL__')r.remove();});}
 function addPanel(){
  const container=document.getElementById('blocks');if(!container)return;
  let p=document.querySelector('.concept-price-panel');
  if(!p){
   p=document.createElement('div');p.className='concept-price-panel';
   p.innerHTML='<div><label>Opción especial: concepto del precio total</label><input name="concept_total_label" value="'+esc(savedLabel||'Materiales')+'" placeholder="Ej.: Materiales"></div><div><label>Precio total</label><input name="concept_total_amount" type="number" min="0" step="0.01" value="'+(Number(savedAmount||0)||'')+'" placeholder="0.00"></div>';
  }
  if(p.previousElementSibling!==container)container.insertAdjacentElement('afterend',p);
 }
 function ensureSentStatus(){
  const s=document.querySelector('select[name="status"]');if(!s)return;
  if(![...s.options].some(o=>o.value==='enviado')){const o=document.createElement('option');o.value='enviado';o.textContent='Enviado';const approved=[...s.options].find(o=>o.value==='aprobado_inicial');if(approved)s.insertBefore(o,approved);else s.appendChild(o);}
  // Aplicar el estado guardado una sola vez. Antes se restauraba en cada mutación
  // del formulario y podía pisar la elección manual justo antes de guardar.
  if(s.dataset.savedStatusApplied!=='1'){
   if(savedStatus)s.value=savedStatus;
   s.dataset.savedStatusApplied='1';
  }
 }
 function updateMaterialTotals(){
  document.querySelectorAll('.material-block').forEach(block=>{
   let total=0;
   block.querySelectorAll('tbody tr').forEach(row=>{
    const manual=row.dataset.manual==='1',productId=row.dataset.product||row.querySelector('.product-id')?.value||'';
    if(!manual&&!productId)return;
    const quantity=Number(row.querySelector('.qty')?.value||0),price=Number(row.querySelector('.unit-price')?.value||0),discount=Math.max(0,Math.min(100,Number(row.querySelector('.item-discount')?.value||0)));
    total+=quantity*price*(1-discount/100);
   });
   const head=block.querySelector('.block-head');if(!head)return;
   let output=head.querySelector('.block-material-total');
   if(!output){output=document.createElement('strong');output.className='block-material-total';const actions=head.querySelector('.block-actions');if(actions)head.insertBefore(output,actions);else head.appendChild(output);}
   const label='US$ '+Math.round(total).toLocaleString('es-AR');
   if(output.textContent!==label)output.textContent=label;
  });
 }
 function bindMaterialTotals(){
  if(document.body.dataset.materialTotalsBound)return;
  const refresh=e=>{if(e.target.closest?.('.material-block')||e.target.id==='priceList')setTimeout(updateMaterialTotals,0);};
  document.addEventListener('input',refresh);document.addEventListener('change',refresh);
  document.body.dataset.materialTotalsBound='1';
 }
 function syncClientFromProject(){
  const project=document.getElementById('project'),client=document.getElementById('client');
  if(!project||!client)return;
  const selected=project.selectedOptions[0],projectClient=selected?.dataset.client||'';
  if(projectClient&&client.value!==projectClient){
   client.value=projectClient;
   client.dispatchEvent(new Event('change',{bubbles:true}));
  }
  if(!project.dataset.clientSyncBound){
   project.addEventListener('change',syncClientFromProject);
   project.dataset.clientSyncBound='1';
  }
 }
 function run(){removeSynthetic();addPanel();ensureSentStatus();syncClientFromProject();bindMaterialTotals();updateMaterialTotals();}
 run();new MutationObserver(run).observe(document.body,{childList:true,subtree:true});
})();
</script>
HTML;
$inject=str_replace(['__AMOUNT__','__LABEL__','__STATUS__'],[$amountJ,$labelJ,$statusJ],$inject);
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
