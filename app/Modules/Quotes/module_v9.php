<?php
declare(strict_types=1);

$a=$_GET['a']??'new_quote';
$root=dirname(__DIR__,3);
$legacyLabor=null;

// Para presupuestos existentes creados antes de los bloques múltiples,
// conservar y recuperar la mano de obra histórica guardada en quotes.
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
            $s=$dbLegacy->prepare('SELECT labor_amount,labor_description,labor_tax_mode,labor_vat_rate FROM quotes WHERE id=?');
            $s->execute([$qid]);
            $legacyLabor=$s->fetch()?:null;
        }
    }catch(Throwable $e){
        $legacyLabor=null;
    }
}

ob_start();
require __DIR__.'/module_v8.php';
$html=ob_get_clean();

// module_v8 ya crea Materiales + Mano de obra en presupuestos nuevos.
// En edición, este refuerzo garantiza que siempre exista al menos un bloque de cada tipo
// y recupera la mano de obra histórica cuando todavía no había quote_labor_items.
if(in_array($a,['new_quote','edit_quote'],true)){
    $legacyJson=json_encode($legacyLabor,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $inject=<<<HTML
<script>
(function(){
  const legacyLabor=$legacyJson;
  const blocks=document.getElementById('blocks');
  if(!blocks) return;

  if(!blocks.querySelector('.material-block') && typeof addMaterialBlock==='function'){
    addMaterialBlock();
  }

  if(!blocks.querySelector('.labor-block') && typeof addLaborBlock==='function'){
    addLaborBlock();
    const laborBlocks=blocks.querySelectorAll('.labor-block');
    const b=laborBlocks[laborBlocks.length-1];
    if(b && legacyLabor){
      const title=b.querySelector('.labor-title');
      const desc=b.querySelector('.labor-description');
      const preset=b.querySelector('.labor-preset');
      const amount=b.querySelector('.labor-amount');
      const tax=b.querySelector('.labor-tax');
      const vat=b.querySelector('.labor-vat');
      if(title) title.value='Mano de obra';
      if(desc) desc.value=legacyLabor.labor_description || 'Configuración, montaje y diseño de escenas';
      if(amount) amount.value=Number(legacyLabor.labor_amount||0).toFixed(2);
      if(tax && legacyLabor.labor_tax_mode) tax.value=legacyLabor.labor_tax_mode;
      if(vat && legacyLabor.labor_vat_rate!==undefined && legacyLabor.labor_vat_rate!==null) vat.value=legacyLabor.labor_vat_rate;
      if(preset){
        const known=['Configuración, montaje y diseño de escenas','Cableado, montaje y configuración'];
        preset.value=known.includes(desc?.value||'')?(desc?.value||known[0]):'__manual__';
      }
    }
  }

  if(typeof renumberBlocks==='function') renumberBlocks();
  if(typeof calcTotals==='function') calcTotals();
})();
</script>
HTML;
    if(str_contains($html,'</body>')) $html=str_replace('</body>',$inject.'</body>',$html);
    else $html.=$inject;
}

echo $html;
