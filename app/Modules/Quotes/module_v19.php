<?php
declare(strict_types=1);

$a=$_GET['a']??'new_quote';
$root=dirname(__DIR__,3);
$quoteId=(int)($_POST['quote_id']??$_GET['id']??0);
$discountDescriptions=[];

if($a==='edit_quote' && $quoteId>0){
    try{
        $cfg=require $root.'/config.php';
        $db=new PDO(
            'mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',
            $cfg['db_user'],$cfg['db_pass'],
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
        );
        $s=$db->prepare('SELECT description FROM quote_discounts WHERE quote_id=? ORDER BY sort_order,id');
        $s->execute([$quoteId]);
        $discountDescriptions=array_map(static fn($v)=>(string)($v??''),$s->fetchAll(PDO::FETCH_COLUMN));
    }catch(Throwable $e){
        $discountDescriptions=[];
    }
}

ob_start();
require __DIR__.'/module_v18.php';
$html=ob_get_clean();

$descriptionsJson=json_encode($discountDescriptions,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$inject=<<<HTML
<script>
(function(){
  const savedDescriptions=$descriptionsJson;
  function restoreDiscountConcepts(){
    const rows=document.querySelectorAll('#discountRows .discount-row');
    if(!rows.length)return false;
    rows.forEach((row,index)=>{
      if(index>=savedDescriptions.length)return;
      const value=savedDescriptions[index]||'';
      const hidden=row.querySelector('input[name$="[description]"]');
      const visible=row.querySelector('.discount-concept input');
      if(hidden)hidden.value=value;
      if(visible)visible.value=value;
    });
    return true;
  }
  if(!restoreDiscountConcepts()){
    const target=document.getElementById('quoteForm')||document.body;
    const observer=new MutationObserver(()=>{if(restoreDiscountConcepts())observer.disconnect();});
    observer.observe(target,{childList:true,subtree:true});
  }
})();
</script>
HTML;

if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
