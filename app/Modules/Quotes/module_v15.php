<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/module_v14.php';
$html=ob_get_clean();

$inject=<<<'HTML'
<script>
(function(){
  const form=document.getElementById('quoteForm');
  if(!form)return;
  function nameLaborFields(){
    document.querySelectorAll('.labor-block').forEach((b,i)=>{
      const map={
        title:b.querySelector('.labor-title'),
        description:b.querySelector('.labor-description'),
        amount:b.querySelector('.labor-amount'),
        tax_mode:b.querySelector('.labor-tax'),
        vat_rate:b.querySelector('.labor-vat')
      };
      Object.entries(map).forEach(([key,el])=>{if(el)el.name=`labor_blocks[${i}][${key}]`;});
      let order=b.querySelector('.labor-order-direct');
      if(!order){order=document.createElement('input');order.type='hidden';order.className='labor-order-direct';b.appendChild(order);}
      order.name=`labor_blocks[${i}][block_order]`;
      order.value=b.dataset.order||((i+1)*10);
    });
  }
  nameLaborFields();
  form.addEventListener('submit',nameLaborFields,true);
  const blocks=document.getElementById('blocks');
  if(blocks)new MutationObserver(nameLaborFields).observe(blocks,{childList:true,subtree:true});
})();
</script>
HTML;

if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
