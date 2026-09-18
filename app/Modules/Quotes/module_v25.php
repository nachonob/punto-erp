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
 const form=document.getElementById('quoteForm');
 if(form&&quoteId>0&&!form.querySelector('.quote-pdf-action')){
  const save=[...form.querySelectorAll('button')].find(button=>button.type==='submit'||(!button.type&&button.textContent.includes('Guardar')));
  if(save){
   save.textContent='Guardar cambios';
   const pdf=document.createElement('a');
   pdf.className='btn dark quote-pdf-action';
   pdf.href='index.php?a=quote_view&id='+quoteId;
   pdf.textContent='Generar PDF / enviar';
   pdf.style.marginLeft='10px';
   save.insertAdjacentElement('afterend',pdf);
  }
 }
})();
</script>
HTML;
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
