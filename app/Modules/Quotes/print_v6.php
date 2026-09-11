<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$id=(int)($_GET['id']??0);
$clientEmail='';$clientWhatsapp='';$projectNumber='';$versionNo=1;$clientName='';
if($id>0){
    try{
        $cfg=require $root.'/config.php';
        $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $s=$db->prepare('SELECT q.version_no,p.project_number,c.business_name,c.contact_name,c.email,c.whatsapp FROM quotes q JOIN projects p ON p.id=q.project_id JOIN clients c ON c.id=p.client_id WHERE q.id=?');
        $s->execute([$id]);$contact=$s->fetch()?:[];
        $clientEmail=trim((string)($contact['email']??''));
        $clientWhatsapp=preg_replace('/\D+/','',(string)($contact['whatsapp']??''))??'';
        $projectNumber=(string)($contact['project_number']??'');$versionNo=(int)($contact['version_no']??1);
        $clientName=trim((string)($contact['contact_name']??''))?:trim((string)($contact['business_name']??''));
    }catch(Throwable $e){}
}

ob_start();require __DIR__.'/print_v5.php';$html=ob_get_clean();
$emailJson=json_encode($clientEmail,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$waJson=json_encode($clientWhatsapp,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$nameJson=json_encode($clientName,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$projectJson=json_encode($projectNumber,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$versionJson=json_encode($versionNo);
$inject=<<<HTML
<style>
.toolbar{flex-wrap:wrap}.toolbar .mail-btn{background:#3157a4}.toolbar .wa-btn{background:#168c4d}.share-help{width:210mm;margin:-8px auto 12px;font:12px Aptos,"Segoe UI",Arial,sans-serif;color:#50555b}
@media(max-width:900px){.toolbar,.share-help{width:auto;margin:10px}.toolbar>*{flex:1 1 auto;text-align:center}}
</style>
<script>
(function(){
 const email=$emailJson, whatsapp=$waJson, clientName=$nameJson, project=$projectJson, version=$versionJson;
 const toolbar=document.querySelector('.toolbar');if(!toolbar)return;
 const pdfBtn=document.getElementById('generatePdfBtn');if(pdfBtn)pdfBtn.textContent='Guardar PDF';
 const mail=document.createElement('button');mail.type='button';mail.className='mail-btn';mail.textContent='Enviar por mail';
 const wa=document.createElement('button');wa.type='button';wa.className='wa-btn';wa.textContent='Enviar por WhatsApp';
 toolbar.append(mail,wa);
 const subject='Presupuesto Punto Domótica · '+project+' · v'+version;
 const text='Hola'+(clientName?' '+clientName:'')+', te enviamos el presupuesto correspondiente al proyecto '+project+'.';
 async function preparePdf(){
   /* El generador actual descarga el PDF final con prólogo + presupuesto + forma de pago. */
   await generatePdf();
 }
 mail.addEventListener('click',async()=>{
   if(!email){alert('El cliente no tiene un correo cargado. Editá el cliente y agregá su email.');return;}
   await preparePdf();
   setTimeout(()=>{location.href='mailto:'+encodeURIComponent(email)+'?subject='+encodeURIComponent(subject)+'&body='+encodeURIComponent(text+'\n\nAdjuntá el PDF que se acaba de guardar.');},350);
 });
 wa.addEventListener('click',async()=>{
   if(!whatsapp){alert('El cliente no tiene un WhatsApp cargado. Editá el cliente y agregá su número.');return;}
   await preparePdf();
   const msg=text+'\n\nTe adjunto el presupuesto en PDF.';
   setTimeout(()=>window.open('https://wa.me/'+whatsapp+'?text='+encodeURIComponent(msg),'_blank'),350);
 });
 const help=document.createElement('div');help.className='share-help';help.innerHTML='<b>Envío:</b> el sistema prepara y guarda el PDF final. Al abrir Mail o WhatsApp, adjuntá ese PDF desde Descargas. Los navegadores no permiten adjuntar automáticamente un archivo a WhatsApp o a un correo mediante un enlace.';
 toolbar.insertAdjacentElement('afterend',help);
})();
</script>
HTML;
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
