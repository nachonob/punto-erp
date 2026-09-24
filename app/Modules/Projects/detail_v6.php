<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);$cfg=require $root.'/config.php';$projectId=(int)($_GET['id']??0);$followup=null;
try{
 $dbFollowup=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 require_once $root.'/app/Core/ProjectFollowup.php';ensureProjectFollowupSchema($dbFollowup);
 $stmt=$dbFollowup->prepare("SELECT p.next_followup_date,p.followup_closed_at,u.name responsible_name,(SELECT COUNT(*) FROM quotes q WHERE q.project_id=p.id) quote_count,(SELECT h.notes FROM project_followup_history h WHERE h.project_id=p.id AND h.notes IS NOT NULL AND TRIM(h.notes)<>'' ORDER BY h.event_date DESC,h.id DESC LIMIT 1) last_note FROM projects p LEFT JOIN users u ON u.id=p.followup_responsible_user_id WHERE p.id=?");$stmt->execute([$projectId]);$followup=$stmt->fetch();
}catch(Throwable $ignored){}
ob_start();require __DIR__.'/detail_v5.php';$html=ob_get_clean();
$e=static fn($value):string=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');$today=date('Y-m-d');
if($followup){
 $isDue=empty($followup['followup_closed_at'])&&!empty($followup['next_followup_date'])&&$followup['next_followup_date']<=$today;
 if(!empty($followup['followup_closed_at']))$state='<span class="pill">Cerrado</span>';
 elseif(!empty($followup['next_followup_date']))$state='<span class="pill" style="'.($isDue?'background:#ffe5e5;color:#a92727':'').'">'.($isDue?'Contactar · ':'').$e(date('d/m/Y',strtotime($followup['next_followup_date']))).'</span>';
 else $state='<span class="muted">Se programa al enviar un presupuesto</span>';
 $note=trim((string)($followup['last_note']??''));
 $card='<section class="card" id="seguimiento-comercial"><div class="actions"><div style="flex:1"><h2 style="margin-bottom:4px">Seguimiento del cliente</h2><p class="muted" style="margin-top:0">Una sola gestión para todo el proyecto, que reúne '.(int)$followup['quote_count'].' presupuesto'.((int)$followup['quote_count']===1?'':'s').'.</p></div><a class="btn" href="?a=project_followup&id='.$projectId.'">Gestionar seguimiento</a><a class="btn light" href="?a=followup_agenda&project_id='.$projectId.'">Ver agenda</a></div><div class="grid" style="margin-top:16px"><div class="c4"><b>Próximo contacto</b><p>'.$state.'</p></div><div class="c4"><b>Responsable</b><p>'.$e($followup['responsible_name']?:'Sin asignar').'</p></div><div class="c4"><b>Última nota</b><p>'.($note!==''?$e($note):'<span class="muted">—</span>').'</p></div></div></section>';
 $marker='<div class="card"><div class="actions"><h2 style="flex:1">Presupuestos';$position=strpos($html,$marker);if($position!==false)$html=substr($html,0,$position).$card.substr($html,$position);elseif(str_contains($html,'</main>'))$html=str_replace('</main>',$card.'</main>',$html);else$html.=$card;
}
echo $html;
