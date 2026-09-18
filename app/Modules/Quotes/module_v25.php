<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$a=$_GET['a']??'new_quote';

try{
    require_once $root.'/app/Services/ProductCategoryCleanup.php';
    $cleanupCfg=require $root.'/config.php';
    $cleanupDb=new PDO('mysql:host='.$cleanupCfg['db_host'].';dbname='.$cleanupCfg['db_name'].';charset=utf8mb4',$cleanupCfg['db_user'],$cleanupCfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    consolidateLifeSmartDomoticsCategory($cleanupDb);
}catch(Throwable $e){}

/*
 * Un presupuesto creado desde "Nuevo presupuesto" siempre inicia una serie
 * independiente en v1. Las versiones siguientes se generan únicamente al
 * duplicar un presupuesto existente.
 */
if($a==='save_quote'){
    $_POST['version_no']='1';
}

if($a==='edit_quote'){
    try{
        $cfg=require $root.'/config.php';
        $db25=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $s=$db25->prepare('SELECT status FROM quotes WHERE id=?');
        $s->execute([(int)($_GET['id']??0)]);
        $status=(string)($s->fetchColumn()?:'');
        if($status!==''&&$status!=='borrador'){
            if(session_status()!==PHP_SESSION_ACTIVE)session_start();
            $_SESSION['msg']='Los materiales de este presupuesto están bloqueados porque ya fue enviado. Podés verlo y cambiar su estado.';
            header('Location:index.php?a=quote_state_edit&id='.(int)($_GET['id']??0));
            exit;
        }
    }catch(Throwable $e){}
}

ob_start();
require __DIR__.'/module_v24.php';
$html=ob_get_clean();

/*
 * Corregimos también el HTML generado por los módulos anteriores. Así el
 * formulario ya llega al navegador mostrando v1, aun antes de ejecutar JS.
 */
if($a==='new_quote'){
    $html=preg_replace_callback(
        '/<input\b[^>]*\bname\s*=\s*(["\'])version_no\1[^>]*>/i',
        static function(array $match):string{
            $tag=$match[0];
            if(preg_match('/\bvalue\s*=\s*(["\'])[^"\']*\1/i',$tag)){
                $tag=preg_replace('/\bvalue\s*=\s*(["\'])[^"\']*\1/i','value="1"',$tag,1)??$tag;
            }else{
                $tag=preg_replace('/\s*\/?>$/',' value="1">',$tag)??$tag;
            }
            if(!preg_match('/\breadonly\b/i',$tag)){
                $tag=preg_replace('/\s*\/?>$/',' readonly>',$tag)??$tag;
            }
            return $tag;
        },
        $html,
        1
    )??$html;
}

$isNewJson=json_encode($a==='new_quote');
$editingQuoteId=$a==='edit_quote'?(int)($_GET['id']??0):0;
$editingQuoteIdJson=json_encode($editingQuoteId);
$savedTotals=null;
if($editingQuoteId>0){
    try{
        $totalsCfg=require $root.'/config.php';
        $totalsDb=new PDO('mysql:host='.$totalsCfg['db_host'].';dbname='.$totalsCfg['db_name'].';charset=utf8mb4',$totalsCfg['db_user'],$totalsCfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $totalsStmt=$totalsDb->prepare('SELECT materials_amount,materials_tax_mode,materials_vat_rate,labor_amount FROM quotes WHERE id=?');
        $totalsStmt->execute([$editingQuoteId]);
        if($totalsRow=$totalsStmt->fetch()){
            $materials=(float)$totalsRow['materials_amount'];
            $materialsTotal=($totalsRow['materials_tax_mode']??'sin_iva')==='mas_iva'?round($materials*(1+(float)$totalsRow['materials_vat_rate']/100),2):$materials;
            $laborRaw=0.0;$laborTotal=0.0;
            $laborStmt=$totalsDb->prepare('SELECT amount,tax_mode,vat_rate FROM quote_labor_items WHERE quote_id=? ORDER BY id');
            $laborStmt->execute([$editingQuoteId]);
            $laborRows=$laborStmt->fetchAll();
            if($laborRows){
                foreach($laborRows as $laborRow){
                    $amount=(float)$laborRow['amount'];$laborRaw+=$amount;
                    $laborTotal+=($laborRow['tax_mode']??'sin_iva')==='mas_iva'?round($amount*(1+(float)$laborRow['vat_rate']/100),2):$amount;
                }
            }else{
                $laborRaw=(float)$totalsRow['labor_amount'];$laborTotal=$laborRaw;
            }
            $grandTotal=round($materialsTotal+$laborTotal,2);
            $subtotal=round($materials+$laborRaw,2);
            $reconcile=$totalsDb->prepare('UPDATE quotes SET labor_amount=?,subtotal=?,total=? WHERE id=?');
            $reconcile->execute([$laborRaw,$subtotal,$grandTotal,$editingQuoteId]);
            $savedTotals=['materials'=>$materialsTotal,'labor'=>$laborTotal,'grand'=>$grandTotal];
        }
    }catch(Throwable $e){}
}
$savedTotalsJson=json_encode($savedTotals,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

$inject=<<<HTML
<script>
(function(){
 const isNew=$isNewJson;
 const version=document.querySelector('input[name="version_no"]');
 if(version){if(isNew){version.value='1';version.setAttribute('value','1');}version.readOnly=true;version.title='La versión se administra automáticamente';}
 const status=document.querySelector('select[name="status"]');
 if(status){
  [...status.options].forEach(option=>{
   if(['enviado','aprobado_inicial','aprobado_definitivo','final'].includes(option.value))option.remove();
   else if(option.value==='rechazado')option.textContent='Rechazado';
  });
  if(isNew)status.value='borrador';
 }
 const name=document.querySelector('input[name="proposal_name"]');
 if(name){name.required=true;name.placeholder='Ej.: Departamento piso 1 · Unidad A';const label=name.closest('p')?.querySelector('label');if(label)label.innerHTML='Nombre del presupuesto';}
 const quoteId=$editingQuoteIdJson;
 const savedTotals=$savedTotalsJson;
 const form=document.getElementById('quoteForm');
 if(form&&savedTotals){
  let financialDirty=false;
  const financialSelector='.qty,.unit-price,.item-discount,.labor-amount,.labor-tax,.labor-vat,#materialsTax,#materialsVat,.discount-value,.discount-scope,.discount-type';
  const markDirty=event=>{if(event.isTrusted&&event.target?.matches?.(financialSelector))financialDirty=true;};
  form.addEventListener('input',markDirty,true);form.addEventListener('change',markDirty,true);
  const showSavedTotals=()=>{
   if(financialDirty)return;
   const materials=document.getElementById('materialsTotal'),labor=document.getElementById('laborTotal'),grand=document.getElementById('grandTotal');
   const formatted=value=>'US$ '+Math.round(Number(value||0)).toLocaleString('es-AR');
   if(materials)materials.textContent=formatted(savedTotals.materials);
   if(labor)labor.textContent=formatted(savedTotals.labor);
   if(grand)grand.textContent=formatted(savedTotals.grand);
  };
  setTimeout(showSavedTotals,100);setTimeout(showSavedTotals,700);
 }

 if(form&&quoteId>0&&!form.querySelector('.quote-pdf-action')){
  const save=[...form.querySelectorAll('button')].find(button=>button.type==='submit'||(!button.type&&button.textContent.includes('Guardar')));
  if(save){
   save.textContent='Guardar cambios';
   const pdf=document.createElement('button');
   pdf.type='submit';
   pdf.name='after_save';
   pdf.value='pdf';
   pdf.className='btn dark quote-pdf-action';
   pdf.textContent='Generar PDF / enviar';
   pdf.title='Guarda los cambios actuales y abre el presupuesto para generar o enviar el PDF';
   pdf.style.marginLeft='10px';
   save.insertAdjacentElement('afterend',pdf);
  }
 }
})();
</script>
HTML;
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
