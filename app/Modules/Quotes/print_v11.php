<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);$id=(int)($_GET['id']??0);$materials=0.0;$hasNormalMaterials=false;
try{$cfg=require $root.'/config.php';$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$s=$db->prepare('SELECT materials_amount FROM quotes WHERE id=?');$s->execute([$id]);$materials=(float)($s->fetchColumn()?:0);$s=$db->prepare("SELECT COUNT(*) FROM quote_items WHERE quote_id=? AND sku NOT IN ('__CONCEPT__','__CONCEPT_TOTAL__')");$s->execute([$id]);$hasNormalMaterials=(int)$s->fetchColumn()>0;}catch(Throwable $e){}
ob_start();require __DIR__.'/print_v10.php';$html=ob_get_clean();
$materialsJ=json_encode($materials);$normalJ=json_encode($hasNormalMaterials);
$inject=<<<HTML
<style>
.equipment tr.concept-pdf-row td{padding-top:3mm;padding-bottom:3mm}.equipment tr.concept-pdf-row .concept-num{width:18mm;text-align:center;font-weight:800}.equipment tr.concept-pdf-row .concept-desc{font-size:10.8px}.equipment tr.concept-total-hidden{display:none!important}
</style>
<script>
(function(){
 const materials=Number($materialsJ||0),hasNormal=$normalJ;
 const table=document.querySelector('.equipment');if(!table)return;
 let n=0;
 [...table.querySelectorAll('tbody tr')].forEach(r=>{
   if(r.classList.contains('section-title')||r.classList.contains('labor-row'))return;
   const cells=r.querySelectorAll('td');if(cells.length<3)return;
   const sku=(cells[1]?.textContent||'').trim();
   if(sku==='__CONCEPT_TOTAL__'){r.classList.add('concept-total-hidden');return;}
   if(sku==='__CONCEPT__'){
     n++;const desc=(cells[2]?.textContent||'').trim();r.className='concept-pdf-row';
     r.innerHTML='<td class="concept-num">'+n+'</td><td class="concept-desc" colspan="5"></td>';
     r.querySelector('.concept-desc').textContent=desc;
   }
 });
 // Si el presupuesto no tiene materiales valorizados ni productos normales, no mostramos un bloque
 // "Materiales US$ 0,00". Las líneas concepto quedan como alcance descriptivo.
 if(materials<=0&&!hasNormal){
   [...table.querySelectorAll('tr.section-title')].forEach(r=>{if(/materiales/i.test(r.textContent))r.remove();});
   const summary=[...document.querySelectorAll('.summary .r')];summary.forEach(r=>{if(/materiales/i.test(r.textContent))r.remove();});
 }
})();
</script>
HTML;
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
