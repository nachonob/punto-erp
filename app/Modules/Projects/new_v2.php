<?php
declare(strict_types=1);
session_start();
$root=dirname(__DIR__,3);$cfg=require $root.'/config.php';date_default_timezone_set($cfg['timezone']??'America/Argentina/Buenos_Aires');
$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
if(empty($_SESSION['user'])){header('Location:index.php');exit;}
$_SESSION['csrf']??=bin2hex(random_bytes(24));
require_once $root.'/app/Core/UnifiedSidebar.php';
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function go(string $u):never{header('Location:'.$u);exit;}
function csrf():void{if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Solicitud vencida.');}}
function isAdminProject():bool{return ($_SESSION['user']['role']??'')==='admin';}
function canManageProjects():bool{if(isAdminProject())return true;$p=$_SESSION['user']['permissions']??[];if(!$p)return false;return !empty($p['projects']['manage']);}
function formatProjectNumber(int $year,int $seq):string{return sprintf('%02d A %02d',$year%100,$seq);}
function currentPreview(PDO $db,int $year):string{
 $s=$db->prepare("SELECT next_number FROM document_sequences WHERE document_type='project' AND year_no=? LIMIT 1");$s->execute([$year]);$n=$s->fetchColumn();if($n===false)$n=$year===2026?50:1;return formatProjectNumber($year,(int)$n);
}
$a=$_GET['a']??'new_project';
if($a==='save_project'){
 if(!canManageProjects()){http_response_code(403);exit('No autorizado.');}csrf();
 try{
  $clientId=(int)($_POST['client_id']??0);$partnerId=(int)($_POST['partner_id']??0)?:null;$name=trim((string)($_POST['name']??''));$notes=trim((string)($_POST['notes']??''));
  if(!$clientId||$name==='')throw new Exception('Completá cliente y nombre del proyecto.');
  $year=(int)date('Y');
  $db->beginTransaction();
  $s=$db->prepare("SELECT id,next_number FROM document_sequences WHERE document_type='project' AND year_no=? FOR UPDATE");$s->execute([$year]);$seq=$s->fetch();
  if(!$seq){$start=$year===2026?50:1;$db->prepare("INSERT INTO document_sequences(document_type,year_no,next_number,prefix) VALUES('project',?,?, 'A')")->execute([$year,$start]);$seq=['id'=>(int)$db->lastInsertId(),'next_number'=>$start];}
  $number=formatProjectNumber($year,(int)$seq['next_number']);
  $db->prepare('UPDATE document_sequences SET next_number=next_number+1,prefix=\'A\' WHERE id=?')->execute([(int)$seq['id']]);
  $db->prepare("INSERT INTO projects(client_id,partner_id,project_number,name,status,currency,tax_mode,vat_rate,engineering_pct,notes) VALUES(?,?,?,?, 'consulta','USD','sin_iva',21,10,?)")->execute([$clientId,$partnerId,$number,$name,$notes]);
  $id=(int)$db->lastInsertId();$db->commit();$_SESSION['msg']='Proyecto '.$number.' creado.';go('index.php?a=project&id='.$id);
 }catch(Throwable $ex){if($db->inTransaction())$db->rollBack();$_SESSION['msg']='No se pudo crear el proyecto: '.$ex->getMessage();go('index.php?a=new_project');}
}
$clients=$db->query('SELECT id,business_name FROM clients WHERE active=1 ORDER BY business_name')->fetchAll();$partners=$db->query('SELECT id,business_name,partner_type FROM project_partners WHERE active=1 ORDER BY business_name')->fetchAll();$preview=currentPreview($db,(int)date('Y'));$msg=$_SESSION['msg']??null;unset($_SESSION['msg']);
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Nuevo proyecto · Punto ERP</title><style><?=erpSidebarCss()?>body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.45 system-ui}.main{margin-left:var(--sidebar);padding:32px 4%}.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:22px;margin-bottom:18px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:16px}.c4{grid-column:span 4}.c6{grid-column:span 6}.c8{grid-column:span 8}.c12{grid-column:span 12}label{display:block;font-weight:650;margin-bottom:6px}input,select,textarea{width:100%;padding:10px 11px;border:1px solid #cbd1d7;border-radius:8px;font:inherit;background:#fff}textarea{min-height:90px}.btn{border:0;border-radius:8px;padding:10px 15px;background:var(--o);color:#fff;text-decoration:none;font-weight:700;cursor:pointer}.muted{color:var(--muted)}.flash{padding:12px 16px;background:#fff0e5;border:1px solid #ffd2b1;border-radius:9px;margin-bottom:18px}.number{font-size:20px;font-weight:800;background:#f6f7f8}@media(max-width:900px){.main{margin-left:0;padding:22px}.c4,.c6,.c8{grid-column:span 12}}</style></head><body><?php erpSidebar('new_project');?><main class="main"><?php if($msg):?><div class="flash"><?=e($msg)?></div><?php endif;?><div class="card"><h1>Nuevo proyecto</h1><p class="muted">El número se genera automáticamente. Formato: año + A + correlativo. En 2026 comienza en 26 A 50; cada nuevo año vuelve a 01.</p><?php if(!$clients):?><p>Primero tenés que crear un cliente.</p><?php else:?><form method="post" action="?a=save_project"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><div class="grid"><p class="c6"><label>Cliente</label><select name="client_id" required><?php foreach($clients as $c):?><option value="<?=$c['id']?>"><?=e($c['business_name'])?></option><?php endforeach;?></select></p><p class="c6"><label>Arquitecto, estudio o constructora</label><select name="partner_id"><option value="">Sin asignar</option><?php foreach($partners as $p):?><option value="<?=$p['id']?>"><?=e($p['business_name'])?> · <?=e(str_replace('_',' ',$p['partner_type']))?></option><?php endforeach;?></select></p><p class="c4"><label>Próximo número</label><input class="number" value="<?=e($preview)?>" readonly></p><p class="c8"><label>Nombre del proyecto u obra</label><input name="name" required></p><p class="c12"><label>Notas</label><textarea name="notes"></textarea></p></div><button class="btn">Crear proyecto</button></form><?php endif;?></div></main></body></html>