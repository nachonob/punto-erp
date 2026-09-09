<?php
declare(strict_types=1);
ob_start();require __DIR__.'/module_v16.php';$html=ob_get_clean();
$inject=<<<'HTML'
<style>
.discount-row{grid-template-columns:1.35fr 1.6fr 1.05fr .85fr 1.25fr auto!important}.discount-concept input{min-width:150px}@media(max-width:900px){.discount-row{grid-template-columns:1fr 1fr!important}.discount-concept{grid-column:span 2}}
</style>
<script>
(function(){
 const rows=document.getElementById('discountRows');if(!rows)return;
 function enhance(r){
   if(r.dataset.conceptReady)return;r.dataset.conceptReady='1';
   const hidden=r.querySelector('input[name$="[description]"]');if(!hidden)return;
   const p=document.createElement('p');p.className='discount-concept';p.innerHTML='<label>Concepto</label><input type="text" placeholder="Ej.: Descuento comercial">';
   const inp=p.querySelector('input');inp.value=hidden.value||'';
   inp.addEventListener('input',()=>{hidden.value=inp.value;hidden.setAttribute('value',inp.value)});
   r.insertBefore(p,r.querySelector('.discount-remove'));
 }
 rows.querySelectorAll('.discount-row').forEach(enhance);
 new MutationObserver(()=>rows.querySelectorAll('.discount-row').forEach(enhance)).observe(rows,{childList:true,subtree:true});
 const form=document.getElementById('quoteForm');
 if(form)form.addEventListener('submit',()=>{rows.querySelectorAll('.discount-row').forEach(r=>{const visible=r.querySelector('.discount-concept input'),hidden=r.querySelector('input[name$="[description]"]');if(visible&&hidden)hidden.value=visible.value;});});
})();
</script>
HTML;
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;echo $html;
