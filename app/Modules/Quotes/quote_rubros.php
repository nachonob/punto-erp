<?php
declare(strict_types=1);

if(session_status()!==PHP_SESSION_ACTIVE)session_start();
$root=dirname(__DIR__,3);
$cfg=require $root.'/config.php';
date_default_timezone_set($cfg['timezone']??'America/Argentina/Buenos_Aires');
$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
]);
if(empty($_SESSION['user'])){header('Location:index.php?a=dashboard');exit;}
if(($_SESSION['user']['role']??'')!=='admin'){
    $permissions=$_SESSION['user']['permissions']??[];
    if(empty($permissions['projects']['manage'])&&empty($permissions['sales_quotes']['manage'])){
        http_response_code(403);
        exit('Tu perfil no permite administrar rubros de presupuestos.');
    }
}
$_SESSION['csrf']??=bin2hex(random_bytes(24));
function qrE($value):string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function qrCsrf():void{if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('Solicitud vencida.');}}
function qrGo():never{header('Location:index.php?a=quote_rubros');exit;}

$db->exec("CREATE TABLE IF NOT EXISTS quote_rubros (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("INSERT IGNORE INTO quote_rubros(name,active) VALUES
    ('General',1),('Domótica',1),('Redes',1),('Cámaras',1),('Alarma',1),('Audio',1),('Electricidad',1)");

$action=$_GET['a']??'quote_rubros';
try{
    if($action==='save_quote_rubro'){
        qrCsrf();
        $name=mb_substr(trim((string)($_POST['name']??'')),0,150);
        if($name==='')throw new Exception('Ingresá el nombre del rubro.');
        try{$db->prepare('INSERT INTO quote_rubros(name,active) VALUES(?,1)')->execute([$name]);}
        catch(PDOException $e){throw new Exception('Ya existe un rubro con ese nombre.');}
        $_SESSION['msg']='Rubro creado. Ya está disponible en los presupuestos.';
        qrGo();
    }
    if($action==='update_quote_rubro'){
        qrCsrf();
        $id=(int)($_POST['rubro_id']??0);
        $name=mb_substr(trim((string)($_POST['name']??'')),0,150);
        $active=!empty($_POST['active'])?1:0;
        if(!$id||$name==='')throw new Exception('Completá el nombre del rubro.');
        $s=$db->prepare('SELECT name FROM quote_rubros WHERE id=?');
        $s->execute([$id]);
        $old=(string)($s->fetchColumn()?:'');
        if($old==='')throw new Exception('El rubro no existe.');
        $db->beginTransaction();
        $db->prepare('UPDATE quote_rubros SET name=?,active=? WHERE id=?')->execute([$name,$active,$id]);
        $db->prepare('UPDATE quotes SET quote_category=? WHERE quote_category=?')->execute([$name,$old]);
        $db->commit();
        $_SESSION['msg']='Rubro actualizado en los presupuestos.';
        qrGo();
    }
    if($action==='delete_quote_rubro'){
        qrCsrf();
        $id=(int)($_POST['rubro_id']??0);
        $s=$db->prepare('SELECT name FROM quote_rubros WHERE id=?');
        $s->execute([$id]);
        $name=(string)($s->fetchColumn()?:'');
        if($name==='')throw new Exception('El rubro no existe.');
        $s=$db->prepare('SELECT COUNT(*) FROM quotes WHERE quote_category=?');
        $s->execute([$name]);
        $used=(int)$s->fetchColumn();
        if($used>0){
            $db->prepare('UPDATE quote_rubros SET active=0 WHERE id=?')->execute([$id]);
            $_SESSION['msg']='El rubro estaba usado en '.$used.' presupuesto(s): se desactivó para conservar el historial.';
        }else{
            $db->prepare('DELETE FROM quote_rubros WHERE id=?')->execute([$id]);
            $_SESSION['msg']='Rubro eliminado.';
        }
        qrGo();
    }
}catch(Throwable $e){
    if($db->inTransaction())$db->rollBack();
    $_SESSION['msg']='No se pudo completar la operación: '.$e->getMessage();
    qrGo();
}

$rows=$db->query("SELECT r.*,COUNT(q.id) quote_count
    FROM quote_rubros r
    LEFT JOIN quotes q ON q.quote_category=r.name
    GROUP BY r.id
    ORDER BY r.active DESC,r.name")->fetchAll();
$message=$_SESSION['msg']??null;
unset($_SESSION['msg']);
require_once $root.'/app/Core/UnifiedSidebar.php';
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rubros de presupuestos · Punto ERP</title>
<style>
<?=erpSidebarCss()?>
body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.45 system-ui,-apple-system,Segoe UI,sans-serif}
.main{margin-left:var(--sidebar);padding:32px 4%}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:22px;margin-bottom:18px}
.grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:18px}
.actions{display:flex;gap:9px;align-items:center;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:8px;padding:10px 15px;background:var(--o);color:#fff;text-decoration:none;font-weight:700;cursor:pointer}
.btn.light{background:#edf0f3;color:var(--ink)}
.btn.danger{color:#a92727}
input{width:100%;padding:10px 11px;border:1px solid #cbd1d7;border-radius:8px;background:#fff;font:inherit;box-sizing:border-box}
input[type=checkbox]{width:auto}
label{display:block;font-weight:700;margin-bottom:6px}
table{width:100%;border-collapse:collapse}
th,td{text-align:left;padding:11px 9px;border-bottom:1px solid var(--line);vertical-align:middle}
th{font-size:12px;text-transform:uppercase;color:var(--muted)}
.muted{color:var(--muted)}
.flash{padding:12px 16px;background:#fff0e5;border:1px solid #ffd2b1;border-radius:9px;margin-bottom:18px}
.scroll{overflow:auto}
@media(max-width:900px){.main{margin-left:0;padding:22px 12px}.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php erpSidebar('quote_rubros');?>
<main class="main">
<div class="actions">
 <div style="flex:1"><span class="muted">Presupuestos · Configuración</span><h1>Rubros de presupuestos</h1><p class="muted">Los rubros clasifican el presupuesto y son independientes de la plantilla PDF, las categorías, las marcas y los productos.</p></div>
 <a class="btn light" href="index.php?a=quotes">Volver a presupuestos</a>
</div>
<?php if($message):?><div class="flash"><?=qrE($message)?></div><?php endif;?>
<div class="grid">
 <section class="card">
  <h2>Rubros existentes</h2>
  <div class="scroll"><table>
   <thead><tr><th>Nombre</th><th>Presupuestos</th><th>Estado</th><th>Acciones</th></tr></thead>
   <tbody>
   <?php foreach($rows as $row):$formId='rubro-'.$row['id'];?>
    <tr>
     <td>
      <form id="<?=$formId?>" method="post" action="index.php?a=update_quote_rubro">
       <input type="hidden" name="csrf" value="<?=qrE($_SESSION['csrf'])?>">
       <input type="hidden" name="rubro_id" value="<?=$row['id']?>">
      </form>
      <input form="<?=$formId?>" style="min-width:220px" name="name" value="<?=qrE($row['name'])?>" required>
     </td>
     <td><?=$row['quote_count']?></td>
     <td><label style="font-weight:400"><input form="<?=$formId?>" type="checkbox" name="active" value="1" <?=$row['active']?'checked':''?>> Activo</label></td>
     <td><div class="actions">
      <button form="<?=$formId?>" class="btn light">Guardar</button>
      <form method="post" action="index.php?a=delete_quote_rubro" onsubmit="return confirm('¿Eliminar este rubro? Si ya fue utilizado, se desactivará para conservar los presupuestos anteriores.')">
       <input type="hidden" name="csrf" value="<?=qrE($_SESSION['csrf'])?>">
       <input type="hidden" name="rubro_id" value="<?=$row['id']?>">
       <button class="btn light danger">Eliminar</button>
      </form>
     </div></td>
    </tr>
   <?php endforeach;?>
   </tbody>
  </table></div>
 </section>
 <aside class="card">
  <h2>Nuevo rubro</h2>
  <p class="muted">Ejemplos: Domótica, Electricidad, Redes, Seguridad o Audio.</p>
  <form method="post" action="index.php?a=save_quote_rubro">
   <input type="hidden" name="csrf" value="<?=qrE($_SESSION['csrf'])?>">
   <p><label>Nombre</label><input name="name" maxlength="150" placeholder="Ej.: Domótica" required></p>
   <button class="btn">Crear rubro</button>
  </form>
 </aside>
</div>
</main>
</body>
</html>
