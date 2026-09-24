<?php
declare(strict_types=1);

$a=$_GET['a']??'new_quote';
$root=dirname(__DIR__,3);
try{
    require_once $root.'/app/Services/AdiProductImport.php';
    $nameCfg=require $root.'/config.php';
    $nameDb=new PDO('mysql:host='.$nameCfg['db_host'].';dbname='.$nameCfg['db_name'].';charset=utf8mb4',$nameCfg['db_user'],$nameCfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    normalizeProductCatalogFields($nameDb);
    normalizeUnifiedProductNamesV2($nameDb);
}catch(Throwable $e){}


function sanitizeQuoteNotesHtml(string $html):string
{
    $html=preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is','',$html)??'';
    $html=strip_tags($html,'<p><br><strong><b><em><i><u><ul><ol><li><h3><h4>');
    $html=preg_replace_callback('/<\/?([a-z0-9]+)\b[^>]*>/i',static function(array $match):string{
        $tag=strtolower($match[1]);
        $closing=str_starts_with($match[0],'</');
        return $tag==='br'?'<br>':'<'.($closing?'/':'').$tag.'>';
    },$html)??'';
    return trim($html);
}

if(in_array($a,['save_quote','update_quote'],true)&&array_key_exists('notes',$_POST)){
    $_POST['notes']=sanitizeQuoteNotesHtml((string)$_POST['notes']);
}

ob_start();
require __DIR__.'/module_v25.php';
$html=ob_get_clean();

$inject=<<<'HTML'
<style>
.quote-rich-editor{border:1px solid #cbd1d7;border-radius:9px;background:#fff;overflow:hidden}
.quote-rich-toolbar{display:flex;flex-wrap:wrap;gap:5px;padding:8px;background:#f4f6f8;border-bottom:1px solid #dfe3e8}
.quote-rich-toolbar button{min-width:34px;height:32px;padding:4px 9px;border:1px solid #cbd1d7;border-radius:6px;background:#fff;color:#263445;font:700 14px/1 system-ui;cursor:pointer}
.quote-rich-toolbar button:hover{border-color:#ff6702;color:#b84600;background:#fff7f1}
.quote-rich-content{min-height:155px;padding:12px 14px;outline:none;line-height:1.5}
.quote-rich-content:empty:before{content:attr(data-placeholder);color:#8a949f}
.quote-rich-content p{margin:0 0 8px}.quote-rich-content ul,.quote-rich-content ol{margin:6px 0 10px;padding-left:28px}
</style>
<script>
(function(){
 const textarea=document.querySelector('textarea[name="notes"]');
 if(!textarea||document.querySelector('.quote-rich-editor'))return;
 textarea.hidden=true;
 const editor=document.createElement('div');editor.className='quote-rich-editor';
 editor.innerHTML='<div class="quote-rich-toolbar" role="toolbar" aria-label="Formato de notas"><button type="button" data-command="bold" title="Negrita"><b>B</b></button><button type="button" data-command="italic" title="Cursiva"><i>I</i></button><button type="button" data-command="underline" title="Subrayado"><u>U</u></button><button type="button" data-command="formatBlock" data-value="h3" title="Título">Título</button><button type="button" data-command="insertUnorderedList" title="Lista con viñetas">• Lista</button><button type="button" data-command="insertOrderedList" title="Lista numerada">1. Lista</button><button type="button" data-command="removeFormat" title="Quitar formato">Limpiar formato</button></div><div class="quote-rich-content" contenteditable="true" data-placeholder="Escribí aquí las condiciones, alcance y forma de pago..."></div>';
 textarea.insertAdjacentElement('afterend',editor);
 const content=editor.querySelector('.quote-rich-content');
 const original=textarea.value.trim();
 if(/<(p|br|strong|b|em|i|u|ul|ol|li|h3|h4)\b/i.test(original))content.innerHTML=original;
 else content.innerHTML=original.replace(/[&<>]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[char])).replace(/\r?\n/g,'<br>');
 editor.querySelectorAll('[data-command]').forEach(button=>button.addEventListener('click',()=>{
  content.focus();document.execCommand(button.dataset.command,false,button.dataset.value||null);
 }));
 const sync=()=>{textarea.value=content.innerHTML.trim();};
 content.addEventListener('input',sync);
 textarea.form?.addEventListener('submit',sync,true);
 sync();
})();
</script>
HTML;

if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
