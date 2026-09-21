<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);$cfg=require $root.'/config.php';$projectId=(int)($_GET['id']??0);
$financial=[];
try{
 $db5=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db5->exec("CREATE TABLE IF NOT EXISTS erp_data_migrations (
  migration_key VARCHAR(190) PRIMARY KEY,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $db5->beginTransaction();
 try{
  $claim=$db5->prepare('INSERT IGNORE INTO erp_data_migrations(migration_key) VALUES(?)');
  $claim->execute(['2026-09-17-normalize-initial-quote-versions-project-detail']);
  if($claim->rowCount()===1){
   $hasSeriesColumn=(bool)$db5->query("SHOW COLUMNS FROM quotes LIKE 'quote_series_key'")->fetch();
   if($hasSeriesColumn){
    $series=$db5->query("SELECT quote_series_key FROM quotes WHERE quote_series_key IS NOT NULL AND quote_series_key<>'' GROUP BY quote_series_key HAVING COUNT(*)=1")->fetchAll(PDO::FETCH_COLUMN);
    $normalize=$db5->prepare('UPDATE quotes SET version_no=1 WHERE quote_series_key=? AND version_no<>1');
    foreach($series as $seriesKey)$normalize->execute([(string)$seriesKey]);
   }
  }
  $db5->commit();
 }catch(Throwable $migrationError){
  if($db5->inTransaction())$db5->rollBack();
  throw $migrationError;
 }
 // Recupera presupuestos aceptados desde el enlace público antes de que existiera la creación automática del cargo.
 $backfill=$db5->prepare("INSERT INTO charges(project_id,quote_id,charge_date,type,currency,description,amount)
  SELECT q.project_id,q.id,COALESCE(DATE(q.followup_closed_at),q.quote_date),'ingenieria',q.currency,
   CONCAT('Adelanto de ingeniería ',p.engineering_pct,'% · ',COALESCE(NULLIF(q.proposal_name,''),'Presupuesto'),' · v',q.version_no),
   ROUND(q.total*p.engineering_pct/100,2)
  FROM quotes q JOIN projects p ON p.id=q.project_id
  WHERE q.project_id=? AND q.status='aprobado_inicial' AND p.engineering_pct>0
   AND NOT EXISTS(SELECT 1 FROM charges c WHERE c.quote_id=q.id AND c.type='ingenieria' AND c.active=1)");
 $backfill->execute([$projectId]);
 $s=$db5->prepare("SELECT * FROM quotes WHERE project_id=? AND status IN ('aprobado_inicial','aprobado_definitivo','final') ORDER BY version_no DESC,id DESC");$s->execute([$projectId]);$approved=$s->fetchAll();
 // Cada presupuesto aprobado representa un alcance vendible independiente, incluso si comparte rubro y moneda.
 $current=$approved;
 foreach(['USD','ARS'] as $cur)$financial[$cur]=['total'=>0.0,'materials'=>0.0,'materials_input'=>0.0,'materials_vat'=>0.0,'labor'=>0.0,'labor_input'=>0.0,'labor_vat'=>0.0,'paid'=>0.0,'due'=>0.0,'approved_ids'=>[]];
 foreach($current as $q){$cur=$q['currency']??'USD';if(!isset($financial[$cur]))continue;$mi=(float)$q['materials_amount'];$li=(float)$q['labor_amount'];$mm=$q['materials_tax_mode']??'sin_iva';$lm=$q['labor_tax_mode']??'sin_iva';$mr=(float)($q['materials_vat_rate']??21);$lr=(float)($q['labor_vat_rate']??21);$mv=$mm==='mas_iva'?round($mi*$mr/100,2):0;$lv=$lm==='mas_iva'?round($li*$lr/100,2):0;$financial[$cur]['materials_input']+=$mi;$financial[$cur]['materials_vat']+=$mv;$financial[$cur]['materials']+=$mi+$mv;$financial[$cur]['labor_input']+=$li;$financial[$cur]['labor_vat']+=$lv;$financial[$cur]['labor']+=$li+$lv;$financial[$cur]['total']+=($mi+$mv+$li+$lv);$financial[$cur]['approved_ids'][(int)$q['id']]=true;}
 $s=$db5->prepare('SELECT currency,SUM(amount) amount FROM payments WHERE project_id=? GROUP BY currency');$s->execute([$projectId]);foreach($s as $p)if(isset($financial[$p['currency']]))$financial[$p['currency']]['paid']=(float)$p['amount'];
 $s=$db5->prepare('SELECT c.*,COALESCE((SELECT SUM(a.amount) FROM allocations a WHERE a.charge_id=c.id),0) paid FROM charges c WHERE c.project_id=? AND c.active=1');$s->execute([$projectId]);foreach($s as $c){$cur=$c['currency'];if(!isset($financial[$cur]))continue;$qid=(int)($c['quote_id']??0);if($qid>0&&!isset($financial[$cur]['approved_ids'][$qid]))continue;$financial[$cur]['due']+=max(0,(float)$c['amount']-(float)$c['paid']);}
}catch(Throwable $e){}
ob_start();require __DIR__.'/detail_v4.php';$html=ob_get_clean();
$data=json_encode($financial,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$inject=<<<HTML
<script>
(function(){
 const data=$data;const money=n=>'US$ '+Number(n||0).toLocaleString('es-AR',{minimumFractionDigits:2,maximumFractionDigits:2});
 const main=document.querySelector('main.main');if(!main)return;const top=main.querySelector(':scope > .actions');const cards=[...main.querySelectorAll(':scope > .card')];const budgets=cards.find(c=>c.querySelector('h2')?.textContent.trim()==='Presupuestos');if(!top||!budgets)return;
 let n=top.nextElementSibling;while(n&&n!==budgets){const next=n.nextElementSibling;n.remove();n=next;}
 const frag=document.createDocumentFragment();
 ['USD','ARS'].forEach(cur=>{const t=data[cur];if(!t)return;if(Number(t.total)<.009&&Number(t.paid)<.009&&Number(t.due)<.009)return;const balance=Number(t.total)-Number(t.paid);const wrap=document.createElement('div');wrap.innerHTML=`<h2>\${cur}</h2><div class="grid"><div class="card c4"><span class="muted">Valor aprobado del proyecto</span><div class="kpi">\${money(t.total)}</div></div><div class="card c4"><span class="muted">Pagado por el cliente</span><div class="kpi ok">\${money(t.paid)}</div></div><div class="card c4"><span class="muted">\${balance>=0?'Saldo total pendiente':'Saldo a favor del cliente'}</span><div class="kpi \${balance>=0?'bad':'ok'}">\${money(Math.abs(balance))}</div></div></div><div class="card"><div class="actions"><div style="flex:1"><h3 style="margin:0">Detalle del saldo aprobado</h3><p class="muted" style="margin:5px 0 0">Solo se consideran presupuestos con estado Aprobado inicial o Final. Borradores y presupuestos enviados no generan deuda.</p></div><div><span class="muted">Exigible ahora</span><div class="kpi">\${money(t.due)}</div></div></div><div class="grid" style="margin-top:18px"><div class="c6"><h3>Materiales</h3><p>Importe aprobado: <b>\${money(t.materials_input)}</b><br>IVA agregado: <b>\${money(t.materials_vat)}</b><br>Total: <b>\${money(t.materials)}</b></p></div><div class="c6"><h3>Mano de obra e ingeniería</h3><p>Importe aprobado: <b>\${money(t.labor_input)}</b><br>IVA agregado: <b>\${money(t.labor_vat)}</b><br>Total: <b>\${money(t.labor)}</b></p></div></div></div>`;while(wrap.firstChild)frag.appendChild(wrap.firstChild);});
 main.insertBefore(frag,budgets);
})();
</script>
HTML;
if(str_contains($html,'</body>'))$html=str_replace('</body>',$inject.'</body>',$html);else$html.=$inject;echo $html;
