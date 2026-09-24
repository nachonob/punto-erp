<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/module_v21.php';
$html=ob_get_clean();

$defaultNotes=<<<'TEXT'
FORMA DE PAGO:
Anticipo por ingeniería: 10% del total del Proyecto, será asignado como crédito al realizar el pago de la mano de obra.
100% de materiales mínimo un mes antes de ingresar en obra. 50% de Mano de obra al ingresar, el resto al terminar el trabajo.

NO INCLUYE CABLEADO, NI CABLES (EN EL CASO DE NECESITARLO).
NO INCLUYE PERFORACIONES EN LAS PUERTAS PARA MONTAJE DE CERRADURAS
COTIZACIÓN: Dólar billete venta banco nación.
Plazo de entrega 30-40 días según importación y aprobación de seguridad eléctrica
TEXT;
$defaultNotesJson=json_encode($defaultNotes,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$isNewQuoteJson=json_encode($a==='new_quote');

$inject=<<<'HTML'
<style>
.quote-drag-cell{width:44px;min-width:44px;max-width:44px;padding:8px 6px!important;text-align:center;vertical-align:middle}.quote-drag-head{width:44px;min-width:44px;max-width:44px;padding:0!important}.quote-drag-handle{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;margin:0;border:1px solid #cbd1d7;border-radius:7px;background:#f4f6f8;color:#59636e;font-size:18px;font-weight:800;cursor:grab;touch-action:none;vertical-align:middle;user-select:none}
.quote-drag-handle:active{cursor:grabbing}
.material-block tbody tr.quote-dragging{opacity:.42;background:#fff3e8}
.material-block tbody.quote-drop-target{outline:2px dashed #ff6702;outline-offset:-2px}
@media(max-width:700px){.quote-drag-handle{width:36px;height:36px;font-size:21px}}
</style>
<script>
(function(){
 const defaultNotes=__DEFAULT_NOTES__,isNewQuote=__IS_NEW_QUOTE__;
 let desktopRow=null,pointerRow=null,lastPointerY=0;

 function bodyOf(row){return row&&row.closest('.material-block tbody');}
 function rows(body){return [...body.querySelectorAll(':scope > tr')];}

 function syncOrder(){
  document.querySelectorAll('.material-block').forEach((block,blockIndex)=>{
   block.dataset.order=String(blockIndex);
   const title=block.querySelector('.block-title')?.value||'Materiales';
   rows(block.querySelector('tbody')).forEach((row,rowIndex)=>{
    const section=row.querySelector('.section-title-hidden');
    const order=row.querySelector('.block-order-hidden');
    if(section)section.value=title;
    if(order)order.value=String(blockIndex);
    const concept=row.querySelector('.concept-no');
    if(concept){
     const conceptIndex=rows(block.querySelector('tbody')).filter(item=>item.dataset.concept==='1').indexOf(row);
     concept.textContent='Concepto '+String(conceptIndex+1);
    }
   });
  });
  if(typeof window.renumberBlocks==='function')window.renumberBlocks();
  if(typeof window.calcTotals==='function')window.calcTotals();
 }

 function insertAtPoint(row,body,y){
  const candidates=rows(body).filter(r=>r!==row);
  const before=candidates.find(r=>{const box=r.getBoundingClientRect();return y<box.top+box.height/2;});
  if(before)body.insertBefore(row,before);else body.appendChild(row);
 }

 function clearTargets(){
  document.querySelectorAll('.quote-drop-target').forEach(el=>el.classList.remove('quote-drop-target'));
 }

 function enhanceRow(row){
  if(row.dataset.dragReady)return;
  row.dataset.dragReady='1';
  row.draggable=true;
  const first=row.firstElementChild;
  if(!first)return;
  const cell=document.createElement('td');cell.className='quote-drag-cell';
  const handle=document.createElement('button');
  handle.type='button';handle.className='quote-drag-handle';handle.title='Arrastrar para cambiar el orden';handle.setAttribute('aria-label','Mover producto');handle.textContent='↕';
  cell.appendChild(handle);row.insertBefore(cell,first);

  handle.addEventListener('mousedown',()=>row.dataset.dragArmed='1');
  document.addEventListener('mouseup',()=>delete row.dataset.dragArmed,{once:true});
  row.addEventListener('dragstart',event=>{
   if(row.dataset.dragArmed!=='1'){event.preventDefault();return;}
   desktopRow=row;row.classList.add('quote-dragging');event.dataTransfer.effectAllowed='move';event.dataTransfer.setData('text/plain','quote-product');
  });
  row.addEventListener('dragend',()=>{row.classList.remove('quote-dragging');desktopRow=null;clearTargets();syncOrder();});

  handle.addEventListener('pointerdown',event=>{
   if(event.pointerType==='mouse')return;
   pointerRow=row;lastPointerY=event.clientY;row.classList.add('quote-dragging');handle.setPointerCapture(event.pointerId);event.preventDefault();
  });
  handle.addEventListener('pointermove',event=>{
   if(pointerRow!==row)return;
   lastPointerY=event.clientY;
   const hit=document.elementFromPoint(event.clientX,event.clientY);
   const body=hit?.closest?.('.material-block tbody');
   if(!body)return;
   clearTargets();body.classList.add('quote-drop-target');insertAtPoint(row,body,event.clientY);event.preventDefault();
  });
  const finish=()=>{if(pointerRow!==row)return;row.classList.remove('quote-dragging');pointerRow=null;clearTargets();syncOrder();};
  handle.addEventListener('pointerup',finish);handle.addEventListener('pointercancel',finish);
 }

 function enhanceBody(body){
  const table=body.closest('table'),head=table?.querySelector('thead tr')||table?.querySelector('tr');
  if(head&&!head.querySelector('.quote-drag-head')){const th=document.createElement('th');th.className='quote-drag-head';th.setAttribute('aria-label','Orden');head.insertBefore(th,head.firstElementChild);}
  if(!body.dataset.dropReady){
   body.dataset.dropReady='1';
   body.addEventListener('dragover',event=>{if(!desktopRow)return;event.preventDefault();body.classList.add('quote-drop-target');insertAtPoint(desktopRow,body,event.clientY);});
   body.addEventListener('dragleave',event=>{if(!body.contains(event.relatedTarget))body.classList.remove('quote-drop-target');});
   body.addEventListener('drop',event=>{if(!desktopRow)return;event.preventDefault();body.classList.remove('quote-drop-target');insertAtPoint(desktopRow,body,event.clientY);syncOrder();});
  }
  rows(body).forEach(enhanceRow);
 }

 function applyDefaultNotes(){
  if(!isNewQuote)return;
  const notes=document.querySelector('textarea[name="notes"]');
  if(notes&&!notes.value.trim())notes.value=defaultNotes;
 }

 function enhance(){
  applyDefaultNotes();
  document.querySelectorAll('.material-block tbody').forEach(enhanceBody);
 }

 enhance();
 const observer=new MutationObserver(()=>enhance());
 observer.observe(document.body,{childList:true,subtree:true});
 document.addEventListener('change',event=>{if(event.target.matches('.block-title'))syncOrder();});
})();
</script>
HTML;
$inject=str_replace(['__DEFAULT_NOTES__','__IS_NEW_QUOTE__'],[$defaultNotesJson,$isNewQuoteJson],$inject);

if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
