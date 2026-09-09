<?php
declare(strict_types=1);
$id=(int)($_GET['id']??0);
if($id<1 || (int)($_SESSION['public_quote_access_id']??0)!==$id){http_response_code(403);exit('Acceso no autorizado.');}
$_SESSION['user']=['role'=>'public_quote'];

$root=dirname(__DIR__,3);$family='';$hasPrologue=false;$hasPayment=false;$projectNumber='presupuesto';$versionNo=1;
try{
 $cfg=require $root.'/config.php';
 $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $s=$db->prepare('SELECT q.quote_template_family,q.version_no,p.project_number FROM quotes q JOIN projects p ON p.id=q.project_id WHERE q.id=?');$s->execute([$id]);$r=$s->fetch()?:[];
 $family=strtolower(trim((string)($r['quote_template_family']??'')));$projectNumber=(string)($r['project_number']??'presupuesto');$versionNo=(int)($r['version_no']??1);
 $hasPrologue=$family!==''&&is_file($root.'/storage/uploads/pdf_assets/prologo/'.$family.'.pdf');
 $hasPayment=$family!==''&&is_file($root.'/storage/uploads/pdf_assets/pago/'.$family.'.pdf');
}catch(Throwable $e){}

ob_start();require __DIR__.'/print_v11.php';$html=ob_get_clean();
$html=preg_replace('/<a class="light" href="\?a=quotes">.*?<\/a>/s','',$html)??$html;
$html=preg_replace('/<a class="dark" href="\?a=edit_quote&id=\d+">Editar<\/a>/','',$html)??$html;
$html=preg_replace('/<button id="mailQuoteBtn"[^>]*>Enviar por mail<\/button>/','',$html)??$html;
$html=preg_replace('/<button id="waQuoteBtn"[^>]*>Enviar por WhatsApp<\/button>/','',$html)??$html;
$html=preg_replace('/<a id="mailQuoteBtn"[^>]*>Enviar por mail<\/a>/','',$html)??$html;
$html=preg_replace('/<a id="waQuoteBtn"[^>]*>Enviar por WhatsApp<\/a>/','',$html)??$html;
$html=str_replace('<div class="screen-note">','<div class="screen-note" style="display:none">',$html);
$html=str_replace('>Guardar PDF</button>','>Descargar PDF completo</button>',$html);
$html=str_replace('>Generar PDF</button>','>Descargar PDF completo</button>',$html);

$familyJ=json_encode($family,JSON_UNESCAPED_SLASHES);$hasProJ=json_encode($hasPrologue);$hasPayJ=json_encode($hasPayment);$fileJ=json_encode('Presupuesto-'.$projectNumber.'-v'.$versionNo.'.pdf',JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$preview=<<<HTML
<div id="clientCommercialPreview" class="client-commercial-preview">
  <div id="prologueSlot"></div>
  <div id="quotePreviewAnchor"></div>
  <div id="paymentSlot"></div>
</div>
<script>
(function(){
 const fam=$familyJ,hasPro=$hasProJ,hasPay=$hasPayJ,filename=$fileJ;
 const sheet=document.getElementById('quoteSheet'),anchor=document.getElementById('quotePreviewAnchor');if(sheet&&anchor)anchor.replaceWith(sheet);
 function addPreview(slotId,type,title){const slot=document.getElementById(slotId);if(!slot)return;const section=document.createElement('section');section.className='commercial-part';const iframe=document.createElement('iframe');iframe.title=title;iframe.src='public-pdf-asset.php?type='+type+'&family='+encodeURIComponent(fam)+'#toolbar=0&navpanes=0';section.appendChild(iframe);slot.replaceWith(section);}
 if(hasPro)addPreview('prologueSlot','prologo','Prólogo del presupuesto');else document.getElementById('prologueSlot')?.remove();
 if(hasPay)addPreview('paymentSlot','pago','Forma de pago');else document.getElementById('paymentSlot')?.remove();
 async function appendPdf(out,url){const res=await fetch(url,{credentials:'same-origin'});if(!res.ok)return;const src=await PDFLib.PDFDocument.load(await res.arrayBuffer());const pages=await out.copyPages(src,src.getPageIndices());pages.forEach(p=>out.addPage(p));}
 async function appendQuote(out){const s=document.getElementById('quoteSheet');if(!s)return;if(typeof waitForImages==='function')await waitForImages(s);const c=await html2canvas(s,{scale:2,useCORS:true,backgroundColor:'#fff',logging:false,width:s.scrollWidth,height:s.scrollHeight,windowWidth:s.scrollWidth,windowHeight:s.scrollHeight});const png=await out.embedPng(c.toDataURL('image/png'));const page=out.addPage([595.28,841.89]);const scale=Math.min(595.28/png.width,841.89/png.height);const w=png.width*scale,h=png.height*scale;page.drawImage(png,{x:(595.28-w)/2,y:(841.89-h)/2,width:w,height:h});}
 window.generatePdf=async function(){try{const out=await PDFLib.PDFDocument.create();if(hasPro)await appendPdf(out,'public-pdf-asset.php?type=prologo&family='+encodeURIComponent(fam));await appendQuote(out);if(hasPay)await appendPdf(out,'public-pdf-asset.php?type=pago&family='+encodeURIComponent(fam));const bytes=await out.save();const blob=new Blob([bytes],{type:'application/pdf'}),url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=filename;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),1500);}catch(e){console.error(e);alert('No pude generar el PDF. Volvé a intentar.');}};
 const btn=document.getElementById('generatePdfBtn');if(btn)btn.onclick=window.generatePdf;
})();
</script>
HTML;
$html=str_replace('</body>',$preview.'</body>',$html);
$html=str_replace('</head>','<style>.toolbar{justify-content:flex-end}.toolbar #generatePdfBtn{background:#ff6702}.client-commercial-preview{width:210mm;margin:0 auto 30px}.client-commercial-preview>.sheet{margin:0 auto 18px}.commercial-part{width:210mm;margin:0 auto 18px;background:#fff;box-shadow:0 12px 34px #0002}.commercial-part iframe{display:block;width:210mm;height:297mm;border:0;background:#fff}@media(max-width:850px){.client-commercial-preview,.commercial-part,.commercial-part iframe{width:100%}.commercial-part iframe{height:75vh}}</style></head>',$html);
echo $html;
