<?php
declare(strict_types=1);
session_start();
$root=dirname(__DIR__,3);$cfg=require $root.'/config.php';
$projectId=(int)($_GET['id']??0);$plans=[];$plansReady=false;$plansMsg=$_SESSION['project_plans_msg']??null;unset($_SESSION['project_plans_msg']);
$isAdmin=(($_SESSION['user']['role']??'')==='admin');
if($isAdmin&&$projectId>0){
 try{
  $dbPlans=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  $plansReady=(bool)$dbPlans->query("SHOW TABLES LIKE 'project_plan_files'")->fetch();
  if($plansReady){
   // Importa una sola vez el PDF histórico que llegó desde Cotizar proyecto, si existiera.
   $ps=$dbPlans->prepare('SELECT notes FROM projects WHERE id=?');$ps->execute([$projectId]);$notes=(string)($ps->fetchColumn()?:'');
   if(preg_match('/Solicitud\s+(WEB-[0-9]{4}-[0-9]+)/i',$notes,$m)){
    $wr=$dbPlans->prepare('SELECT plans_file FROM web_quote_requests WHERE request_number=? AND plans_file IS NOT NULL AND plans_file<>"" LIMIT 1');$wr->execute([$m[1]]);$legacy=(string)($wr->fetchColumn()?:'');
    if($legacy!==''){
     $exists=$dbPlans->prepare('SELECT COUNT(*) FROM project_plan_files WHERE project_id=? AND source="web"');$exists->execute([$projectId]);
     if(!(int)$exists->fetchColumn()){
      $publicHtml=dirname($root);$src=$publicHtml.'/'.ltrim($legacy,'/');
      if(is_file($src)){$dir=$root.'/storage/uploads/project_plans/'.$projectId;if((is_dir($dir)||mkdir($dir,0775,true))){$safe='web-'.date('Ymd-His').'-'.bin2hex(random_bytes(5)).'.pdf';$dst=$dir.'/'.$safe;if(@copy($src,$dst)){$dbPlans->prepare('INSERT INTO project_plan_files(project_id,file_path,original_name,mime_type,file_size,source,uploaded_by) VALUES(?,?,?,?,?,"web",NULL)')->execute([$projectId,'storage/uploads/project_plans/'.$projectId.'/'.$safe,'Planos enviados desde formulario web.pdf','application/pdf',filesize($dst)]);}}}
     }
    }
   }
   $s=$dbPlans->prepare('SELECT * FROM project_plan_files WHERE project_id=? ORDER BY created_at DESC,id DESC');$s->execute([$projectId]);$plans=$s->fetchAll();
  }
 }catch(Throwable $e){$plansReady=false;}
}
session_write_close();
ob_start();require __DIR__.'/detail_v3.php';$html=ob_get_clean();
if($isAdmin&&$projectId>0){
 $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
 if(!$plansReady){$card='<section class="card"><h2>Planos del proyecto</h2><p class="muted">Falta habilitar el almacenamiento de planos en la base DEV.</p></section>';}
 else{
  $rows='';foreach($plans as $p){$id=(int)$p['id'];$src=$p['source']==='web'?'Formulario web':'Carga manual';$rows.='<div style="display:flex;gap:12px;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid #e5e7eb"><div><b>📄 '.$e($p['original_name']).'</b><div class="muted" style="font-size:13px">'.$e($src).' · '.$e(date('d/m/Y H:i',strtotime($p['created_at']))).'</div></div><div class="actions"><a class="btn light" target="_blank" href="?a=project_plan_view&project_id='.$projectId.'&id='.$id.'">Ver</a><a class="btn light" href="?a=project_plan_download&project_id='.$projectId.'&id='.$id.'">Descargar</a><form method="post" action="?a=delete_project_plan" onsubmit="return confirm(\'¿Eliminar este plano?\')" style="display:inline"><input type="hidden" name="csrf" value="'.$e($_SESSION['csrf']??'').'"><input type="hidden" name="project_id" value="'.$projectId.'"><input type="hidden" name="id" value="'.$id.'"><button class="btn danger" type="submit">Eliminar</button></form></div></div>';}
  if($rows==='')$rows='<p class="muted">Este proyecto todavía no tiene planos cargados.</p>';
  $flash=$plansMsg?'<div style="padding:10px 12px;margin-bottom:12px;background:#eef8f1;border:1px solid #b9dfc5;border-radius:9px">'.$e($plansMsg).'</div>':'';
  $card='<section class="card"><div class="actions"><div style="flex:1"><h2>Planos del proyecto</h2><p class="muted">Podés guardar varios archivos PDF por proyecto.</p></div></div>'.$flash.$rows.'<form method="post" enctype="multipart/form-data" action="?a=upload_project_plans" style="margin-top:18px;padding-top:16px;border-top:1px solid #e5e7eb"><input type="hidden" name="csrf" value="'.$e($_SESSION['csrf']??'').'"><input type="hidden" name="project_id" value="'.$projectId.'"><label style="display:block;font-weight:700;margin-bottom:7px">Agregar planos PDF</label><input type="file" name="plans[]" accept="application/pdf,.pdf" multiple required><div class="muted" style="margin:6px 0 10px">Podés seleccionar varios PDF a la vez. Máximo 25 MB por archivo.</div><button class="btn" type="submit">Subir planos</button></form></section>';
 }
 if(str_contains($html,'</main>'))$html=str_replace('</main>',$card.'</main>',$html);else$html.=$card;
}
echo $html;
