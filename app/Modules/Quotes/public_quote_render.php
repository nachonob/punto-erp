<?php
declare(strict_types=1);
$id=(int)($_GET['id']??0);
if($id<1 || (int)($_SESSION['public_quote_access_id']??0)!==$id){http_response_code(403);exit('Acceso no autorizado.');}
$_SESSION['user']=['role'=>'public_quote'];

$root=dirname(__DIR__,3);$family='';$hasPrologue=false;$hasPayment=false;$projectNumber='presupuesto';$versionNo=1;$quote=[];$acceptance=null;$acceptError=null;
try{
 $cfg=require $root.'/config.php';
 $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec("CREATE TABLE IF NOT EXISTS quote_acceptances (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,quote_id INT UNSIGNED NOT NULL,accepted_by_name VARCHAR(190) NOT NULL,accepted_by_document VARCHAR(40) NOT NULL,accepted_at DATETIME NOT NULL,ip_address VARCHAR(45) NULL,user_agent VARCHAR(255) NULL,evidence_hash CHAR(64) NOT NULL,evidence_json MEDIUMTEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_quote_acceptance(quote_id),INDEX idx_quote_acceptance_hash(evidence_hash),CONSTRAINT fk_quote_acceptance_quote FOREIGN KEY(quote_id) REFERENCES quotes(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $statusType=(string)$db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='quotes' AND COLUMN_NAME='status'")->fetchColumn();
 if(!str_contains($statusType,'aprobado_inicial'))$db->exec("ALTER TABLE quotes MODIFY status ENUM('borrador','enviado','aprobado_inicial','aprobado_definitivo','final','rechazado') NOT NULL DEFAULT 'borrador'");
 $s=$db->prepare('SELECT q.*,p.project_number,p.name project_name,c.business_name,c.contact_name,c.cuit FROM quotes q JOIN projects p ON p.id=q.project_id JOIN clients c ON c.id=p.client_id WHERE q.id=?');$s->execute([$id]);$quote=$s->fetch()?:[];
 $family=strtolower(trim((string)($quote['quote_template_family']??'')));$projectNumber=(string)($quote['project_number']??'presupuesto');$versionNo=(int)($quote['version_no']??1);
 $hasPrologue=$family!==''&&is_file($root.'/storage/uploads/pdf_assets/prologo/'.$family.'.pdf');
 $hasPayment=$family!==''&&is_file($root.'/storage/uploads/pdf_assets/pago/'.$family.'.pdf');
 $_SESSION['csrf']??=bin2hex(random_bytes(24));
 if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['public_action']??'')==='accept'){
  try{
   if(!hash_equals((string)$_SESSION['csrf'],(string)($_POST['csrf']??'')))throw new Exception('La sesión venció. Actualizá la página e intentá nuevamente.');
   $name=trim((string)($_POST['accepted_by_name']??''));$document=trim((string)($_POST['accepted_by_document']??''));
   if(!$quote||empty($quote['is_current'])||!in_array($quote['status'],['enviado'],true))throw new Exception('Esta versión ya no está disponible para aceptación.');
   if(strlen($name)<3||strlen($document)<5||empty($_POST['accept_terms']))throw new Exception('Completá tu nombre, DNI o CUIT y confirmá la aceptación.');
   $itemsStmt=$db->prepare('SELECT * FROM quote_items WHERE quote_id=? ORDER BY id');$itemsStmt->execute([$id]);$evidenceItems=$itemsStmt->fetchAll();
   $acceptedAt=date('Y-m-d H:i:s');$ip=substr((string)($_SERVER['REMOTE_ADDR']??''),0,45);$agent=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255);
   $evidence=['quote'=>$quote,'items'=>$evidenceItems,'acceptance'=>['name'=>$name,'document'=>$document,'accepted_at'=>$acceptedAt]];
   $evidenceJson=json_encode($evidence,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($evidenceJson===false)throw new Exception('No se pudo generar la evidencia de aceptación.');$hash=hash('sha256',$evidenceJson);
   $db->beginTransaction();$u=$db->prepare("UPDATE quotes SET status='aprobado_inicial',followup_closed_at=?,next_followup_date=NULL WHERE id=? AND is_current=1 AND status='enviado'");$u->execute([$acceptedAt,$id]);if($u->rowCount()!==1)throw new Exception('El presupuesto ya fue aceptado o dejó de estar vigente.');
   $db->prepare('INSERT INTO quote_acceptances(quote_id,accepted_by_name,accepted_by_document,accepted_at,ip_address,user_agent,evidence_hash,evidence_json) VALUES(?,?,?,?,?,?,?,?)')->execute([$id,$name,$document,$acceptedAt,$ip?:null,$agent?:null,$hash,$evidenceJson]);
   $db->commit();$notify=(string)($cfg['company_email']??'');if(filter_var($notify,FILTER_VALIDATE_EMAIL))@mail($notify,'Presupuesto aceptado · '.$projectNumber,'El presupuesto del proyecto '.$projectNumber.' fue aceptado inicialmente por '.$name.' ('.$document.'). Evidencia: '.$hash,'From: '.$notify);
   header('Location: public-presupuesto.php?t='.rawurlencode((string)($_GET['t']??'')).'&accepted=1');exit;
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$acceptError=$e->getMessage();}
 }
 $a=$db->prepare('SELECT * FROM quote_acceptances WHERE quote_id=?');$a->execute([$id]);$acceptance=$a->fetch();
 if($acceptance){$quote['status']='aprobado_inicial';}
}catch(Throwable $e){$acceptError='No se pudo preparar la aceptación del presupuesto.';}

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
$csrfHtml=htmlspecialchars((string)($_SESSION['csrf']??''),ENT_QUOTES,'UTF-8');$errorHtml=$acceptError?'<p class="accept-error">'.htmlspecialchars($acceptError,ENT_QUOTES,'UTF-8').'</p>':'';
if($acceptance){$panel='<section class="accept-card accepted"><h2>Presupuesto aceptado</h2><p>La aceptación inicial fue registrada por <b>'.htmlspecialchars($acceptance['accepted_by_name'],ENT_QUOTES,'UTF-8').'</b> el '.date('d/m/Y H:i',strtotime($acceptance['accepted_at'])).'.</p><p><b>DNI/CUIT:</b> '.htmlspecialchars($acceptance['accepted_by_document'],ENT_QUOTES,'UTF-8').'<br><b>Código de evidencia:</b> <span>'.htmlspecialchars($acceptance['evidence_hash'],ENT_QUOTES,'UTF-8').'</span></p></section>';}
elseif(($quote['status']??'')==='enviado'&&!empty($quote['is_current'])){$panel='<section class="accept-card"><h2>Aceptar presupuesto</h2><p>Completá tus datos para registrar la aceptación inicial de esta propuesta, versión '.(int)$versionNo.'.</p>'.$errorHtml.'<form method="post"><input type="hidden" name="csrf" value="'.$csrfHtml.'"><input type="hidden" name="public_action" value="accept"><label>Nombre y apellido<input type="text" name="accepted_by_name" autocomplete="name" required></label><label>DNI o CUIT<input type="text" name="accepted_by_document" inputmode="numeric" required></label><label class="check"><input type="checkbox" name="accept_terms" value="1" required> Confirmo que revisé y acepto los importes y condiciones de este presupuesto.</label><button type="submit">Confirmar aceptación inicial</button></form></section>';}
else{$panel='<section class="accept-card"><h2>Aceptación no disponible</h2><p>Esta versión todavía no fue enviada, ya fue reemplazada o no se encuentra vigente.</p>'.$errorHtml.'</section>';}
$html=str_replace('<body>','<body>'.$panel,$html);

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
$html=str_replace('</head>','<style>.accept-card{width:min(94%,900px);margin:18px auto;padding:24px;border-radius:14px;background:#fff;border:2px solid #ff6702;box-shadow:0 10px 28px #0002}.accept-card h2{margin:0 0 8px}.accept-card form{display:grid;gap:14px}.accept-card label{display:grid;gap:6px;font-weight:700}.accept-card input[type=text]{padding:11px;border:1px solid #bbb;border-radius:8px;font:inherit}.accept-card .check{display:flex;grid-template-columns:auto 1fr;align-items:flex-start;font-weight:500}.accept-card button{justify-self:start;border:0;border-radius:8px;padding:12px 18px;background:#ff6702;color:#fff;font-weight:800;cursor:pointer}.accept-card.accepted{border-color:#15815c;background:#effbf5}.accept-card.accepted span{word-break:break-all}.accept-error{padding:11px;border-radius:8px;background:#ffe7e7;color:#9d2020}.toolbar{justify-content:flex-end}.toolbar #generatePdfBtn{background:#ff6702}.client-commercial-preview{width:210mm;margin:0 auto 30px}.client-commercial-preview>.sheet{margin:0 auto 18px}.commercial-part{width:210mm;margin:0 auto 18px;background:#fff;box-shadow:0 12px 34px #0002}.commercial-part iframe{display:block;width:210mm;height:297mm;border:0;background:#fff}@media(max-width:850px){.client-commercial-preview,.commercial-part,.commercial-part iframe{width:100%}.commercial-part iframe{height:75vh}}</style></head>',$html);
echo $html;
