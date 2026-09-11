<?php
declare(strict_types=1);

$a=$_GET['a']??'new_quote';
$root=dirname(__DIR__,3);
$desiredTemplate=(string)($_POST['quote_template_family']??'');
$editTemplate='';

// module_v12 todavía valida las tres familias históricas. Para mantener intacta esa lógica,
// guardamos normalmente y, al finalizar la petición, persistimos la nueva plantilla Electricidad.
if(in_array($a,['save_quote','update_quote'],true) && $desiredTemplate==='electricidad'){
    $projectId=(int)($_POST['project_id']??0);
    $quoteId=(int)($_POST['quote_id']??0);
    $_POST['quote_template_family']='lifesmart';
    $families=$_POST['quote_families']??[];
    if(!is_array($families))$families=[$families];
    if(!in_array('lifesmart',$families,true))$families[]='lifesmart';
    $_POST['quote_families']=$families;
    register_shutdown_function(function() use($root,$a,$projectId,$quoteId):void{
        try{
            $cfg=require $root.'/config.php';
            $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
            ]);
            $id=$quoteId;
            if($a==='save_quote' && $projectId>0){
                $s=$db->prepare('SELECT id FROM quotes WHERE project_id=? ORDER BY id DESC LIMIT 1');
                $s->execute([$projectId]);
                $id=(int)$s->fetchColumn();
            }
            if($id>0)$db->prepare("UPDATE quotes SET quote_template_family='electricidad' WHERE id=?")->execute([$id]);
        }catch(Throwable $e){}
    });
}

if($a==='edit_quote'){
    try{
        $cfg=require $root.'/config.php';
        $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $s=$db->prepare('SELECT quote_template_family FROM quotes WHERE id=?');
        $s->execute([(int)($_GET['id']??0)]);
        $editTemplate=(string)($s->fetchColumn()?:'');
    }catch(Throwable $e){}
}

ob_start();
require __DIR__.'/module_v13.php';
$html=ob_get_clean();
$templateJson=json_encode($editTemplate,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$inject=<<<HTML
<script>
(function(){
  const sel=document.querySelector('select[name="quote_template_family"]');
  if(!sel)return;
  if(![...sel.options].some(o=>o.value==='electricidad')){
    const o=document.createElement('option');o.value='electricidad';o.textContent='Electricidad';sel.appendChild(o);
  }
  const current=$templateJson;
  if(current==='electricidad')sel.value='electricidad';
})();
</script>
HTML;
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
