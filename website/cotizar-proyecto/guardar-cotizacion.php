<?php
declare(strict_types=1);

function fail(string $message, int $status = 400): never {
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>No se pudo enviar</title><style>body{font:16px system-ui;background:#f4f6f8;color:#27303b;padding:40px}.box{max-width:680px;margin:auto;background:#fff;border:1px solid #e1e5e9;border-radius:14px;padding:28px}a{color:#ff6702}</style><div class="box"><h1>No se pudo guardar la solicitud</h1><p>'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p><p><a href="./">Volver al formulario</a></p></div>';
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Método no permitido.', 405);
$publicHtml = dirname(__DIR__);
$erpRoots = [$publicHtml.'/erp-dev', $publicHtml.'/erp'];
$cfg = null; $erpRoot = null;
foreach ($erpRoots as $candidate) { if (is_file($candidate.'/config.php')) { $erpRoot=$candidate; $cfg=require $candidate.'/config.php'; break; } }
if (!$cfg || !$erpRoot) fail('No se encontró la configuración del ERP.', 500);
date_default_timezone_set($cfg['timezone'] ?? 'America/Argentina/Buenos_Aires');
try { $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); } catch(Throwable $e){ fail('No se pudo conectar con la base de datos.',500); }
function clean(string $key): string { return trim((string)($_POST[$key]??'')); }
function intOrNull(string $key): ?int { $v=$_POST[$key]??null; if($v===null||$v==='')return null; $n=filter_var($v,FILTER_VALIDATE_INT); return $n===false?null:(int)$n; }
function arr(string $key): array { $v=$_POST[$key]??[]; if(!is_array($v))$v=[$v]; return array_values(array_filter(array_map(static fn($x)=>trim((string)$x),$v),static fn($x)=>$x!=='')); }
function addLine(array &$lines,string $label,mixed $value):void{ if($value===null||$value===''||$value===[])return; if(is_array($value))$value=implode(', ',$value); $lines[]=$label.': '.$value; }
function phoneDigits(string $v):string{return preg_replace('/\D+/','',$v)??'';}
function projectNumber(PDO $db): string {
    $year=(int)date('Y'); $min=$year===2026?82:1;
    $s=$db->prepare("SELECT id,next_number FROM document_sequences WHERE document_type='project' AND year_no=? FOR UPDATE");
    $s->execute([$year]); $seq=$s->fetch();
    if(!$seq){$db->prepare("INSERT INTO document_sequences(document_type,year_no,next_number,prefix) VALUES('project',?,?, 'A')")->execute([$year,$min]);$seq=['id'=>(int)$db->lastInsertId(),'next_number'=>$min];}
    $next=max($min,(int)$seq['next_number']); $number=sprintf('%02dA%02d',$year%100,$next);
    $db->prepare("UPDATE document_sequences SET next_number=?,prefix='A' WHERE id=?")->execute([$next+1,(int)$seq['id']]);
    return $number;
}
function effectivePrice(PDO $db,int $productId,int $listId,float $cost,float $defaultMarkup): float {
    $markup=$defaultMarkup;
    try{$s=$db->prepare('SELECT markup_percentage FROM product_price_overrides WHERE product_id=? AND price_list_id=?');$s->execute([$productId,$listId]);$v=$s->fetchColumn();if($v!==false)$markup=(float)$v;}catch(Throwable $e){}
    return round($cost*(1+$markup/100),2);
}
function productBySku(PDO $db,string $sku): ?array {
    $s=$db->prepare('SELECT p.id,p.sku,p.description,p.unit,p.cost_usd,COALESCE(pc.name,\'Otros\') category FROM products p LEFT JOIN product_categories pc ON pc.id=p.category_id WHERE p.active=1 AND UPPER(TRIM(p.sku))=UPPER(TRIM(?)) LIMIT 1');
    $s->execute([$sku]); return $s->fetch()?:null;
}
function addRule(array &$rules,string $sku,int $qty):void{if($qty<=0)return;$rules[$sku]=($rules[$sku]??0)+$qty;}

$name=clean('nombre'); $whatsapp=clean('telefono'); $email=strtolower(clean('email')); $location=clean('ubicacion');
if($name===''||$whatsapp===''||$location==='')fail('Completá nombre, WhatsApp y ciudad/localidad.'); if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))fail('El email ingresado no es válido.');
$rooms=arr('ambientes'); $systems=arr('sistemas'); $climateTypes=arr('tipos_climatizacion'); $plansAvailable=clean('planos')==='si'?1:0; $plansPath=null;
if($plansAvailable){ if(empty($_FILES['archivo_planos'])||$_FILES['archivo_planos']['error']!==UPLOAD_ERR_OK)fail('Elegiste que tenés planos disponibles, pero no adjuntaste un PDF.'); if((int)$_FILES['archivo_planos']['size']>15*1024*1024)fail('El PDF de planos supera el máximo de 15 MB.'); $mime=(new finfo(FILEINFO_MIME_TYPE))->file($_FILES['archivo_planos']['tmp_name']); if($mime!=='application/pdf')fail('El archivo de planos debe ser PDF.'); $uploadDir=$publicHtml.'/uploads/cotizaciones-planos'; if(!is_dir($uploadDir)&&!mkdir($uploadDir,0775,true)&&!is_dir($uploadDir))fail('No se pudo preparar la carpeta de planos.',500); $safeName='planos-'.date('Ymd-His').'-'.bin2hex(random_bytes(6)).'.pdf'; $full=$uploadDir.'/'.$safeName; if(!move_uploaded_file($_FILES['archivo_planos']['tmp_name'],$full))fail('No se pudo guardar el PDF de planos.',500); $plansPath='uploads/cotizaciones-planos/'.$safeName; }
try{
$db->beginTransaction();
$client=null;$phone=phoneDigits($whatsapp);
if($phone!==''){$s=$db->prepare("SELECT * FROM clients WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(whatsapp,'+',''),' ',''),'-',''),'(',''),')','')=? ORDER BY id DESC LIMIT 1 FOR UPDATE");$s->execute([$phone]);$client=$s->fetch()?:null;}
if(!$client&&$email!==''){$s=$db->prepare('SELECT * FROM clients WHERE LOWER(email)=? ORDER BY id DESC LIMIT 1 FOR UPDATE');$s->execute([$email]);$client=$s->fetch()?:null;}
if($client){$clientId=(int)$client['id'];$clientNumber=(int)($client['client_number']??0);if($clientNumber<=0){$clientNumber=(int)$db->query('SELECT COALESCE(MAX(client_number),0)+1 FROM clients FOR UPDATE')->fetchColumn();$db->prepare('UPDATE clients SET client_number=? WHERE id=?')->execute([$clientNumber,$clientId]);}$db->prepare("UPDATE clients SET contact_name=CASE WHEN contact_name='' OR contact_name IS NULL THEN ? ELSE contact_name END,email=CASE WHEN email='' OR email IS NULL THEN ? ELSE email END,whatsapp=CASE WHEN whatsapp='' OR whatsapp IS NULL THEN ? ELSE whatsapp END,city=CASE WHEN city='' OR city IS NULL THEN ? ELSE city END,active=1 WHERE id=?")->execute([$name,$email,$whatsapp,$location,$clientId]);}
else{$next=(int)$db->query('SELECT COALESCE(MAX(client_number),0)+1 FROM clients FOR UPDATE')->fetchColumn();$db->prepare('INSERT INTO clients(client_number,business_name,contact_name,cuit,iva_condition,email,whatsapp,address,city,province,country,notes,active) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,1)')->execute([$next,$name,$name,'','consumidor_final',$email,$whatsapp,'',$location,'','Argentina','Alta automática desde cotizar-proyecto']);$clientId=(int)$db->lastInsertId();$clientNumber=$next;}
$requestSeq=(int)$db->query('SELECT COALESCE(MAX(id),0)+1 FROM web_quote_requests FOR UPDATE')->fetchColumn(); $requestNumber='WEB-'.date('Y').'-'.str_pad((string)$requestSeq,5,'0',STR_PAD_LEFT);
$payload=['estado'=>clean('estado'),'tipo'=>clean('tipo'),'superficie'=>intOrNull('superficie'),'plantas'=>intOrNull('plantas'),'fecha'=>clean('fecha'),'dormitorios'=>intOrNull('dormitorios'),'banos'=>intOrNull('banos'),'ambientes'=>$rooms,'sistemas'=>$systems,'tipos_climatizacion'=>$climateTypes,'cantidad_teclas'=>intOrNull('cantidad_teclas'),'cantidad_splits'=>intOrNull('cantidad_splits'),'cantidad_termostatos'=>intOrNull('cantidad_termostatos'),'cantidad_cortinas'=>intOrNull('cantidad_cortinas'),'cantidad_camaras'=>intOrNull('cantidad_camaras'),'cantidad_cerraduras'=>intOrNull('cantidad_cerraduras'),'cantidad_zonas_audio'=>intOrNull('cantidad_zonas_audio'),'planos'=>$plansAvailable,'presupuesto'=>clean('presupuesto'),'comentarios'=>clean('comentarios')];
$sql='INSERT INTO web_quote_requests(request_number,client_id,contact_name,whatsapp,email,location,project_stage,property_type,surface_m2,floors,estimated_date,bedrooms,bathrooms,rooms,systems,climate_types,switches_qty,splits_qty,thermostats_qty,curtains_qty,cameras_qty,smart_locks_qty,audio_zones_qty,plans_available,plans_file,budget_range,comments,payload_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
$db->prepare($sql)->execute([$requestNumber,$clientId,$name,$whatsapp,$email,$location,clean('estado'),clean('tipo'),intOrNull('superficie'),intOrNull('plantas'),clean('fecha'),intOrNull('dormitorios'),intOrNull('banos'),implode(', ',$rooms),implode(', ',$systems),implode(', ',$climateTypes),intOrNull('cantidad_teclas'),intOrNull('cantidad_splits'),intOrNull('cantidad_termostatos'),intOrNull('cantidad_cortinas'),intOrNull('cantidad_camaras'),intOrNull('cantidad_cerraduras'),intOrNull('cantidad_zonas_audio'),$plansAvailable,$plansPath,clean('presupuesto'),clean('comentarios'),json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);

$projectNo=projectNumber($db);
$projectNotes='Generado automáticamente desde Cotizar proyecto. Solicitud '.$requestNumber."\n".'Etapa: '.clean('estado')."\n".'Tipo: '.clean('tipo')."\n".'Superficie: '.(intOrNull('superficie')??'').' m²'."\n".'Plantas: '.(intOrNull('plantas')??'')."\n".'Ubicación: '.$location."\n".'Planos: '.($plansPath?:'No adjuntados')."\n".'Comentarios: '.clean('comentarios');
$db->prepare("INSERT INTO projects(client_id,partner_id,project_number,name,status,currency,tax_mode,vat_rate,engineering_pct,notes) VALUES(?,NULL,?,?, 'consulta','USD','sin_iva',21,10,?)")->execute([$clientId,$projectNo,$name,$projectNotes]);
$projectId=(int)$db->lastInsertId();

$rules=[];
$floors=max(0,(int)(intOrNull('plantas')??0)); addRule($rules,'LS082WH',$floors); if((int)(intOrNull('superficie')??0)>200)addRule($rules,'LS082WH',1);
addRule($rules,'LS125WH',max(0,(int)(intOrNull('cantidad_teclas')??0)));
addRule($rules,'LS251WH',max(0,(int)(intOrNull('cantidad_splits')??0)));
addRule($rules,'LS220-GT1',max(0,(int)(intOrNull('cantidad_termostatos')??0)));
addRule($rules,'QS-ECC02-Zigbee',max(0,(int)(intOrNull('cantidad_cortinas')??0)));
addRule($rules,'LS259',max(0,(int)(intOrNull('cantidad_camaras')??0)));
addRule($rules,'C200',max(0,(int)(intOrNull('cantidad_cerraduras')??0)));
$poolBombas=false;foreach($systems as $sys){if(mb_strtolower(trim($sys))==='pileta y bombas'){$poolBombas=true;break;}}if($poolBombas)addRule($rules,'LS193',1);

$list=$db->query('SELECT id,name,markup_percentage FROM price_lists WHERE active=1 ORDER BY id LIMIT 1')->fetch();
if(!$list)throw new Exception('No hay una lista de precios activa para generar el presupuesto automático.');
$listId=(int)$list['id'];$materials=0;$quoteItems=[];$missing=[];$sort=0;
foreach($rules as $sku=>$qty){$p=productBySku($db,$sku);if(!$p){$missing[]=$sku;continue;}$unit=effectivePrice($db,(int)$p['id'],$listId,(float)$p['cost_usd'],(float)$list['markup_percentage']);$sub=round($qty*$unit,2);$materials+=$sub;$quoteItems[]=[$p,$qty,$unit,$sub,$sort++];}
$matVat=21.0;
// Mano de obra automática: 80% del total neto de materiales, sin IVA.
$labor=round($materials*0.80,2);
$total=round(($materials*1.21)+$labor,2);
$autoNotes='Presupuesto generado automáticamente desde '.$requestNumber.'. Mano de obra calculada inicialmente al 80% de materiales netos; es editable. Revisar antes de enviar al cliente.';if($missing)$autoNotes.=' Productos no encontrados por SKU: '.implode(', ',$missing).'.';
$db->prepare('INSERT INTO quotes(project_id,version_no,quote_category,currency,quote_date,quote_families,quote_template_family,price_list_id,materials_amount,labor_amount,labor_description,subtotal,tax_mode,vat_rate,materials_tax_mode,materials_vat_rate,labor_tax_mode,labor_vat_rate,total,status,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$projectId,1,'domotica','USD',date('Y-m-d'),'lifesmart','lifesmart',$listId,$materials,$labor,'Configuración, montaje y diseño de escenas',round($materials+$labor,2),'sin_iva',21,'mas_iva',$matVat,'sin_iva',21,$total,'borrador',$autoNotes]);
$quoteId=(int)$db->lastInsertId();
$ins=$db->prepare('INSERT INTO quote_items(quote_id,product_id,category,brand,section_title,block_order,sku,description,unit,quantity,unit_price,price_list_id,subtotal,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
foreach($quoteItems as [$p,$qty,$unit,$sub,$order]){$cat=(string)$p['category'];$brand=(stripos($cat,'control4')!==false?'Control4':(stripos($cat,'shelly')!==false?'Shelly':(stripos($cat,'lifesmart')!==false||stripos($cat,'domot')!==false?'LifeSmart':'Otro')));$ins->execute([$quoteId,(int)$p['id'],$cat,$brand,'Materiales',10,$p['sku'],$p['description'],$p['unit'],$qty,$unit,$listId,$sub,$order]);}
try{$db->prepare('INSERT INTO quote_labor_items(quote_id,title,description,amount,tax_mode,vat_rate,block_order) VALUES(?,?,?,?,?,?,?)')->execute([$quoteId,'Mano de obra','Configuración, montaje y diseño de escenas',$labor,'sin_iva',21,20]);}catch(Throwable $e){}

$db->commit();
}catch(Throwable $e){ if($db->inTransaction())$db->rollBack(); if($plansPath)@unlink($publicHtml.'/'.$plansPath); fail('La solicitud no pudo guardarse en el ERP. '.$e->getMessage(),500); }
$lines=['Hola Punto Domótica, completé el formulario de cotización.','','Solicitud: '.$requestNumber,'Cliente N°: '.str_pad((string)$clientNumber,5,'0',STR_PAD_LEFT),'Proyecto: '.$projectNo,'','DATOS DE CONTACTO']; addLine($lines,'Nombre',$name);addLine($lines,'WhatsApp',$whatsapp);addLine($lines,'Email',$email);addLine($lines,'Ubicación',$location);$lines[]='';$lines[]='PROYECTO';addLine($lines,'Etapa',clean('estado'));addLine($lines,'Tipo de propiedad',clean('tipo'));if(intOrNull('superficie')!==null)addLine($lines,'Superficie',intOrNull('superficie').' m²');addLine($lines,'Cantidad de plantas',intOrNull('plantas'));addLine($lines,'Fecha estimada',clean('fecha'));$lines[]='';$lines[]='AMBIENTES';addLine($lines,'Dormitorios',intOrNull('dormitorios'));addLine($lines,'Baños',intOrNull('banos'));addLine($lines,'Otros ambientes',$rooms);$lines[]='';$lines[]='SISTEMAS';addLine($lines,'Sistemas seleccionados',$systems);addLine($lines,'Tipo de climatización',$climateTypes);if(in_array('Iluminación inteligente',$systems,true))addLine($lines,'Cantidad de teclas',intOrNull('cantidad_teclas'));if(in_array('Climatización',$systems,true)){if(in_array('Split',$climateTypes,true))addLine($lines,'Cantidad de splits',intOrNull('cantidad_splits'));if(in_array('Losa radiante',$climateTypes,true)||in_array('Radiadores',$climateTypes,true))addLine($lines,'Cantidad de termostatos',intOrNull('cantidad_termostatos'));}if(in_array('Cortinas y persianas',$systems,true))addLine($lines,'Cantidad de cortinas o persianas',intOrNull('cantidad_cortinas'));if(in_array('Cámaras',$systems,true))addLine($lines,'Cantidad de cámaras',intOrNull('cantidad_camaras'));if(in_array('Cerradura inteligente',$systems,true))addLine($lines,'Cantidad de cerraduras inteligentes',intOrNull('cantidad_cerraduras'));if(in_array('Audio multizona',$systems,true))addLine($lines,'Cantidad de zonas de audio',intOrNull('cantidad_zonas_audio'));$lines[]='';$lines[]='DETALLES';addLine($lines,'Planos',$plansAvailable?'Sí, PDF adjuntado':'No');addLine($lines,'Presupuesto orientativo',clean('presupuesto'));addLine($lines,'Comentarios',clean('comentarios'));$lines[]='';$lines[]='PRESUPUESTO AUTOMÁTICO';addLine($lines,'Materiales sin IVA','US$ '.number_format($materials,2,',','.'));addLine($lines,'Mano de obra (80%)','US$ '.number_format($labor,2,',','.'));addLine($lines,'Total estimado','US$ '.number_format($total,2,',','.'));
header('Location: https://wa.me/5493413661548?text='.rawurlencode(implode("\n",$lines)),true,303); exit;
