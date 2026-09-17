<?php
declare(strict_types=1);

$id=(int)($_GET['id']??0);
ob_start();
require __DIR__.'/print_v11.php';
$html=ob_get_clean();
$_SESSION['csrf']??=bin2hex(random_bytes(24));$csrf=(string)$_SESSION['csrf'];

$html=preg_replace_callback('/href="(mailto:[^"]+)"/',function(array $match)use($id,$csrf):string{
    $target=htmlspecialchars_decode($match[1],ENT_QUOTES);
    return 'href="?a=send_quote&amp;id='.$id.'&amp;channel=email&amp;csrf='.rawurlencode($csrf).'&amp;target='.rawurlencode(base64_encode($target)).'"';
},$html)??$html;
$html=preg_replace_callback('/href="(https:\/\/wa\.me\/[^"]+)"/',function(array $match)use($id,$csrf):string{
    $target=htmlspecialchars_decode($match[1],ENT_QUOTES);
    return 'href="?a=send_quote&amp;id='.$id.'&amp;channel=whatsapp&amp;csrf='.rawurlencode($csrf).'&amp;target='.rawurlencode(base64_encode($target)).'"';
},$html)??$html;

echo $html;
