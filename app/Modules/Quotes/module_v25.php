<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$a=$_GET['a']??'new_quote';
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
$isNewJson=json_encode($a==='new_quote');

$inject=<<<HTML
<script>
(function(){
 const isNew=$isNewJson;
 const version=document.querySelector('input[name="version_no"]');
 if(version){if(isNew)version.value='1';version.readOnly=true;version.title='La versión se administra automáticamente';}
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
})();
</script>
HTML;
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
