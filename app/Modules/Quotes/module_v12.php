<?php
declare(strict_types=1);

$a=$_GET['a']??'new_quote';
$root=dirname(__DIR__,3);

function q12Brand(string $cat):string{
    $x=mb_strtolower($cat);
    if(str_contains($x,'control4')||str_contains($x,'c4'))return 'Control4';
    if(str_contains($x,'shelly'))return 'Shelly';
    if(str_contains($x,'lifesmart')||str_contains($x,'domot'))return 'LifeSmart';
    return 'Otro';
}
function q12ReplaceCharge(PDO $db,int $projectId,int $newChargeId,array $types):void{
    $m=implode(',',array_fill(0,count($types),'?'));
    $s=$db->prepare("SELECT id FROM charges WHERE project_id=? AND active=1 AND id<>? AND type IN ($m)");
    $s->execute(array_merge([$projectId,$newChargeId],$types));
    foreach($s as $old){
        $als=$db->prepare('SELECT payment_id,amount FROM allocations WHERE charge_id=?');$als->execute([$old['id']]);
        foreach($als as $al)$db->prepare('INSERT INTO allocations(payment_id,charge_id,amount) VALUES(?,?,?) ON DUPLICATE KEY UPDATE amount=amount+VALUES(amount)')->execute([$al['payment_id'],$newChargeId,$al['amount']]);
        $db->prepare('DELETE FROM allocations WHERE charge_id=?')->execute([$old['id']]);
        $db->prepare('UPDATE charges SET active=0 WHERE id=?')->execute([$old['id']]);
    }
}
function q12FollowupDate(string $from):string{
    $date=new DateTimeImmutable($from);$days=0;
    while($days<10){$date=$date->modify('+1 day');if((int)$date->format('N')<=5)$days++;}
    return $date->format('Y-m-d');
}

function q12EnsureVersioning(PDO $db):void{
    if(!(bool)$db->query("SHOW COLUMNS FROM quotes LIKE 'quote_series_key'")->fetch())$db->exec("ALTER TABLE quotes ADD COLUMN quote_series_key CHAR(32) NULL AFTER project_id");
    if(!(bool)$db->query("SHOW COLUMNS FROM quotes LIKE 'locked_at'")->fetch())$db->exec("ALTER TABLE quotes ADD COLUMN locked_at DATETIME NULL AFTER sent_at");
    if(!(bool)$db->query("SHOW COLUMNS FROM quote_items LIKE 'discount_pct'")->fetch())$db->exec("ALTER TABLE quote_items ADD COLUMN discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER unit_price");
    $db->exec("UPDATE quotes SET quote_series_key=LOWER(LEFT(SHA2(CONCAT(project_id,'|',quote_category,'|',COALESCE(NULLIF(TRIM(proposal_name),''),CONCAT('presupuesto-',id))),256),32)) WHERE quote_series_key IS NULL OR quote_series_key=''");
    $statusType=(string)$db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='quotes' AND COLUMN_NAME='status'")->fetchColumn();
    if(!str_contains($statusType,'aprobado_definitivo'))$db->exec("ALTER TABLE quotes MODIFY status ENUM('borrador','enviado','aprobado_inicial','aprobado_definitivo','final','rechazado') NOT NULL DEFAULT 'borrador'");
    $old=$db->query("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='quotes' AND NON_UNIQUE=0 AND INDEX_NAME<>'PRIMARY' GROUP BY INDEX_NAME HAVING GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)='project_id,version_no' LIMIT 1")->fetchColumn();
    if($old)$db->exec('ALTER TABLE quotes DROP INDEX `'.str_replace('`','``',(string)$old).'`');
    $hasSeriesIndex=$db->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='quotes' AND INDEX_NAME='uq_quote_series_version'")->fetchColumn();
    if(!(int)$hasSeriesIndex)$db->exec("ALTER TABLE quotes MODIFY quote_series_key CHAR(32) NOT NULL, ADD UNIQUE KEY uq_quote_series_version (quote_series_key,version_no)");
}

if($a==='duplicate_quote'){
    session_start();
    $cfg=require $root.'/config.php';
    date_default_timezone_set($cfg['timezone']??'America/Argentina/Buenos_Aires');
    if(empty($_SESSION['user'])){header('Location:index.php');exit;}
    if(($_SESSION['user']['role']??'')!=='admin'){http_response_code(403);exit('No autorizado.');}
    if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Solicitud vencida.');}
    $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);q12EnsureVersioning($db);
    try{
        $sourceId=(int)($_POST['quote_id']??0);
        $responsibleUserId=(int)($_SESSION['user']['id']??0);
        $userCheck=$db->prepare('SELECT id FROM users WHERE id=? AND active=1');$userCheck->execute([$responsibleUserId]);
        if(!$userCheck->fetchColumn())throw new RuntimeException('La sesión pertenece a un usuario que ya no existe. Cerrá sesión e ingresá nuevamente.');
        if(!(bool)$db->query("SHOW COLUMNS FROM quotes LIKE 'proposal_name'")->fetch())$db->exec("ALTER TABLE quotes ADD COLUMN proposal_name VARCHAR(150) NULL AFTER quote_category");
        $sourceQuery=$db->prepare('SELECT * FROM quotes WHERE id=?');$sourceQuery->execute([$sourceId]);$source=$sourceQuery->fetch();
        if(!$source)throw new RuntimeException('El presupuesto que querés duplicar no existe.');
        $db->beginTransaction();
        $seriesKey=(string)($source['quote_series_key']??'');if($seriesKey==='')$seriesKey=bin2hex(random_bytes(16));$versionQuery=$db->prepare('SELECT COALESCE(MAX(version_no),0)+1 FROM quotes WHERE quote_series_key=? FOR UPDATE');$versionQuery->execute([$seriesKey]);$newVersion=(int)$versionQuery->fetchColumn();
        $insert=$db->prepare('INSERT INTO quotes(project_id,quote_series_key,version_no,quote_category,proposal_name,currency,quote_date,quote_families,quote_template_family,price_list_id,materials_amount,labor_amount,labor_description,subtotal,tax_mode,vat_rate,materials_tax_mode,materials_vat_rate,labor_tax_mode,labor_vat_rate,total,status,notes,responsible_user_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $insert->execute([(int)$source['project_id'],$seriesKey,$newVersion,$source['quote_category'],$source['proposal_name']?:null,$source['currency'],date('Y-m-d'),$source['quote_families'],$source['quote_template_family'],$source['price_list_id'],$source['materials_amount'],$source['labor_amount'],$source['labor_description'],$source['subtotal'],$source['tax_mode'],$source['vat_rate'],$source['materials_tax_mode'],$source['materials_vat_rate'],$source['labor_tax_mode'],$source['labor_vat_rate'],$source['total'],'borrador',$source['notes'],$responsibleUserId]);
        $newId=(int)$db->lastInsertId();
        $copyItems=$db->prepare('INSERT INTO quote_items(quote_id,product_id,is_manual,category,brand,section_title,block_order,sku,description,unit,quantity,unit_price,discount_pct,price_list_id,subtotal,sort_order) SELECT ?,product_id,is_manual,category,brand,section_title,block_order,sku,description,unit,quantity,unit_price,discount_pct,price_list_id,subtotal,sort_order FROM quote_items WHERE quote_id=? ORDER BY id');
        $copyItems->execute([$newId,$sourceId]);
        $laborMap=[];$laborQuery=$db->prepare('SELECT * FROM quote_labor_items WHERE quote_id=? ORDER BY id');$laborQuery->execute([$sourceId]);
        $insertLabor=$db->prepare('INSERT INTO quote_labor_items(quote_id,title,description,amount,tax_mode,vat_rate,block_order) VALUES(?,?,?,?,?,?,?)');
        foreach($laborQuery as $labor){$insertLabor->execute([$newId,$labor['title'],$labor['description'],$labor['amount'],$labor['tax_mode'],$labor['vat_rate'],$labor['block_order']]);$laborMap[(int)$labor['id']]=(int)$db->lastInsertId();}
        if((bool)$db->query("SHOW TABLES LIKE 'quote_discounts'")->fetch()){
            $discountQuery=$db->prepare('SELECT * FROM quote_discounts WHERE quote_id=? ORDER BY sort_order,id');$discountQuery->execute([$sourceId]);
            $insertDiscount=$db->prepare('INSERT INTO quote_discounts(quote_id,scope,item_type,item_id,discount_type,value,description,sort_order) VALUES(?,?,?,?,?,?,?,?)');
            foreach($discountQuery as $discount){$itemId=(int)($discount['item_id']??0);if(($discount['item_type']??'')==='labor'&&$itemId)$itemId=$laborMap[$itemId]??0;$insertDiscount->execute([$newId,$discount['scope'],$discount['item_type'],$itemId?:null,$discount['discount_type'],$discount['value'],$discount['description'],$discount['sort_order']]);}
        }
        $db->commit();
        $_SESSION['msg']='Presupuesto duplicado como versión '.$newVersion.'. Ya podés editar la copia.';
        header('Location:index.php?a=edit_quote&id='.$newId);exit;
    }catch(Throwable $ex){
        if(isset($db)&&$db->inTransaction())$db->rollBack();
        $_SESSION['msg']='No se pudo duplicar: '.$ex->getMessage();
        header('Location:index.php?a=quotes');exit;
    }
}

if(in_array($a,['save_quote','update_quote'],true)){
    session_start();
    $cfg=require $root.'/config.php';
    date_default_timezone_set($cfg['timezone']??'America/Argentina/Buenos_Aires');
    if(empty($_SESSION['user'])){header('Location:index.php');exit;}
    if(($_SESSION['user']['role']??'')!=='admin'){http_response_code(403);exit('No autorizado.');}
    if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Solicitud vencida.');}
    $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);q12EnsureVersioning($db);
    try{
        $responsibleUserId=(int)($_SESSION['user']['id']??0);
        $userCheck=$db->prepare('SELECT id FROM users WHERE id=? AND active=1');
        $userCheck->execute([$responsibleUserId]);
        if(!$userCheck->fetchColumn())throw new RuntimeException('La sesión pertenece a un usuario que ya no existe. Cerrá sesión e ingresá nuevamente.');
        if(!(bool)$db->query("SHOW COLUMNS FROM quotes LIKE 'proposal_name'")->fetch())$db->exec("ALTER TABLE quotes ADD COLUMN proposal_name VARCHAR(150) NULL AFTER quote_category");
        if(!(bool)$db->query("SHOW COLUMNS FROM quote_items LIKE 'is_manual'")->fetch())throw new RuntimeException('Falta ejecutar la migración 2026_09_09_presupuestos_items_manuales.sql.');
        $qid=(int)($_POST['quote_id']??0);$pid=(int)($_POST['project_id']??0);$clientId=(int)($_POST['client_id']??0);$listId=(int)($_POST['price_list_id']??0);
        $s=$db->prepare('SELECT p.*,c.id client_id FROM projects p JOIN clients c ON c.id=p.client_id WHERE p.id=? AND c.id=?');$s->execute([$pid,$clientId]);$project=$s->fetch();if(!$project)throw new Exception('Seleccioná un proyecto válido.');
        $families=array_values(array_intersect(['lifesmart','control4','shelly'],$_POST['quote_families']??[]));if(!$families)$families=['lifesmart'];$template=$_POST['quote_template_family']??'';if(!in_array($template,$families,true))$template=$families[0];
        $s=$db->prepare('SELECT id,name,markup_percentage FROM price_lists WHERE id=? AND active=1');$s->execute([$listId]);$list=$s->fetch();if(!$list)throw new Exception('Seleccioná una lista de precios.');$defaultMarkup=(float)$list['markup_percentage'];
        $hasOverrides=true;try{$db->query('SELECT 1 FROM product_price_overrides LIMIT 1');}catch(Throwable $x){$hasOverrides=false;}
        $prodQ=$db->prepare("SELECT p.id,p.sku,p.description,p.unit,p.cost_usd,COALESCE(pc.name,'Otros') category FROM products p LEFT JOIN product_categories pc ON pc.id=p.category_id WHERE p.id=? AND p.active=1");
        $ovQ=$hasOverrides?$db->prepare('SELECT markup_percentage FROM product_price_overrides WHERE product_id=? AND price_list_id=?'):null;
        $items=[];$materials=0;$sort=0;
        foreach($_POST['items']??[] as $r){
            $qty=max(1,(int)round((float)($r['quantity']??1)));$posted=$r['unit_price']??null;$unit=(is_numeric($posted)&&(float)$posted>=0)?round((float)$posted,2):0;
            $section=trim((string)($r['section_title']??'Materiales'))?:'Materiales';$order=(int)($r['block_order']??0);$manual=!empty($r['is_manual']);$discount=max(0,min(100,round((float)($r['discount_pct']??0),2)));
            if($manual){
                $sku=trim((string)($r['sku']??''));$desc=trim((string)($r['description']??''));$u=trim((string)($r['unit']??'unidad'))?:'unidad';
                if($sku===''&&$desc==='')continue;if($desc==='')$desc='Ítem manual';
                $sub=round($qty*$unit*(1-$discount/100),2);$materials+=$sub;
                $items[]=[null,1,'Electricidad','Otro',$section,$order,$sku,$desc,$u,$qty,$unit,$discount,null,$sub,$sort++];
                continue;
            }
            $productId=(int)($r['product_id']??0);if(!$productId)continue;
            $prodQ->execute([$productId]);$p=$prodQ->fetch();if(!$p)continue;
            $markup=$defaultMarkup;if($ovQ){$ovQ->execute([$productId,$listId]);$ov=$ovQ->fetchColumn();if($ov!==false)$markup=(float)$ov;}
            $defaultUnit=round((float)$p['cost_usd']*(1+$markup/100),2);if(!is_numeric($posted))$unit=$defaultUnit;
            $sub=round($qty*$unit*(1-$discount/100),2);$materials+=$sub;
            $items[]=[$productId,0,$p['category'],q12Brand((string)$p['category']),$section,$order,$p['sku'],$p['description'],$p['unit'],$qty,$unit,$discount,$listId,$sub,$sort++];
        }
        if(!$items)throw new Exception('Agregá al menos un producto o un ítem manual.');
        $laborBlocks=[];$labor=0;$labTotal=0;
        foreach($_POST['labor_blocks']??[] as $r){$amount=max(0,(float)($r['amount']??0));$title=trim((string)($r['title']??'Mano de obra'))?:'Mano de obra';$desc=trim((string)($r['description']??''))?:'Configuración, montaje y diseño de escenas';$mode=in_array(($r['tax_mode']??'sin_iva'),['sin_iva','mas_iva','iva_incluido'],true)?$r['tax_mode']:'sin_iva';$vat=(float)($r['vat_rate']??21);$order=(int)($r['block_order']??0);if($amount<=0)continue;$labor+=$amount;$labTotal+=($mode==='mas_iva'?round($amount*(1+$vat/100),2):$amount);$laborBlocks[]=[$title,$desc,$amount,$mode,$vat,$order];}
        $matMode=in_array(($_POST['materials_tax_mode']??'mas_iva'),['sin_iva','mas_iva','iva_incluido'],true)?$_POST['materials_tax_mode']:'mas_iva';$matVat=(float)($_POST['materials_vat_rate']??21);$matTotal=$matMode==='mas_iva'?round($materials*(1+$matVat/100),2):$materials;$total=round($matTotal+$labTotal,2);
        $version=1;$seriesKey='';
        if($a==='save_quote'){$seriesKey=bin2hex(random_bytes(16));}
        else{
            $existing=$db->prepare('SELECT quote_series_key,status,version_no FROM quotes WHERE id=?');$existing->execute([$qid]);$currentQuote=$existing->fetch();
            if(!$currentQuote)throw new Exception('Presupuesto inválido.');
            if(($currentQuote['status']??'borrador')!=='borrador')throw new Exception('El presupuesto ya fue enviado y está bloqueado. Duplicalo para generar una nueva versión.');
            $seriesKey=(string)$currentQuote['quote_series_key'];$version=(int)$currentQuote['version_no'];
        }
        $date=$_POST['quote_date']??date('Y-m-d');$status=$_POST['status']??'borrador';$category=$_POST['quote_category']??'general';$proposalName=mb_substr(trim((string)($_POST['proposal_name']??'')),0,150);if($proposalName==='')throw new Exception('Ingresá el nombre del presupuesto.');$notes=trim((string)($_POST['notes']??''));$laborDesc=count($laborBlocks)===1?$laborBlocks[0][1]:(count($laborBlocks)>1?'Mano de obra por sectores':'');
        $db->beginTransaction();
        if($a==='update_quote'){
            if(!$qid)throw new Exception('Presupuesto inválido.');
            $db->prepare('UPDATE quotes SET project_id=?,quote_series_key=?,version_no=?,quote_category=?,proposal_name=?,currency="USD",quote_date=?,quote_families=?,quote_template_family=?,price_list_id=?,materials_amount=?,labor_amount=?,labor_description=?,subtotal=?,tax_mode="sin_iva",vat_rate=21,materials_tax_mode=?,materials_vat_rate=?,labor_tax_mode="sin_iva",labor_vat_rate=21,total=?,status=?,notes=? WHERE id=?')->execute([$pid,$seriesKey,$version,$category,$proposalName!==''?$proposalName:null,$date,implode(',',$families),$template,$listId,$materials,$labor,$laborDesc,$materials+$labor,$matMode,$matVat,$total,$status,$notes,$qid]);
            $db->prepare('DELETE FROM quote_items WHERE quote_id=?')->execute([$qid]);$db->prepare('DELETE FROM quote_labor_items WHERE quote_id=?')->execute([$qid]);
        }else{
            $db->prepare('INSERT INTO quotes(project_id,quote_series_key,version_no,quote_category,proposal_name,currency,quote_date,quote_families,quote_template_family,price_list_id,materials_amount,labor_amount,labor_description,subtotal,tax_mode,vat_rate,materials_tax_mode,materials_vat_rate,labor_tax_mode,labor_vat_rate,total,status,notes,responsible_user_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$pid,$seriesKey,$version,$category,$proposalName!==''?$proposalName:null,'USD',$date,implode(',',$families),$template,$listId,$materials,$labor,$laborDesc,$materials+$labor,'sin_iva',21,$matMode,$matVat,'sin_iva',21,$total,$status,$notes,$responsibleUserId]);$qid=(int)$db->lastInsertId();
        }
        $ins=$db->prepare('INSERT INTO quote_items(quote_id,product_id,is_manual,category,brand,section_title,block_order,sku,description,unit,quantity,unit_price,discount_pct,price_list_id,subtotal,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');foreach($items as $r)$ins->execute(array_merge([$qid],$r));
        $li=$db->prepare('INSERT INTO quote_labor_items(quote_id,title,description,amount,tax_mode,vat_rate,block_order) VALUES(?,?,?,?,?,?,?)');foreach($laborBlocks as $r)$li->execute(array_merge([$qid],$r));
        if($status==='enviado'){
            $next=q12FollowupDate($date);
            $db->prepare('UPDATE quotes SET sent_at=COALESCE(sent_at,?),responsible_user_id=COALESCE(responsible_user_id,?),next_followup_date=COALESCE(next_followup_date,?),reminder_sent_at=NULL,followup_closed_at=NULL WHERE id=?')->execute([$date,$responsibleUserId,$next,$qid]);
            $db->prepare("INSERT INTO quote_followup_history(quote_id,user_id,event_type,next_contact_date,notes) SELECT ?,?,'enviado',?,'Presupuesto marcado como enviado' WHERE NOT EXISTS (SELECT 1 FROM quote_followup_history WHERE quote_id=? AND event_type='enviado' AND DATE(event_date)=?)")->execute([$qid,$responsibleUserId,$next,$qid,$date]);
        }elseif(in_array($status,['aprobado_inicial','aprobado_definitivo','final','rechazado'],true)){
            $db->prepare('UPDATE quotes SET next_followup_date=NULL,followup_closed_at=COALESCE(followup_closed_at,NOW()) WHERE id=?')->execute([$qid]);
        }
        if($status==='aprobado_inicial'){$pct=(float)$project['engineering_pct'];$amt=round($total*$pct/100,2);$db->prepare("INSERT INTO charges(project_id,quote_id,charge_date,type,currency,description,amount) VALUES(?,?,?,'ingenieria','USD',?,?)")->execute([$pid,$qid,$date,'Adelanto de ingeniería '.$pct.'% · v'.$version,$amt]);$eng=(int)$db->lastInsertId();q12ReplaceCharge($db,$pid,$eng,['ingenieria']);$db->prepare("UPDATE projects SET status='aprobado' WHERE id=?")->execute([$pid]);}
        if(in_array($status,['aprobado_definitivo','final'],true)){$db->prepare("INSERT INTO charges(project_id,quote_id,charge_date,type,currency,description,amount) VALUES(?,?,?,'materiales','USD',?,?)")->execute([$pid,$qid,$date,'Materiales · presupuesto final v'.$version,$matTotal]);$mat=(int)$db->lastInsertId();q12ReplaceCharge($db,$pid,$mat,['materiales']);if($labTotal>0){$db->prepare("INSERT INTO charges(project_id,quote_id,charge_date,type,currency,description,amount) VALUES(?,?,?,'mano_obra','USD',?,?)")->execute([$pid,$qid,$date,'Mano de obra · presupuesto final v'.$version,$labTotal]);$labId=(int)$db->lastInsertId();q12ReplaceCharge($db,$pid,$labId,['ingenieria','mano_obra']);}$db->prepare("UPDATE projects SET status='en_obra' WHERE id=?")->execute([$pid]);}
        $db->commit();$_SESSION['msg']=$a==='update_quote'?'Presupuesto actualizado.':'Presupuesto creado. Podés seguir editándolo o generar el PDF cuando quieras.';$afterSave=(string)($_POST['after_save']??'edit');$destination=$afterSave==='pdf'?'quote_view':'edit_quote';header('Location:index.php?a='.$destination.'&id='.$qid);exit;
    }catch(Throwable $ex){if(isset($db)&&$db->inTransaction())$db->rollBack();$_SESSION['msg']='No se pudo guardar: '.$ex->getMessage();header('Location:index.php?a='.($a==='update_quote'?'edit_quote&id='.$qid:'new_quote'));exit;}
}

$manualRows=[];$currentCategory='';$nextVersions=[];
try{
    $cfg=require $root.'/config.php';
    $db12=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);q12EnsureVersioning($db12);
    foreach($db12->query('SELECT project_id,COALESCE(MAX(version_no),0)+1 next_version FROM quotes GROUP BY project_id') as $r)$nextVersions[(int)$r['project_id']]=(int)$r['next_version'];
    if($a==='edit_quote'){$qid=(int)($_GET['id']??0);$s=$db12->prepare('SELECT quote_category FROM quotes WHERE id=?');$s->execute([$qid]);$currentCategory=(string)($s->fetchColumn()?:'');$s=$db12->prepare('SELECT block_order,section_title,sku,description,unit,quantity,unit_price,discount_pct,sort_order FROM quote_items WHERE quote_id=? AND (is_manual=1 OR product_id IS NULL) ORDER BY block_order,sort_order,id');$s->execute([$qid]);$manualRows=$s->fetchAll();}
}catch(Throwable $e){}

ob_start();require __DIR__.'/module_v10.php';$html=ob_get_clean();
$manualJson=json_encode($manualRows,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$currentCategoryJson=json_encode($currentCategory,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$nextVersionsJson=json_encode($nextVersions,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$editingJson=json_encode($a==='edit_quote');
$inject=<<<HTML
<script>
(function(){
 const rubro=document.querySelector('select[name="quote_category"]');if(rubro&&![...rubro.options].some(o=>o.value==='electricidad')){const o=document.createElement('option');o.value='electricidad';o.textContent='Electricidad';rubro.insertBefore(o,rubro.querySelector('option[value="general"]')||null);}const currentCategory=$currentCategoryJson;if(rubro&&currentCategory)rubro.value=currentCategory;
 const nextVersions=$nextVersionsJson,isEditing=$editingJson,project=document.getElementById('project'),version=document.querySelector('input[name="version_no"]');if(project&&version&&!isEditing)project.addEventListener('change',()=>{const id=Number(project.value);version.value=nextVersions[id]||1;});
 function escm(s){return String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}
 window.addManualItem=function(block,data={}){const i=itemIndex++,tr=document.createElement('tr');tr.dataset.manual='1';tr.innerHTML=`<td><input type="hidden" name="items[\${i}][is_manual]" value="1"><input class="section-title-hidden" type="hidden" name="items[\${i}][section_title]"><input class="block-order-hidden" type="hidden" name="items[\${i}][block_order]"><input class="sku-input" name="items[\${i}][sku]" value="\${escm(data.sku||'')}" placeholder="Ej.: 01"></td><td><input class="desc-input" name="items[\${i}][description]" value="\${escm(data.description||'')}" placeholder="Ej.: Cambio de plafones de iluminación"></td><td><input name="items[\${i}][unit]" value="\${escm(data.unit||'unidad')}" placeholder="unidad" style="min-width:90px"></td><td><input class="qty" type="number" min="1" step="1" name="items[\${i}][quantity]" value="\${Number(data.quantity||1)}" oninput="calcTotals()"></td><td class="right"><input class="unit-price" type="number" min="0" step=".01" name="items[\${i}][unit_price]" value="\${Number(data.unit_price??0).toFixed(2)}" oninput="calcTotals()"></td><td><input class="item-discount" type="number" min="0" max="100" step=".01" name="items[\${i}][discount_pct]" value="\${Number(data.discount_pct??0).toFixed(2)}" oninput="calcTotals()"></td><td class="right sub">—</td><td><button class="btn danger" type="button" onclick="this.closest('tr').remove();calcTotals()">×</button></td>`;block.querySelector('.item-body').appendChild(tr);renumberBlocks();calcTotals();};
 function addManualButton(block){const btn=block.querySelector('.block-body > .btn.light');if(btn&&!block.querySelector('.manual-add')){const m=document.createElement('button');m.type='button';m.className='btn light manual-add';m.style.marginLeft='8px';m.textContent='+ Ítem manual';m.onclick=()=>addManualItem(block);btn.insertAdjacentElement('afterend',m);}}
 document.querySelectorAll('.material-block').forEach(addManualButton);const oldAdd=window.addMaterialBlock;if(typeof oldAdd==='function')window.addMaterialBlock=function(){oldAdd();const b=document.querySelector('#blocks .material-block:last-of-type');if(b)addManualButton(b);};
 if(isEditing){document.querySelectorAll('.material-block tr').forEach(tr=>{const pid=tr.querySelector('.product-id');if(pid&&!pid.value)tr.remove();});}
 const rows=$manualJson;rows.forEach(r=>{let block=[...document.querySelectorAll('.material-block')].find(b=>(b.querySelector('.block-title')?.value||'')===(r.section_title||''));if(!block)block=document.querySelector('.material-block');if(block)addManualItem(block,r);});
 const originalCalc=window.calcTotals;window.calcTotals=function(){let mat=0;document.querySelectorAll('.material-block tr').forEach(tr=>{const manual=tr.dataset.manual==='1',id=tr.dataset.product,qty=Number(tr.querySelector('.qty')?.value||0),price=Number(tr.querySelector('.unit-price')?.value||0),discount=Math.max(0,Math.min(100,Number(tr.querySelector('.item-discount')?.value||0))),valid=manual||!!id,sub=valid?qty*price*(1-discount/100):0;const c=tr.querySelector('.sub');if(c)c.textContent=valid?money(sub):'—';mat+=sub});let labor=0,laborTaxed=0;document.querySelectorAll('.labor-block').forEach(b=>{const a=Number(b.querySelector('.labor-amount').value||0),m=b.querySelector('.labor-tax').value,v=Number(b.querySelector('.labor-vat').value||21);labor+=a;laborTaxed+=m==='mas_iva'?a*(1+v/100):a});const mm=document.getElementById('materialsTax').value,mv=Number(document.getElementById('materialsVat').value||21),matTaxed=mm==='mas_iva'?mat*(1+mv/100):mat;document.getElementById('materialsTotal').textContent=money(matTaxed);document.getElementById('laborTotal').textContent=money(laborTaxed);document.getElementById('grandTotal').textContent=money(matTaxed+laborTaxed)};
 calcTotals();
})();
</script>
HTML;
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;
echo $html;
