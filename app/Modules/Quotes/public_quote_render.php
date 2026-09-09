<?php
declare(strict_types=1);
$id=(int)($_GET['id']??0);
if($id<1 || (int)($_SESSION['public_quote_access_id']??0)!==$id){http_response_code(403);exit('Acceso no autorizado.');}
$_SESSION['user']=['role'=>'public_quote'];
ob_start();require __DIR__.'/print_v8.php';$html=ob_get_clean();
// Mantenemos la sesión pública durante la navegación para que los endpoints de prólogo/pago
// puedan validar que este visitante llegó mediante un token seguro.

$html=preg_replace('/<a class="light" href="\?a=quotes">.*?<\/a>/s','',$html)??$html;
$html=preg_replace('/<a class="dark" href="\?a=edit_quote&id=\d+">Editar<\/a>/','',$html)??$html;
$html=preg_replace('/<button id="mailQuoteBtn"[^>]*>Enviar por mail<\/button>/','',$html)??$html;
$html=preg_replace('/<button id="waQuoteBtn"[^>]*>Enviar por WhatsApp<\/button>/','',$html)??$html;
$html=preg_replace('/<a id="mailQuoteBtn"[^>]*>Enviar por mail<\/a>/','',$html)??$html;
$html=preg_replace('/<a id="waQuoteBtn"[^>]*>Enviar por WhatsApp<\/a>/','',$html)??$html;
$html=str_replace('<div class="screen-note">','<div class="screen-note" style="display:none">',$html);
$html=str_replace('>Guardar PDF</button>','>Descargar PDF completo</button>',$html);
$html=str_replace('>Generar PDF</button>','>Descargar PDF completo</button>',$html);

// En la URL pública no existe ?a=quote_pdf_asset. Apuntamos los assets comerciales al endpoint
// público y mostramos desde la vista previa el documento completo: prólogo + presupuesto + pago.
$html=str_replace("prologueUrl='?a=quote_pdf_asset&type=prologo&family='+encodeURIComponent(quoteFamily),paymentUrl='?a=quote_pdf_asset&type=pago&family='+encodeURIComponent(quoteFamily)","prologueUrl='public-pdf-asset.php?type=prologo&family='+encodeURIComponent(quoteFamily),paymentUrl='public-pdf-asset.php?type=pago&family='+encodeURIComponent(quoteFamily)",$html);

$preview=<<<'HTML'
<div id="clientCommercialPreview" class="client-commercial-preview">
  <section class="commercial-part"><div class="commercial-label">Prólogo</div><iframe id="prologuePreview" title="Prólogo del presupuesto"></iframe></section>
  <div id="quotePreviewAnchor"></div>
  <section class="commercial-part"><div class="commercial-label">Forma de pago</div><iframe id="paymentPreview" title="Forma de pago"></iframe></section>
</div>
<script>
(function(){
 const sheet=document.getElementById('quoteSheet'), anchor=document.getElementById('quotePreviewAnchor');
 if(sheet&&anchor)anchor.replaceWith(sheet);
 const fam=(typeof quoteFamily!=='undefined'?quoteFamily:'');
 const pro=document.getElementById('prologuePreview'), pay=document.getElementById('paymentPreview');
 if(fam){
   if(pro)pro.src='public-pdf-asset.php?type=prologo&family='+encodeURIComponent(fam)+'#toolbar=0&navpanes=0';
   if(pay)pay.src='public-pdf-asset.php?type=pago&family='+encodeURIComponent(fam)+'#toolbar=0&navpanes=0';
 }
})();
</script>
HTML;
$html=str_replace('</body>',$preview.'</body>',$html);
$html=str_replace('</head>','<style>.toolbar{justify-content:flex-end}.toolbar #generatePdfBtn{background:#ff6702}.client-commercial-preview{width:210mm;margin:0 auto 30px}.client-commercial-preview>.sheet{margin:0 auto 18px}.commercial-part{width:210mm;margin:0 auto 18px;background:#fff;box-shadow:0 12px 34px #0002}.commercial-part iframe{display:block;width:210mm;height:297mm;border:0;background:#fff}.commercial-label{display:none}@media(max-width:850px){.client-commercial-preview,.commercial-part,.commercial-part iframe{width:100%}.commercial-part iframe{height:75vh}}</style></head>',$html);
echo $html;
