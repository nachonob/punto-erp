<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$id=(int)($_GET['id']??0);
$isElectricidad=false;
if($id>0){
    try{
        $cfg=require $root.'/config.php';
        $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $s=$db->prepare('SELECT quote_template_family FROM quotes WHERE id=?');
        $s->execute([$id]);
        $isElectricidad=((string)$s->fetchColumn()==='electricidad');
    }catch(Throwable $e){}
}

ob_start();
require __DIR__.'/print_v4.php';
$html=ob_get_clean();

if($isElectricidad){
    $html=str_replace('Sin definir','Rubro electricidad',$html);
    $html=str_replace('const quoteFamily=""','const quoteFamily="electricidad"',$html);
}

echo $html;
