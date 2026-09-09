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

ob_start();require __DIR__.'/print_v5.php';$html=ob_get_clean();
$emailJ=json_encode($email,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$waJ=json_encode($whatsapp,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$clientJ=json_encode($client,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$projectJ=json_encode($project,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$versionJ=json_encode($version);

// Los botones se insertan como HTML real para que siempre sean visibles aunque falle otro JS de la página.
$html=str_replace('<button id="generatePdfBtn" type="button" onclick="generatePdf()">Generar PDF</button>',
'<button id="generatePdfBtn" type="button" onclick="generatePdf()">Guardar PDF</button><button id="mailQuoteBtn" class="mail-btn" type="button">Enviar por mail</button><button id="waQuoteBtn" class="wa-btn" type="button">Enviar por WhatsApp</button>',$html);

$inject=<<<HTML
<style>
.toolbar{flex-wrap:wrap}.toolbar .mail-btn{background:#3157a4}.toolbar .wa-btn{background:#168c4d}
@media(max-width:900px){.toolbar{width:auto;margin:10px}.toolbar>*{flex:1 1 auto;text-align:center}}
</style>
<script>
(function(){
 const email=$emailJ,wa=$waJ,client=$clientJ,project=$projectJ,version=$versionJ;
 const subject='Presupuesto Punto Domótica · '+project+' · v'+version;
 const text='Hola'+(client?' '+client:'')+', te enviamos el presupuesto correspondiente al proyecto '+project+'.';
 const mail=document.getElementById('mailQuoteBtn'),wab=document.getElementById('waQuoteBtn');
 if(mail)mail.onclick=async()=>{if(!email){alert('El cliente no tiene un correo cargado.');return;}if(typeof generatePdf==='function')await generatePdf();setTimeout(()=>{location.href='mailto:'+encodeURIComponent(email)+'?subject='+encodeURIComponent(subject)+'&body='+encodeURIComponent(text+'\n\nAdjuntá el PDF guardado.');},350);};
 if(wab)wab.onclick=async()=>{if(!wa){alert('El cliente no tiene un WhatsApp cargado.');return;}if(typeof generatePdf==='function')await generatePdf();setTimeout(()=>window.open('https://wa.me/'+wa+'?text='+encodeURIComponent(text+'\n\nTe adjunto el presupuesto en PDF.'),'_blank'),350);};
})();
</script>
HTML;
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
