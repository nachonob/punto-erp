<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/module_v22.php';
$html=ob_get_clean();

$categoryNames=[];
try{
 $categoryNames=$db->query("SELECT name FROM product_categories ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
}catch(Throwable $e){
 $categoryNames=[];
}
$categoryNamesJson=json_encode(array_values(array_unique(array_filter(array_map('strval',$categoryNames)))),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

$inject=<<<'HTML'
<style>
.quote-product-filters{margin:0 0 18px;padding:16px 18px;border:1px solid #dfe3e8;border-radius:12px;background:#f8fafb}
.quote-product-filters__head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:12px}
.quote-product-filters__title{margin:0;font-size:15px;font-weight:800;color:#172437}
.quote-product-filters__help{margin:3px 0 0;color:#6c7785;font-size:13px}
.quote-product-filter-chips{display:flex;flex-wrap:wrap;gap:8px}
.quote-product-filter-chip{display:inline-flex;align-items:center;gap:7px;padding:7px 11px;border:1px solid #cfd6dd;border-radius:999px;background:#fff;color:#263445;font-size:13px;font-weight:700;cursor:pointer;user-select:none}
.quote-product-filter-chip:has(input:checked){border-color:#ff6702;background:#fff2e9;color:#b84600}
.quote-product-filter-chip input{width:auto!important;height:auto!important;margin:0;accent-color:#ff6702}
@media(max-width:700px){.quote-product-filters{padding:14px}.quote-product-filters__head{display:block}.quote-product-filter-chip{padding:9px 12px}}
</style>
<script>
(function(){
 if(typeof products==='undefined'||typeof searchProducts!=='function')return;
 const blocks=document.getElementById('blocks');
 if(!blocks||document.getElementById('quote-product-filters'))return;

 const rubroSelect=document.querySelector('select[name="quote_category"]');
 const rubroField=rubroSelect?.closest('p');
 if(rubroField)rubroField.hidden=true;

 const configuredNames=__CATEGORY_NAMES__;
 const canonicalLifeSmart='Domótica LifeSmart';
 const lifeSmartAliases=new Set([
  'domótica lifesmart',
  'domotica lifesmart',
  'domótica live smart',
  'domotica live smart',
  'lifesmart / domótica',
  'lifesmart / domotica',
  'live smart / domótica',
  'live smart / domotica'
 ]);
 const normalizedCategory=name=>{
  const value=String(name||'').trim();
  return lifeSmartAliases.has(value.toLocaleLowerCase('es'))?canonicalLifeSmart:value;
 };
 const productCategoryNames=Object.values(products).map(product=>String(product.category||'Otros').trim());
 const names=[...new Set([
  ...configuredNames.map(normalizedCategory),
  ...productCategoryNames.map(normalizedCategory)
 ].filter(Boolean))]
  .sort((a,b)=>{
   return a.localeCompare(b,'es',{sensitivity:'base'});
  });

 const panel=document.createElement('section');
 panel.id='quote-product-filters';
 panel.className='quote-product-filters';
 panel.innerHTML='<div class="quote-product-filters__head"><div><p class="quote-product-filters__title">Categorías de productos</p><p class="quote-product-filters__help">Podés elegir varias categorías. Con “Todas” vas a encontrar el catálogo completo.</p></div></div><div class="quote-product-filter-chips"></div>';
 const chips=panel.querySelector('.quote-product-filter-chips');

 function addChip(label,value,checked){
  const chip=document.createElement('label');
  chip.className='quote-product-filter-chip';
  const input=document.createElement('input');
  input.type='checkbox';input.value=value;input.checked=checked;
  input.dataset.productCategory=value;
  const text=document.createElement('span');text.textContent=label;
  chip.append(input,text);chips.appendChild(chip);
  return input;
 }

 const all=addChip('Todas','__all__',true);
 const categoryInputs=names.map(name=>addChip(name,name,false));

 function selectedCategories(){
  if(all.checked)return [];
  return categoryInputs.filter(input=>input.checked).map(input=>input.value);
 }

 searchProducts=function(q,mode){
  q=String(q||'').trim().toLowerCase();
  let available=Object.values(products);
  const selected=selectedCategories();
  if(selected.length)available=available.filter(product=>selected.includes(normalizedCategory(product.category||'Otros')));
  if(q)available=available.filter(product=>(String(product.sku||'')+' '+String(product.description||'')+' '+String(product.category||'')).toLowerCase().includes(q));
  return available.slice(0,100);
 };

 all.addEventListener('change',()=>{
  if(all.checked)categoryInputs.forEach(input=>input.checked=false);
  document.querySelectorAll('.suggestions').forEach(list=>list.style.display='none');
 });
 categoryInputs.forEach(input=>input.addEventListener('change',()=>{
  if(input.checked)all.checked=false;
  if(!categoryInputs.some(item=>item.checked))all.checked=true;
  document.querySelectorAll('.suggestions').forEach(list=>list.style.display='none');
 }));

 blocks.insertAdjacentElement('beforebegin',panel);
})();
</script>
HTML;

$inject=str_replace('__CATEGORY_NAMES__',$categoryNamesJson,$inject);

if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
