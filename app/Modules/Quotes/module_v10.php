<?php
declare(strict_types=1);

$a=$_GET['a']??'new_quote';
$root=dirname(__DIR__,3);
$legacyLabor=null;

if($a==='edit_quote'){
    try{
        $cfg=require $root.'/config.php';
        $dbLegacy=new PDO(
            'mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',
            $cfg['db_user'],
            $cfg['db_pass'],
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
        );
        $qid=(int)($_GET['id']??0);
        if($qid>0){
            $s=$dbLegacy->prepare('SELECT labor_amount,labor_description,labor_tax_mode,labor_vat_rate,materials_amount,materials_tax_mode,materials_vat_rate,total,subtotal FROM quotes WHERE id=?');
            $s->execute([$qid]);
            $legacyLabor=$s->fetch()?:null;
            if($legacyLabor){
                $amount=(float)($legacyLabor['labor_amount']??0);
                if($amount<=0){
                    $materials=(float)($legacyLabor['materials_amount']??0);
                    $matMode=(string)($legacyLabor['materials_tax_mode']??'sin_iva');
                    $matVat=(float)($legacyLabor['materials_vat_rate']??21);
                    $matTotal=$matMode==='mas_iva'?$materials*(1+$matVat/100):$materials;
                    $total=(float)($legacyLabor['total']??0);
                    $inferred=round($total-$matTotal,2);
                    if($inferred>0)$legacyLabor['labor_amount']=$inferred;
                }
            }
        }
    }catch(Throwable $e){$legacyLabor=null;}
}

ob_start();
require __DIR__.'/module_v9.php';
$html=ob_get_clean();

if($a==='edit_quote' && $legacyLabor){
    $legacyJson=json_encode($legacyLabor,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $inject=<<<HTML
<script>
(function(){
  const legacy=$legacyJson;
  const amount=Number(legacy?.labor_amount||0);
  if(!(amount>0)) return;
  const blocks=document.getElementById('blocks');
  if(!blocks) return;
  let b=blocks.querySelector('.labor-block');
  if(!b && typeof addLaborBlock==='function'){
    addLaborBlock();
    b=blocks.querySelector('.labor-block:last-of-type') || blocks.querySelector('.labor-block');
  }
  if(!b) return;
  const amountInput=b.querySelector('.labor-amount');
  if(amountInput && Number(amountInput.value||0)<=0) amountInput.value=amount.toFixed(2);
  const desc=b.querySelector('.labor-description');
  if(desc && (!desc.value || desc.value==='Configuración, montaje y diseño de escenas')) desc.value=legacy.labor_description || 'Configuración, montaje y diseño de escenas';
  const tax=b.querySelector('.labor-tax');
  if(tax && legacy.labor_tax_mode) tax.value=legacy.labor_tax_mode;
  const vat=b.querySelector('.labor-vat');
  if(vat && legacy.labor_vat_rate!==undefined && legacy.labor_vat_rate!==null) vat.value=legacy.labor_vat_rate;
  const preset=b.querySelector('.labor-preset');
  if(preset && desc){
    const known=['Configuración, montaje y diseño de escenas','Cableado, montaje y configuración'];
    preset.value=known.includes(desc.value)?desc.value:'__manual__';
  }
  if(typeof renumberBlocks==='function') renumberBlocks();
  if(typeof calcTotals==='function') calcTotals();
})();
</script>
HTML;
    if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
}

echo $html;
