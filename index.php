<?php
declare(strict_types=1);
$a=$_GET['a']??'';
if($a==='quotes'){
 require __DIR__.'/app/Modules/Quotes/list_v2.php';
 exit;
}
if(in_array($a,['new_quote','save_quote','edit_quote','update_quote','quote_view','quote_print'],true)){
 require __DIR__.'/app/Modules/Quotes/module_v5.php';
 exit;
}
if(in_array($a,['new_project','save_project'],true)){
 require __DIR__.'/app/Modules/Projects/new_v2.php';
 exit;
}
if(in_array($a,['products','new_product','edit_product','save_product','update_product','price_lists','save_price_list','update_price_list','product_categories','save_product_category','update_product_category','stock_movement','save_stock_movement'],true)){
 require __DIR__.'/app/Modules/Products/module_costs.php';
 exit;
}
if(in_array($a,['inventory_movements','save_inventory_movement'],true)){
 require __DIR__.'/app/Modules/Inventory/movements.php';
 exit;
}
if(in_array($a,['inventory','warehouses','inventory_transfer','inventory_reorder','save_warehouse','update_min_stock','save_inventory_transfer'],true)){
 require __DIR__.'/app/Modules/Inventory/module_v2.php';
 exit;
}
$module=$_GET['module']??'accounts';
$registry=require __DIR__.'/app/modules.php';
if(!isset($registry[$module])){http_response_code(404);exit('Módulo inexistente.');}
if(empty($registry[$module]['enabled'])){
 require __DIR__.'/app/Core/ComingSoon.php';
 exit;
}
require __DIR__.'/'.$registry[$module]['entry'];
?><script>(function(){
 const nav=document.querySelector('.sidebar-nav');if(!nav)return;
 if(!nav.querySelector('a[href="?a=quotes"]')){const link=document.createElement('a');link.href='?a=quotes';link.innerHTML='<span class="nav-icon">▥</span>Presupuestos';const projectLink=nav.querySelector('a[href="?a=projects"]');if(projectLink&&projectLink.parentNode===nav){projectLink.insertAdjacentElement('afterend',link)}else{nav.appendChild(link)}}
 if(!nav.querySelector('a[href="?a=inventory"]')){const group=document.createElement('div');group.className='nav-group';group.innerHTML='<div class="nav-group-title"><span class="nav-icon">▣</span>Inventario</div><div class="nav-submenu"><a href="?a=inventory">Stock actual</a><a href="?a=inventory_movements">Movimientos</a><a href="?a=warehouses">Depósitos</a><a href="?a=inventory_transfer">Transferencias</a><a href="?a=inventory_reorder">Reposición</a></div>';const sales=Array.from(nav.querySelectorAll('.nav-group')).find(g=>g.textContent.includes('Ventas'));if(sales)sales.insertAdjacentElement('afterend',group);else nav.appendChild(group)}
})();</script>
