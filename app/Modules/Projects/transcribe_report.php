<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
$root=dirname(__DIR__,3);
$cfg=require $root.'/config.php';
if(empty($_SESSION['user'])){http_response_code(401);echo json_encode(['ok'=>false,'error'=>'Sesión vencida.']);exit;}
require_once $root.'/app/Services/OpenAITranscription.php';
try{
    $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
    ]);
    $uid=(int)($_SESSION['user']['id']??0);
    $isAdmin=(($_SESSION['user']['role']??'')==='admin');
    $manage=$isAdmin||!empty(($_SESSION['user']['permissions']??[])['technical_projects']['manage']);
    $audioId=(int)($_POST['audio_id']??0);
    if($audioId<=0)throw new RuntimeException('Audio inválido.');
    $s=$db->prepare("SELECT a.id,a.file_path,a.mime_type,a.report_id,r.user_id,r.event_id FROM technical_daily_report_audio a JOIN technical_daily_reports r ON r.id=a.report_id WHERE a.id=?");
    $s->execute([$audioId]);
    $audio=$s->fetch();
    if(!$audio)throw new RuntimeException('No se encontró el audio.');
    if(!$manage&&(int)$audio['user_id']!==$uid){http_response_code(403);throw new RuntimeException('No tenés permiso para transcribir este audio.');}
    $key=OpenAITranscription::apiKey($cfg);
    if($key==='')throw new RuntimeException('Falta configurar OPENAI_API_KEY en el servidor.');
    $full=$root.'/'.ltrim((string)$audio['file_path'],'/');
    $text=OpenAITranscription::transcribe($full,(string)$audio['mime_type'],$cfg);
    $u=$db->prepare('UPDATE technical_daily_reports SET transcription=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
    $u->execute([$text,(int)$audio['report_id']]);
    echo json_encode(['ok'=>true,'text'=>$text],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
    if(http_response_code()<400)http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
