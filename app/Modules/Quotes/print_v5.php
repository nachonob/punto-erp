<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$id=(int)($_GET['id']??0);
$isElectricidad=false;$laborHasAddedVat=false;$laborHasIncludedVat=false;
if($id>0){
    try{
        $cfg=require $root.'/config.php';
        $db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $s=$db->prepare('SELECT quote_template_family,labor_tax_mode FROM quotes WHERE id=?');
        $s->execute([$id]);
        $quoteData=$s->fetch()?:[];$isElectricidad=((string)($quoteData['quote_template_family']??'')==='electricidad');
        $laborHasAddedVat=((string)($quoteData['labor_tax_mode']??'')==='mas_iva');
        $laborHasIncludedVat=((string)($quoteData['labor_tax_mode']??'')==='iva_incluido');
        try{$tax=$db->prepare("SELECT SUM(tax_mode='mas_iva') added,SUM(tax_mode='iva_incluido') included FROM quote_labor_items WHERE quote_id=?");$tax->execute([$id]);$taxData=$tax->fetch()?:[];$laborHasAddedVat=$laborHasAddedVat||(int)($taxData['added']??0)>0;$laborHasIncludedVat=$laborHasIncludedVat||(int)($taxData['included']??0)>0;}catch(Throwable $ignored){}
    }catch(Throwable $e){}
}

ob_start();
require __DIR__.'/print_v4.php';
$html=ob_get_clean();

if($isElectricidad){
    $html=str_replace('Sin definir','Rubro electricidad',$html);
    $html=str_replace('const quoteFamily=""','const quoteFamily="electricidad"',$html);
}
if($laborHasAddedVat)$html=str_replace('<span>Mano de obra</span>','<span>Mano de obra + IVA</span>',$html);
elseif($laborHasIncludedVat)$html=str_replace('<span>Mano de obra</span>','<span>Mano de obra (IVA incluido)</span>',$html);

echo $html;
