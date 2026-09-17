<?php
declare(strict_types=1);

ob_start();
require __DIR__.'/module_v23.php';
$html=ob_get_clean();

$categoryNames=[];
try{
    $categoryNames=$db->query("SELECT name FROM product_categories WHERE active=1 ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
}catch(Throwable $e){
    try{
        $categoryNames=$db->query("SELECT name FROM product_categories ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    }catch(Throwable $ignored){
        $categoryNames=[];
    }
}
$categoryNamesJson=json_encode(
    array_values(array_unique(array_filter(array_map('strval',$categoryNames)))),
    JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
);

$inject=<<<'HTML'
<style>
.quote-rubro-help{display:block;margin-top:6px;color:#6c7785;font-size:12px;font-weight:400}
.quote-hidden-families{display:none!important}
</style>
<script>
(function(){
 const rubro=document.querySelector('select[name="quote_category"]');
 const familyLabel=[...document.querySelectorAll('label')].find(label=>label.textContent.trim()==='Familias incluidas');
 const familyBlock=familyLabel?.closest('.c6');
 if(!rubro||!familyBlock)return;

 const originalField=rubro.closest('p');
 const form=rubro.closest('form');
 const familyInputs=[...familyBlock.querySelectorAll('input[name="quote_families[]"]')];
 const hiddenFamilies=document.createElement('div');
 hiddenFamilies.className='quote-hidden-families';
 familyInputs.forEach(input=>hiddenFamilies.appendChild(input));
 form?.appendChild(hiddenFamilies);

 const currentValue=String(rubro.value||'').trim();
 const categoryNames=__CATEGORY_NAMES__;
 const aliases={
  domotica:'LifeSmart / Domótica',
  redes:'Redes',
  camaras:'Cámaras',
  alarma:'Alarma',
  audio:'Audio',
  electricidad:'Electricidad',
  general:'General'
 };
 const normalized=value=>String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();
 const unique=[...new Set(categoryNames.map(name=>String(name||'').trim()).filter(Boolean))];
 const desired=aliases[normalized(currentValue)]||currentValue;
 let selected=unique.find(name=>normalized(name)===normalized(desired))||'';
 if(!selected&&currentValue){
  unique.push(desired);
  selected=desired;
 }
 unique.sort((a,b)=>{
  const label=value=>value==='LifeSmart / Domótica'?'Domótica LifeSmart':value;
  return label(a).localeCompare(label(b),'es',{sensitivity:'base'});
 });

 rubro.innerHTML='';
 unique.forEach(name=>{
  const option=document.createElement('option');
  option.value=name;
  option.textContent=name==='LifeSmart / Domótica'?'Domótica LifeSmart':name;
  option.selected=name===selected;
  rubro.appendChild(option);
 });

 familyBlock.innerHTML='';
 const label=document.createElement('label');
 label.setAttribute('for','quoteRubro');
 label.textContent='Rubro';
 rubro.id='quoteRubro';
 rubro.required=true;
 const help=document.createElement('small');
 help.className='quote-rubro-help';
 help.textContent='Se obtiene de las categorías del catálogo de productos.';
 familyBlock.append(label,rubro,help);
 originalField?.remove();

 const template=document.querySelector('select[name="quote_template_family"]');
 const templateLabel=template?.closest('p')?.querySelector('label');
 if(templateLabel)templateLabel.textContent='Plantilla del PDF';
 function keepLegacyFamilyValid(){
  const value=String(template?.value||'').toLowerCase();
  const matching=familyInputs.find(input=>input.value===value);
  if(matching)matching.checked=true;
 }
 template?.addEventListener('change',keepLegacyFamilyValid);
 form?.addEventListener('submit',keepLegacyFamilyValid);
 keepLegacyFamilyValid();
})();
</script>
HTML;

$inject=str_replace('__CATEGORY_NAMES__',$categoryNamesJson,$inject);
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
