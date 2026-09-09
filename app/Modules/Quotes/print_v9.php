<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$id=(int)($_GET['id']??0);
$email='';$whatsapp='';$client='';$project='';$version=1;
try{
    $cfg=require $root.'/config.php';
    $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $s=$db->prepare('SELECT q.version_no,p.project_number,c.business_name,c.contact_name,c.email,c.whatsapp FROM quotes q JOIN projects p ON p.id=q.project_id JOIN clients c ON c.id=p.client_id WHERE q.id=?');
    $s->execute([$id]);$r=$s->fetch()?:[];
    $email=trim((string)($r['email']??''));
    $whatsapp=preg_replace('/\D+/','',(string)($r['whatsapp']??''))??'';
    $client=trim((string)($r['contact_name']??''))?:trim((string)($r['business_name']??''));
    $project=(string)($r['project_number']??'');$version=(int)($r['version_no']??1);
}catch(Throwable $e){}

ob_start();require __DIR__.'/print_v8.php';$html=ob_get_clean();
$emailJ=json_encode($email,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$waJ=json_encode($whatsapp,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$clientJ=json_encode($client,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$projectJ=json_encode($project,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$versionJ=json_encode($version);

// print_v7 agregaba handlers que esperaban la generación del PDF. Si ésta fallaba o el navegador
// bloqueaba la ventana luego del await/setTimeout, mail/WhatsApp no se abrían. Reasignamos al final
// handlers directos, dentro del gesto del click, para que siempre funcionen.
$inject=<<<HTML
<script>
(function(){
 const email=$emailJ, wa=$waJ, client=$clientJ, project=$projectJ, version=$versionJ;
 const subject='Presupuesto Punto Domótica · '+project+' · v'+version;
 const text='Hola'+(client?' '+client:'')+', te enviamos el presupuesto correspondiente al proyecto '+project+'.';
 const mail=document.getElementById('mailQuoteBtn');
 const wab=document.getElementById('waQuoteBtn');
 if(mail){
   mail.onclick=function(ev){
     ev.preventDefault();
     if(!email){alert('El cliente no tiene un correo cargado.');return;}
     const url='mailto:'+email+'?subject='+encodeURIComponent(subject)+'&body='+encodeURIComponent(text+'\n\nAdjuntamos el presupuesto en PDF.');
     window.location.href=url;
   };
 }
 if(wab){
   wab.onclick=function(ev){
     ev.preventDefault();
     if(!wa){alert('El cliente no tiene un WhatsApp cargado.');return;}
     let phone=wa;
     if(phone.startsWith('0'))phone=phone.replace(/^0+/, '');
     if(phone.startsWith('15'))phone=phone.substring(2);
     // Para números argentinos guardados sin código de país, completa 54.
     if(!phone.startsWith('54') && phone.length>=10)phone='54'+phone;
     const url='https://wa.me/'+phone+'?text='+encodeURIComponent(text+'\n\nTe envío el presupuesto en PDF.');
     const w=window.open(url,'_blank');
     if(!w)window.location.href=url;
   };
 }
})();
</script>
HTML;
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
