<?php
declare(strict_types=1);
session_start();
$root=dirname(__DIR__,3);$cfg=require $root.'/config.php';
if(empty($_SESSION['user'])){header('Location:index.php');exit;}
$isAdmin=(($_SESSION['user']['role']??'')==='admin');if(!$isAdmin){http_response_code(403);exit('No autorizado.');}
$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$_SESSION['csrf']??=bin2hex(random_bytes(24));
$a=$_GET['a']??'';$projectId=(int)($_GET['project_id']??$_POST['project_id']??0);if($projectId<=0){http_response_code(400);exit('Proyecto inválido.');}
$p=$db->prepare('SELECT id,project_number,name FROM projects WHERE id=?');$p->execute([$projectId]);$project=$p->fetch();if(!$project){http_response_code(404);exit('Proyecto inexistente.');}
function ppFile(PDO $db,int $id,int $projectId):array{$s=$db->prepare('SELECT * FROM project_plan_files WHERE id=? AND project_id=?');$s->execute([$id,$projectId]);$r=$s->fetch();if(!$r){http_response_code(404);exit('Plano inexistente.');}return $r;}
if(in_array($a,['project_plan_view','project_plan_download'],true)){
 $f=ppFile($db,(int)($_GET['id']??0),$projectId);$full=$root.'/'.ltrim((string)$f['file_path'],'/');if(!is_file($full)){http_response_code(404);exit('Archivo no encontrado.');}
 header('Content-Type: application/pdf');header('Content-Length: '.filesize($full));$name=preg_replace('/[^A-Za-z0-9._-]+/','_',basename((string)$f['original_name']))?:'plano.pdf';header('Content-Disposition: '.($a==='project_plan_download'?'attachment':'inline').'; filename="'.$name.'"');readfile($full);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'&&$a==='upload_project_plans'){
 if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Solicitud vencida.');}
 $files=$_FILES['plans']??null;if(!$files||!is_array($files['name']??null)){$_SESSION['project_plans_msg']='Seleccioná al menos un PDF.';header('Location:?a=project&id='.$projectId);exit;}
 $dir=$root.'/storage/uploads/project_plans/'.$projectId;if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir)){$_SESSION['project_plans_msg']='No se pudo crear la carpeta para planos.';header('Location:?a=project&id='.$projectId);exit;}
 $saved=0;$errors=[];$count=count($files['name']);
 for($i=0;$i<$count;$i++){
  if(($files['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;if(($files['error'][$i]??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK){$errors[]='No se pudo recibir '.$files['name'][$i];continue;}
  if((int)$files['size'][$i]>25*1024*1024){$errors[]=$files['name'][$i].' supera 25 MB';continue;}
  $tmp=(string)$files['tmp_name'][$i];$mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);if($mime!=='application/pdf'){$errors[]=$files['name'][$i].' no es PDF';continue;}
  $safe='plano-'.date('Ymd-His').'-'.bin2hex(random_bytes(5)).'.pdf';$full=$dir.'/'.$safe;if(!move_uploaded_file($tmp,$full)){$errors[]='No se pudo guardar '.$files['name'][$i];continue;}
  $rel='storage/uploads/project_plans/'.$projectId.'/'.$safe;$s=$db->prepare('INSERT INTO project_plan_files(project_id,file_path,original_name,mime_type,file_size,source,uploaded_by) VALUES(?,?,?,?,?,"manual",?)');$s->execute([$projectId,$rel,(string)$files['name'][$i],'application/pdf',(int)$files['size'][$i],(int)($_SESSION['user']['id']??0)]);$saved++;
 }
 $_SESSION['project_plans_msg']=$saved.' plano(s) subido(s).'.($errors?' '.implode(' · ',$errors):'');header('Location:?a=project&id='.$projectId);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'&&$a==='delete_project_plan'){
 if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Solicitud vencida.');}
 $f=ppFile($db,(int)($_POST['id']??0),$projectId);$full=$root.'/'.ltrim((string)$f['file_path'],'/');$db->prepare('DELETE FROM project_plan_files WHERE id=? AND project_id=?')->execute([(int)$f['id'],$projectId]);if(is_file($full))@unlink($full);$_SESSION['project_plans_msg']='Plano eliminado.';header('Location:?a=project&id='.$projectId);exit;
}
http_response_code(400);exit('Acción inválida.');
