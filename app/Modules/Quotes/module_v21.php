<?php
declare(strict_types=1);
$a=$_GET['a']??'new_quote';$root=dirname(__DIR__,3);$qid=(int)($_POST['quote_id']??$_GET['id']??0);
$conceptTotal=0.0;$conceptLabel='Materiales';
if($a==='edit_quote'&&$qid>0){
 try{$cfg=require $root.'/config.php';$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$s=$db->prepare("SELECT description,unit_price FROM quote_items WHERE quote_id=? AND sku='__CONCEPT_TOTAL__' LIMIT 1");$s->execute([$qid]);if($r=$s->fetch()){$conceptLabel=trim((string)$r['description'])?:'Materiales';$conceptTotal=(float)$r['unit_price'];}}catch(Throwable $e){}
}
if(in_array($a,['save_quote','update_quote'],true)){
 $amount=max(0,(float)($_POST['concept_total_amount']??0));$label=trim((string)($_POST['concept_total_label']??'Materiales'))?:'Materiales';
 if($amount>0){$_POST['items']['concept_total']=['is_manual'=>'1','sku'=>'__CONCEPT_TOTAL__','description'=>$label,'unit'=>'total','quantity'=>'1','unit_price'=>(string)$amount,'section_title'=>'Materiales','block_order'=>'9999'];}
}
ob_start();require __DIR__.'/module_v20.php';$html=ob_get_clean();
$amountJ=json_encode($conceptTotal);$labelJ=json_encode($conceptLabel,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$inject=<<<HTML
<style>.concept-price-panel{margin:16px 0;padding:14px;border:1px solid #ddd;border-radius:10px;background:#fafafa;display:grid;grid-template-columns:1fr 220px;gap:12px}.concept-price-panel label{font-weight:700;display:block;margin-bottom:5px}.concept-price-panel input{width:100%}@media(max-width:700px){.concept-price-panel{grid-template-columns:1fr}}</style>
<script>
(function(){
 const savedAmount=$amountJ,savedLabel=$labelJ;
 function removeSynthetic(){document.querySelectorAll('tr').forEach(r=>{const sku=r.querySelector('.sku-input,input[name$="[sku]"]');if(sku&&sku.value==='__CONCEPT_TOTAL__')r.remove();});}
 function addPanel(){
  if(document.querySelector('.concept-price-panel'))return;
  const blocks=document.querySelectorAll('.material-block');if(!blocks.length)return;
  const last=blocks[blocks.length-1];const p=document.createElement('div');p.className='concept-price-panel';
  p.innerHTML=`<div><label>Concepto del precio total</label><input name="concept_total_label" value="${String(savedLabel||'Materiales').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}" placeholder="Ej.: Materiales"></div><div><label>Precio total</label><input name="concept_total_amount" type="number" min="0" step="0.01" value="${Number(savedAmount||0)||''}" placeholder="0.00"></div>`;
  last.insertAdjacentElement('afterend',p);
 }
 function run(){removeSynthetic();addPanel();}
 run();new MutationObserver(run).observe(document.body,{childList:true,subtree:true});
})();
</script>
HTML;
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
