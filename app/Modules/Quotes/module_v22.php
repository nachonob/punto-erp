<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/module_v21.php';
$html=ob_get_clean();

$inject=<<<'HTML'
<style>
.quote-drag-handle{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;margin-right:7px;border:1px solid #cbd1d7;border-radius:7px;background:#f4f6f8;color:#59636e;font-size:18px;font-weight:800;cursor:grab;touch-action:none;vertical-align:middle;user-select:none}
.quote-drag-handle:active{cursor:grabbing}
.material-block tbody tr.quote-dragging{opacity:.42;background:#fff3e8}
.material-block tbody.quote-drop-target{outline:2px dashed #ff6702;outline-offset:-2px}
.quote-sort-help{display:inline-block;margin:5px 0 10px;color:#6e7781;font-size:13px}
@media(max-width:700px){.quote-drag-handle{width:36px;height:36px;font-size:21px}}
</style>
<script>
(function(){
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
    if(concept)concept.textContent=String(rowIndex+1);
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
  const handle=document.createElement('button');
  handle.type='button';handle.className='quote-drag-handle';handle.title='Arrastrar para cambiar el orden';handle.setAttribute('aria-label','Mover producto');handle.textContent='↕';
  first.insertBefore(handle,first.firstChild);

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
  if(!body.dataset.dropReady){
   body.dataset.dropReady='1';
   body.addEventListener('dragover',event=>{if(!desktopRow)return;event.preventDefault();body.classList.add('quote-drop-target');insertAtPoint(desktopRow,body,event.clientY);});
   body.addEventListener('dragleave',event=>{if(!body.contains(event.relatedTarget))body.classList.remove('quote-drop-target');});
   body.addEventListener('drop',event=>{if(!desktopRow)return;event.preventDefault();body.classList.remove('quote-drop-target');insertAtPoint(desktopRow,body,event.clientY);syncOrder();});
  }
  rows(body).forEach(enhanceRow);
  const block=body.closest('.material-block');
  const anchor=block?.querySelector('.block-body');
  if(anchor&&!block.querySelector('.quote-sort-help')){const help=document.createElement('span');help.className='quote-sort-help';help.textContent='Usá ↕ para arrastrar y ordenar los productos.';anchor.insertBefore(help,anchor.firstChild);}
 }

 function enhance(){
  document.querySelectorAll('.material-block tbody').forEach(enhanceBody);
 }

 enhance();
 const observer=new MutationObserver(()=>enhance());
 observer.observe(document.body,{childList:true,subtree:true});
 document.addEventListener('change',event=>{if(event.target.matches('.block-title'))syncOrder();});
})();
</script>
HTML;

if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
