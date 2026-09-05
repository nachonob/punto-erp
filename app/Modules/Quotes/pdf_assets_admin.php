<?php
declare(strict_types=1);
session_start();
$root=dirname(__DIR__,3);
$cfg=require $root.'/config.php';
if(empty($_SESSION['user'])){header('Location:index.php');exit;}
if(($_SESSION['user']['role']??'')!=='admin'){http_response_code(403);exit('Solo administrador.');}
$_SESSION['csrf']??=bin2hex(random_bytes(24));
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$base=$root.'/storage/uploads/pdf_assets';
$families=['lifesmart'=>'LifeSmart','control4'=>'Control4','shelly'=>'Shelly'];
$types=['prologo'=>'Prólogo','pago'=>'Forma de pago'];
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($_SESSION['csrf'],$_POST['csrf']??'')){http_response_code(419);exit('Solicitud vencida.');}
 $family=(string)($_POST['family']??'');$type=(string)($_POST['type']??'');
 if(!isset($families[$family],$types[$type])){$msg='Selección inválida.';}
 elseif(empty($_FILES['pdf']['tmp_name'])||!is_uploaded_file($_FILES['pdf']['tmp_name'])){$msg='Seleccioná un PDF.';}
 else{
  $fh=fopen($_FILES['pdf']['tmp_name'],'rb');$magic=fread($fh,5);fclose($fh);
  if($magic!=='%PDF-'){$msg='El archivo no es un PDF válido.';}
  elseif((int)$_FILES['pdf']['size']>20*1024*1024){$msg='El PDF supera 20 MB.';}
  else{
   $dir=$base.'/'.$type;if(!is_dir($dir)&&!mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No pude crear el directorio.');
   $dest=$dir.'/'.$family.'.pdf';
   if(!move_uploaded_file($_FILES['pdf']['tmp_name'],$dest))throw new RuntimeException('No pude guardar el PDF.');
   $msg=$types[$type].' de '.$families[$family].' guardado correctamente.';
  }
 }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>PDF de presupuestos · Punto ERP</title><style>body{font:15px/1.45 system-ui;background:#f4f6f8;color:#27303b;margin:0}.wrap{max-width:900px;margin:40px auto;padding:0 18px}.card{background:#fff;border:1px solid #e1e5e9;border-radius:14px;padding:22px;margin-bottom:18px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.btn{background:#ff6702;color:#fff;border:0;border-radius:8px;padding:11px 16px;font-weight:700;cursor:pointer}.muted{color:#6e7781}.ok{padding:10px 12px;background:#fff0e5;border-radius:8px;margin-bottom:15px}.status{font-size:13px;margin-top:6px}@media(max-width:700px){.grid{grid-template-columns:1fr}}</style></head><body><div class="wrap"><div class="card"><a href="?a=quotes">← Presupuestos</a><h1>Archivos PDF del presupuesto</h1><p class="muted">Estos archivos quedan persistentes en el servidor y se usan automáticamente al generar el PDF final.</p><?php if($msg):?><div class="ok"><?=e($msg)?></div><?php endif;?><div class="grid"><?php foreach($families as $key=>$label):?><div class="card"><h2><?=e($label)?></h2><?php foreach($types as $type=>$typeLabel):$exists=is_file($base.'/'.$type.'/'.$key.'.pdf');?><form method="post" enctype="multipart/form-data" style="margin-bottom:18px"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="family" value="<?=e($key)?>"><input type="hidden" name="type" value="<?=e($type)?>"><b><?=e($typeLabel)?></b><div class="status"><?=$exists?'✓ Cargado':'Pendiente'?></div><input type="file" name="pdf" accept="application/pdf" required style="margin:9px 0"><br><button class="btn">Guardar <?=e(strtolower($typeLabel))?></button></form><?php endforeach;?></div><?php endforeach;?></div></div></div></body></html>