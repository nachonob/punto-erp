<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$cfg=require $root.'/config.php';
$db24=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
]);

$db24->exec("CREATE TABLE IF NOT EXISTS quote_rubros (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$column=$db24->query("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='quotes' AND COLUMN_NAME='quote_category'")->fetchColumn();
if($column==='enum'){
    $db24->exec("ALTER TABLE quotes MODIFY quote_category VARCHAR(150) NOT NULL DEFAULT 'General'");
}
$db24->exec("INSERT IGNORE INTO quote_rubros(name,active) VALUES
    ('General',1),('Domótica',1),('Redes',1),('Cámaras',1),('Alarma',1),('Audio',1),('Electricidad',1)");
$db24->exec("UPDATE quotes SET quote_category=CASE quote_category
    WHEN 'general' THEN 'General'
    WHEN 'domotica' THEN 'Domótica'
    WHEN 'redes' THEN 'Redes'
    WHEN 'camaras' THEN 'Cámaras'
    WHEN 'alarma' THEN 'Alarma'
    WHEN 'audio' THEN 'Audio'
    WHEN 'electricidad' THEN 'Electricidad'
    ELSE quote_category END
    WHERE quote_category IN ('general','domotica','redes','camaras','alarma','audio','electricidad')");

$a=$_GET['a']??'new_quote';
if(in_array($a,['save_quote','update_quote'],true)){
    if(session_status()!==PHP_SESSION_ACTIVE)session_start();
    $requestedRubro=trim((string)($_POST['quote_category']??''));
    $check=$db24->prepare('SELECT name FROM quote_rubros WHERE name=? AND active=1');
    $check->execute([$requestedRubro]);
    $validRubro=(string)($check->fetchColumn()?:'');
    if($validRubro===''){
        $_SESSION['msg']='No se pudo guardar: seleccioná un rubro válido.';
        $target=$a==='update_quote'?'?a=edit_quote&id='.(int)($_POST['quote_id']??0):'?a=new_quote';
        header('Location: '.$target);
        exit;
    }
    $_POST['quote_category']=$validRubro;
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
}

$currentRubro='';
if($a==='edit_quote'){
    $quoteId=(int)($_GET['id']??0);
    if($quoteId>0){
        $current=$db24->prepare('SELECT quote_category FROM quotes WHERE id=?');
        $current->execute([$quoteId]);
        $currentRubro=trim((string)($current->fetchColumn()?:''));
    }
}
$rubros=$db24->query('SELECT name FROM quote_rubros WHERE active=1 ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
$rubrosJson=json_encode(array_values(array_map('strval',$rubros)),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$currentRubroJson=json_encode($currentRubro,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

ob_start();
require __DIR__.'/module_v23.php';
$html=ob_get_clean();

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

 const serverValue=__CURRENT_RUBRO__;
 const currentValue=String(serverValue||rubro.value||'').trim();
 const rubros=__RUBROS__;
 const aliases={general:'General',domotica:'Domótica',redes:'Redes',camaras:'Cámaras',alarma:'Alarma',audio:'Audio',electricidad:'Electricidad'};
 const normalized=value=>String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();
 const desired=aliases[normalized(currentValue)]||currentValue;
 let selected=rubros.find(name=>normalized(name)===normalized(desired))||'';
 if(!selected&&currentValue)selected=desired;

 rubro.innerHTML='';
 rubros.forEach(name=>{
  const option=document.createElement('option');
  option.value=name;
  option.textContent=name;
  option.selected=name===selected;
  rubro.appendChild(option);
 });
 if(selected&&![...rubro.options].some(option=>option.value===selected)){
  const historical=document.createElement('option');
  historical.value=selected;
  historical.textContent=selected+' (histórico)';
  historical.selected=true;
  rubro.appendChild(historical);
 }

 familyBlock.innerHTML='';
 const label=document.createElement('label');
 label.setAttribute('for','quoteRubro');
 label.textContent='Rubro';
 rubro.id='quoteRubro';
 rubro.required=true;
 const help=document.createElement('small');
 help.className='quote-rubro-help';
 help.innerHTML='Clasificación comercial del presupuesto. <a href="?a=quote_rubros">Administrar rubros</a>.';
 familyBlock.append(label,rubro,help);
 originalField?.remove();

 const sidebarNav=document.querySelector('.sidebar-nav');
 if(sidebarNav&&!sidebarNav.querySelector('a[href="?a=quote_rubros"]')){
  const link=document.createElement('a');
  link.href='?a=quote_rubros';
  link.innerHTML='<span class="nav-icon">◫</span>Rubros de presupuestos';
  const quotesLink=sidebarNav.querySelector('a[href="?a=quotes"]');
  if(quotesLink)quotesLink.insertAdjacentElement('afterend',link);else sidebarNav.appendChild(link);
 }

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

$inject=str_replace(['__RUBROS__','__CURRENT_RUBRO__'],[$rubrosJson,$currentRubroJson],$inject);
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
