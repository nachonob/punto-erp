<?php
declare(strict_types=1);

function projectFollowupBusinessDate(string $from,int $days=10):string{
    $date=new DateTimeImmutable($from);$count=0;
    while($count<$days){$date=$date->modify('+1 day');if((int)$date->format('N')<=5)$count++;}
    return $date->format('Y-m-d');
}

function ensureProjectFollowupSchema(PDO $db):void{
    $columns=[
        'followup_sent_at'=>'DATETIME NULL',
        'followup_responsible_user_id'=>'INT UNSIGNED NULL',
        'next_followup_date'=>'DATE NULL',
        'followup_reminder_sent_at'=>'DATETIME NULL',
        'followup_closed_at'=>'DATETIME NULL'
    ];
    foreach($columns as $name=>$definition){
        $q=$db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='projects' AND COLUMN_NAME=?");$q->execute([$name]);
        if(!(int)$q->fetchColumn())$db->exec('ALTER TABLE projects ADD COLUMN `'.$name.'` '.$definition);
    }
    $db->exec("CREATE TABLE IF NOT EXISTS project_followup_history (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_id INT UNSIGNED NOT NULL,
        source_quote_id INT UNSIGNED NULL,
        user_id INT UNSIGNED NULL,
        event_type VARCHAR(40) NOT NULL,
        contact_method VARCHAR(30) NULL,
        contact_result VARCHAR(60) NULL,
        next_contact_date DATE NULL,
        notes TEXT NULL,
        event_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        legacy_quote_history_id INT UNSIGNED NULL,
        UNIQUE KEY uq_project_followup_legacy (legacy_quote_history_id),
        KEY idx_project_followup_project (project_id,event_date),
        KEY idx_project_followup_quote (source_quote_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    foreach(['contact_method'=>'VARCHAR(30) NULL','contact_result'=>'VARCHAR(60) NULL'] as $name=>$definition){
        $q=$db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='project_followup_history' AND COLUMN_NAME=?");$q->execute([$name]);
        if(!(int)$q->fetchColumn())$db->exec('ALTER TABLE project_followup_history ADD COLUMN `'.$name.'` '.$definition.' AFTER event_type');
    }
    $db->exec("CREATE TABLE IF NOT EXISTS erp_data_migrations (migration_key VARCHAR(190) PRIMARY KEY,applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $claim=$db->prepare('INSERT IGNORE INTO erp_data_migrations(migration_key) VALUES(?)');$claim->execute(['2026-09-24-project-followups']);
    if(!$claim->rowCount())return;
    try{
        if((bool)$db->query("SHOW TABLES LIKE 'quote_followup_history'")->fetch()){
            $db->exec("INSERT IGNORE INTO project_followup_history(project_id,source_quote_id,user_id,event_type,next_contact_date,notes,event_date,legacy_quote_history_id)
                SELECT q.project_id,h.quote_id,h.user_id,h.event_type,h.next_contact_date,h.notes,h.event_date,h.id
                FROM quote_followup_history h JOIN quotes q ON q.id=h.quote_id");
        }
        $db->exec("UPDATE projects p SET
            followup_sent_at=COALESCE(followup_sent_at,(SELECT MIN(q.sent_at) FROM quotes q WHERE q.project_id=p.id AND q.sent_at IS NOT NULL)),
            next_followup_date=COALESCE(next_followup_date,(SELECT MIN(q.next_followup_date) FROM quotes q WHERE q.project_id=p.id AND q.next_followup_date IS NOT NULL AND q.followup_closed_at IS NULL)),
            followup_responsible_user_id=COALESCE(followup_responsible_user_id,(SELECT q.responsible_user_id FROM quotes q WHERE q.project_id=p.id AND q.responsible_user_id IS NOT NULL ORDER BY COALESCE(q.sent_at,q.quote_date) DESC,q.id DESC LIMIT 1))
            WHERE EXISTS(SELECT 1 FROM quotes q WHERE q.project_id=p.id AND (q.sent_at IS NOT NULL OR q.next_followup_date IS NOT NULL)");
    }catch(Throwable $migrationError){error_log('Migración histórica de seguimiento: '.$migrationError->getMessage());}
}

function scheduleProjectFollowupForQuote(PDO $db,int $projectId,int $quoteId,string $sentDate,int $userId,string $channel=''):void{
    $next=projectFollowupBusinessDate($sentDate);
    $db->prepare("UPDATE projects SET followup_sent_at=COALESCE(followup_sent_at,?),followup_responsible_user_id=COALESCE(followup_responsible_user_id,?),next_followup_date=?,followup_reminder_sent_at=NULL,followup_closed_at=NULL WHERE id=?")
       ->execute([$sentDate.' 00:00:00',$userId?:null,$next,$projectId]);
    $note='Presupuesto enviado'.($channel!==''?' por '.$channel:'');
    $db->prepare("INSERT INTO project_followup_history(project_id,source_quote_id,user_id,event_type,next_contact_date,notes)
        SELECT ?,?,?,'presupuesto_enviado',?,? WHERE NOT EXISTS(SELECT 1 FROM project_followup_history WHERE project_id=? AND source_quote_id=? AND event_type='presupuesto_enviado')")
       ->execute([$projectId,$quoteId,$userId?:null,$next,$note,$projectId,$quoteId]);
}
