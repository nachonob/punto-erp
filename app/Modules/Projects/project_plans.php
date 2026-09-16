<?php
declare(strict_types=1);
session_start();
$root=dirname(__DIR__,3);$cfg=require $root.'/config.php';
if(empty($_SESSION['user'])){header('Location:index.php');exit;}
$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$_SESSION['csrf']??=bin2hex(random_bytes(24));
$a=$_GET['a']??'';$projectId=(int)($_GET['project_id']??$_POST['project_id']??0);if($projectId<=0){http_response_code(400);exit('Proyecto inválido.');}
$p=$db->prepare('SELECT id,project_number,name FROM projects WHERE id=?');$p->execute([$projectId]);$project=$p->fetch();if(!$project){http_response_code(404);exit('Proyecto inexistente.');}

$isAdmin=(($_SESSION['user']['role']??'')==='admin');
$permissions=$_SESSION['user']['permissions']??[];
$canManage=$isAdmin||!empty($permissions['projects']['manage']);
$canView=$canManage||!empty($permissions['projects']['view']);
if(!$canView){
 try{$s=$db->prepare('SELECT COUNT(*) FROM project_technical_users WHERE project_id=? AND user_id=?');$s->execute([$projectId,(int)($_SESSION['user']['id']??0)]);$canView=(bool)$s->fetchColumn();}catch(Throwable $e){}
}
if(!$canView){http_response_code(403);exit('No autorizado.');}

try{if(!(bool)$db->query("SHOW COLUMNS FROM project_plan_files LIKE 'file_kind'")->fetch())$db->exec("ALTER TABLE project_plan_files ADD COLUMN file_kind VARCHAR(10) NOT NULL DEFAULT 'pdf' AFTER project_id");}catch(Throwable $e){}

function ppFile(PDO $db,int $id,int $projectId):array{$s=$db->prepare('SELECT * FROM project_plan_files WHERE id=? AND project_id=?');$s->execute([$id,$projectId]);$r=$s->fetch();if(!$r){http_response_code(404);exit('Plano inexistente.');}return $r;}
function ppBack(int $projectId):never{header('Location:?a=project&id='.$projectId);exit;}
function ppSafeName(string $name,string $fallback):string{$safe=preg_replace('/[^A-Za-z0-9._-]+/','_',basename($name));return $safe?:$fallback;}

if(in_array($a,['project_plan_view','project_plan_download'],true)){
 $f=ppFile($db,(int)($_GET['id']??0),$projectId);$full=$root.'/'.ltrim((string)$f['file_path'],'/');if(!is_file($full)){http_response_code(404);exit('Archivo no encontrado.');}
 $kind=(string)($f['file_kind']??'pdf');$mime=$kind==='pdf'?'application/pdf':'application/octet-stream';
 $name=ppSafeName((string)$f['original_name'],$kind==='pdf'?'plano.pdf':'plano-cad');
 $inline=$a==='project_plan_view'&&$kind==='pdf';
 header('X-Content-Type-Options: nosniff');header('Content-Type: '.$mime);header('Content-Length: '.filesize($full));header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="'.$name.'"');readfile($full);exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'&&in_array($a,['upload_project_plans','upload_project_cad'],true)){
 if(!$canManage){http_response_code(403);exit('No autorizado.');}
 if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Solicitud vencida.');}
 $cad=$a==='upload_project_cad';$field=$cad?'cad_plans':'plans';$files=$_FILES[$field]??null;
 if(!$files||!is_array($files['name']??null)){$_SESSION['project_plans_msg']='Seleccioná al menos un archivo.';ppBack($projectId);}
 $dir=$root.'/storage/uploads/project_plans/'.$projectId;if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir)){$_SESSION['project_plans_msg']='No se pudo crear la carpeta para planos.';ppBack($projectId);}
 $saved=0;$errors=[];$count=count($files['name']);$allowedCad=['dwg','dxf','dwf','zip'];
 for($i=0;$i<$count;$i++){
  $original=(string)($files['name'][$i]??'archivo');
  if(($files['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)continue;
  if(($files['error'][$i]??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK){$errors[]='No se pudo recibir '.$original;continue;}
  $limit=($cad?50:25)*1024*1024;if((int)$files['size'][$i]>$limit){$errors[]=$original.' supera '.($cad?'50':'25').' MB';continue;}
  $tmp=(string)$files['tmp_name'][$i];$extension=strtolower(pathinfo($original,PATHINFO_EXTENSION));$mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp)?:'application/octet-stream';
  if(!$cad&&($extension!=='pdf'||$mime!=='application/pdf')){$errors[]=$original.' no es PDF';continue;}
  if($cad&&!in_array($extension,$allowedCad,true)){$errors[]=$original.' no es DWG, DXF, DWF ni ZIP';continue;}
  $safe=($cad?'cad-':'plano-').date('Ymd-His').'-'.bin2hex(random_bytes(5)).'.'.$extension;$full=$dir.'/'.$safe;
  if(!move_uploaded_file($tmp,$full)){$errors[]='No se pudo guardar '.$original;continue;}
  $rel='storage/uploads/project_plans/'.$projectId.'/'.$safe;
  $s=$db->prepare('INSERT INTO project_plan_files(project_id,file_kind,file_path,original_name,mime_type,file_size,source,uploaded_by) VALUES(?,?,?,?,?,?,"manual",?)');
  $s->execute([$projectId,$cad?'cad':'pdf',$rel,$original,$cad?'application/octet-stream':'application/pdf',(int)$files['size'][$i],(int)($_SESSION['user']['id']??0)]);$saved++;
 }
 $_SESSION['project_plans_msg']=$saved.' archivo(s) subido(s).'.($errors?' '.implode(' · ',$errors):'');ppBack($projectId);
}

if($_SERVER['REQUEST_METHOD']==='POST'&&$a==='delete_project_plan'){
 if(!$canManage){http_response_code(403);exit('No autorizado.');}
 if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Solicitud vencida.');}
 $f=ppFile($db,(int)($_POST['id']??0),$projectId);$full=$root.'/'.ltrim((string)$f['file_path'],'/');$db->prepare('DELETE FROM project_plan_files WHERE id=? AND project_id=?')->execute([(int)$f['id'],$projectId]);if(is_file($full))@unlink($full);$_SESSION['project_plans_msg']='Plano eliminado.';ppBack($projectId);
}
http_response_code(400);exit('Acción inválida.');
