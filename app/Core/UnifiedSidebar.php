<?php
declare(strict_types=1);
function erpIsAdmin():bool{return ($_SESSION['user']['role']??'')==='admin';}
function erpCanView(string $module):bool{if(erpIsAdmin())return true;$p=$_SESSION['user']['permissions']??[];if(!$p)return true;return !empty($p[$module]['view']);}
function erpSidebar(string $current=''):void{$u=$_SESSION['user']??[];$inv=['inventory','inventory_movements','warehouses','inventory_transfer','inventory_reorder','save_inventory_movement','save_inventory_transfer','save_warehouse','update_min_stock'];$prod=['products','new_product','edit_product','product_categories','price_lists'];$proj=['projects','project','new_project','edit_project'];$quotes=['quotes','new_quote','save_quote','edit_quote','update_quote','quote_view','quote_print'];?>
<aside class="sidebar" id="sidebar"><div class="sidebar-brand"><strong><i>●</i> Punto ERP</strong><span>Gestión administrativa</span></div><nav class="sidebar-nav">
<?php if(erpCanView('dashboard')):?><a class="<?=$current==='dashboard'?'active':''?>" href="index.php"><span class="nav-icon">▦</span>Panel general</a><?php endif;?>
<?php if(erpCanView('clients')):?><a class="<?=in_array($current,['clients','new_client','edit_client'],true)?'active':''?>" href="?a=clients"><span class="nav-icon">◇</span>Clientes</a><?php endif;?>
<?php if(erpCanView('partners')):?><a class="<?=in_array($current,['partners','new_partner','edit_partner'],true)?'active':''?>" href="?a=partners"><span class="nav-icon">⌂</span>Arquitectos y constructoras</a><?php endif;?>
<?php if(erpCanView('products')):?><div class="nav-group"><div class="nav-group-title"><span class="nav-icon">▦</span>Ventas</div><div class="nav-submenu"><a class="<?=in_array($current,$prod,true)?'active':''?>" href="?a=products">Productos</a><a class="<?=$current==='product_categories'?'active':''?>" href="?a=product_categories">Categorías</a><a class="<?=$current==='price_lists'?'active':''?>" href="?a=price_lists">Listas de precios</a></div></div>
<div class="nav-group"><div class="nav-group-title"><span class="nav-icon">▣</span>Inventario</div><div class="nav-submenu"><a class="<?=$current==='inventory'?'active':''?>" href="?a=inventory">Stock actual</a><a class="<?=$current==='inventory_movements'?'active':''?>" href="?a=inventory_movements">Movimientos</a><a class="<?=$current==='warehouses'?'active':''?>" href="?a=warehouses">Depósitos</a><a class="<?=$current==='inventory_transfer'?'active':''?>" href="?a=inventory_transfer">Transferencias</a><a class="<?=$current==='inventory_reorder'?'active':''?>" href="?a=inventory_reorder">Reposición</a></div></div><?php endif;?>
<?php if(erpCanView('projects')):?><a class="<?=in_array($current,$proj,true)?'active':''?>" href="?a=projects"><span class="nav-icon">▤</span>Proyectos</a><a class="<?=in_array($current,$quotes,true)?'active':''?>" href="?a=quotes"><span class="nav-icon">▥</span>Presupuestos</a><?php endif;?>
<?php if(erpCanView('receipts')):?><a class="<?=in_array($current,['receipts','receipt','new_payment'],true)?'active':''?>" href="?a=receipts"><span class="nav-icon">▧</span>Recibos y pagos</a><?php endif;?>
<?php if(erpIsAdmin()):?><a class="<?=in_array($current,['users','profiles','edit_profile'],true)?'active':''?>" href="?a=users"><span class="nav-icon">○</span>Perfiles y usuarios</a><?php endif;?>
</nav><div class="sidebar-user"><small><?=erpIsAdmin()?'Administrador':htmlspecialchars((string)($u['profile_name']??'Usuario'),ENT_QUOTES,'UTF-8')?></small><strong><?=htmlspecialchars((string)($u['name']??'Usuario'),ENT_QUOTES,'UTF-8')?></strong><a href="?a=logout">Cerrar sesión</a></div></aside><div class="sidebar-overlay" onclick="document.getElementById('sidebar').classList.toggle('open')"></div>
<style>
#items td:nth-child(1),#items~*{} 
#items td:nth-child(1){position:relative}
#items td:nth-child(2) .suggest-wrap{min-width:0;width:100%}
#items td:nth-child(2) .desc-input{width:100%;min-width:0}
#items td:nth-child(3){white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#items tr.dragging{opacity:.45;background:#fff5ee}
#items tr.drag-over{box-shadow:inset 0 3px 0 #ff6702}
.drag-handle{position:absolute;left:-17px;top:50%;transform:translateY(-50%);cursor:grab;color:#9aa3ad;font-size:18px;line-height:1;user-select:none;padding:8px 4px}
.drag-handle:active{cursor:grabbing}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const select=document.getElementById('laborPreset'),input=document.getElementById('laborDescription');
 if(select&&input){
  const a='Configuración, montaje y diseño de escenas',b='Cableado, montaje y configuración';
  select.innerHTML='';[[a,a],[b,b],['__manual__','Otros']].forEach(function(o){const op=document.createElement('option');op.value=o[0];op.textContent=o[1];select.appendChild(op)});
  const current=(input.value||'').trim();if(current===b){select.value=b}else if(current!==''&&current!==a){select.value='__manual__'}else{select.value=a;if(current==='')input.value=a}
  select.addEventListener('change',function(){if(this.value==='__manual__'){input.value='';input.focus()}else{input.value=this.value}});
 }
 const items=document.getElementById('items');
 if(items){
  const scroller=items.closest('div[style*="overflow:auto"]');
  if(scroller){scroller.style.overflow='visible';scroller.style.width='100%';}
  const table=items.closest('table');
  if(table){
   table.style.width='100%';table.style.tableLayout='fixed';
   const heads=table.querySelectorAll('thead th');
   const widths=['16%','30%','11%','8%','15%','15%','5%'];
   heads.forEach(function(th,i){if(widths[i])th.style.setProperty('width',widths[i],'important')});
  }
  const card=items.closest('.card');
  if(card){card.style.overflow='visible';card.style.minHeight='360px';}

  let dragged=null;
  function renumberRows(){
   Array.from(items.querySelectorAll('tr')).forEach(function(tr,index){
    tr.querySelectorAll('[name]').forEach(function(el){
     el.name=el.name.replace(/items\[\d+\]/,'items['+index+']');
    });
   });
  }
  function prepareRow(tr){
   if(!tr||tr.dataset.dragReady==='1')return;tr.dataset.dragReady='1';tr.draggable=true;
   const first=tr.cells&&tr.cells[0];
   if(first&&!first.querySelector('.drag-handle')){const h=document.createElement('span');h.className='drag-handle';h.title='Arrastrar para cambiar posición';h.textContent='⋮⋮';first.prepend(h)}
   tr.addEventListener('dragstart',function(ev){if(!ev.target.closest('.drag-handle')){ev.preventDefault();return}dragged=tr;tr.classList.add('dragging');ev.dataTransfer.effectAllowed='move';ev.dataTransfer.setData('text/plain','move')});
   tr.addEventListener('dragend',function(){tr.classList.remove('dragging');items.querySelectorAll('.drag-over').forEach(function(r){r.classList.remove('drag-over')});dragged=null;renumberRows();if(typeof calc==='function')calc()});
   tr.addEventListener('dragover',function(ev){if(!dragged||dragged===tr)return;ev.preventDefault();tr.classList.add('drag-over');const rect=tr.getBoundingClientRect();const after=ev.clientY>rect.top+rect.height/2;items.insertBefore(dragged,after?tr.nextSibling:tr)});
   tr.addEventListener('dragleave',function(){tr.classList.remove('drag-over')});
   tr.addEventListener('drop',function(ev){ev.preventDefault();tr.classList.remove('drag-over');renumberRows();if(typeof calc==='function')calc()});
  }
  Array.from(items.querySelectorAll('tr')).forEach(prepareRow);
  new MutationObserver(function(mutations){mutations.forEach(function(m){m.addedNodes.forEach(function(n){if(n.nodeType===1&&n.tagName==='TR')prepareRow(n)})});renumberRows()}).observe(items,{childList:true});
 }
 function quoteSearchInput(el){return el&&el.matches&&el.matches('.sku-input,.desc-input')}
 function renderAllOrFiltered(el){
  if(typeof products==='undefined'||typeof selectProduct!=='function')return;
  const wrap=el.closest('.suggest-wrap'),box=wrap&&wrap.querySelector('.suggestions');if(!box)return;
  const q=(el.value||'').toLowerCase().trim(),isSku=el.classList.contains('sku-input');
  let matches=Object.values(products);
  if(q!=='')matches=matches.filter(function(p){const value=isSku?String(p.sku||''):String(p.description||'');return value.toLowerCase().includes(q)});
  matches.sort(function(a,b){const av=isSku?String(a.sku||''):String(a.description||'');const bv=isSku?String(b.sku||''):String(b.description||'');return av.localeCompare(bv,'es',{numeric:true,sensitivity:'base'})});
  box.innerHTML=matches.map(function(p){return '<div class="suggestion" data-id="'+p.id+'"><b>'+esc(p.sku)+'</b><span>'+esc(p.description)+'</span></div>'}).join('')||'<div class="suggestion">Sin coincidencias</div>';
  box.style.display='block';box.style.maxHeight='340px';box.style.overflowY='auto';box.style.minWidth=isSku?'360px':'620px';box.style.width='max-content';box.style.maxWidth='72vw';
  box.querySelectorAll('[data-id]').forEach(function(item){item.onmousedown=function(ev){ev.preventDefault();selectProduct(el.closest('tr'),item.dataset.id)}});
 }
 document.addEventListener('focusin',function(ev){if(quoteSearchInput(ev.target))renderAllOrFiltered(ev.target)});
 document.addEventListener('input',function(ev){if(quoteSearchInput(ev.target))renderAllOrFiltered(ev.target)});
});
</script>
<?php }
function erpSidebarCss():string{return ':root{--o:#ff6702;--dark:#171b20;--ink:#27303b;--muted:#6e7781;--line:#e1e5e9;--bg:#f4f6f8;--sidebar:270px}*{box-sizing:border-box}.sidebar{position:fixed;z-index:30;inset:0 auto 0 0;width:var(--sidebar);display:flex;flex-direction:column;padding:28px 18px 20px;background:var(--dark);color:#fff;box-shadow:8px 0 30px #11182014}.sidebar-brand{padding:0 12px 28px;border-bottom:1px solid #ffffff18}.sidebar-brand strong{display:block;font-size:22px}.sidebar-brand strong i{color:var(--o);font-style:normal}.sidebar-brand span{display:block;margin-top:6px;color:#aeb7c2;font-size:13px}.sidebar-nav{display:flex;flex-direction:column;gap:5px;padding:24px 0;overflow:auto}.sidebar-nav a{display:flex;align-items:center;gap:12px;padding:11px 13px;border-radius:9px;color:#d8dde3;text-decoration:none;font-weight:600}.sidebar-nav a:hover{background:#ffffff0d;color:#fff}.sidebar-nav a.active{background:var(--o);color:#fff}.nav-icon{width:22px;text-align:center}.nav-group{margin:4px 0}.nav-group-title{display:flex;align-items:center;gap:12px;padding:11px 13px;color:#fff;font-weight:750}.nav-submenu{display:flex;flex-direction:column;gap:3px;margin:0 0 7px 34px;padding-left:10px;border-left:1px solid #ffffff26}.sidebar-nav .nav-submenu a{padding:8px 11px;font-size:14px;font-weight:550}.sidebar-user{margin-top:auto;padding:18px 12px 0;border-top:1px solid #ffffff18}.sidebar-user small{display:block;color:#aeb7c2}.sidebar-user strong{display:block;margin:3px 0 12px}.sidebar-user a{color:#fff;text-decoration:none}.sidebar-overlay{display:none}@media(max-width:900px){.sidebar{transform:translateX(-100%);transition:.2s}.sidebar.open{transform:translateX(0)}.sidebar-overlay{position:fixed;z-index:25;inset:0;background:#11182080}.sidebar.open+.sidebar-overlay{display:block}}';}
