<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$id=(int)($_GET['id']??0);
$notes='';
try{
    $cfg=require $root.'/config.php';
    $notesDb=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $stmt=$notesDb->prepare('SELECT notes FROM quotes WHERE id=?');
    $stmt->execute([$id]);
    $notes=(string)($stmt->fetchColumn()?:'');
}catch(Throwable $e){}

ob_start();
require __DIR__.'/print_v12.php';
$html=ob_get_clean();

if($notes!==''){
    $safe=preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is','',$notes)??'';
    if(preg_match('/<(p|br|strong|b|em|i|u|ul|ol|li|h3|h4)\b/i',$safe)){
        $safe=strip_tags($safe,'<p><br><strong><b><em><i><u><ul><ol><li><h3><h4>');
        $safe=preg_replace_callback('/<\/?([a-z0-9]+)\b[^>]*>/i',static function(array $match):string{$tag=strtolower($match[1]);$closing=str_starts_with($match[0],'</');return $tag==='br'?'<br>':'<'.($closing?'/':'').$tag.'>';},$safe)??'';
        $escaped=htmlspecialchars($notes,ENT_QUOTES,'UTF-8');
        $html=str_replace('<p>'.$escaped.'</p>','<div class="notes-content">'.$safe.'</div>',$html);
    }
}

$style='<style>.notes-content{color:#4f565d;font-size:10.5px;line-height:1.5}.notes-content p{margin:0 0 2mm}.notes-content h3,.notes-content h4{margin:0 0 2mm;color:#4f565d}.notes-content ul,.notes-content ol{margin:1mm 0 2mm;padding-left:5mm}</style>';
if(str_contains($html,'</head>'))$html=str_replace('</head>',$style.'</head>',$html);else$html=$style.$html;
echo $html;
