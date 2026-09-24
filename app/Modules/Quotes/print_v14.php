<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
try{
    require_once $root.'/app/Services/AdiProductImport.php';
    $nameCfg=require $root.'/config.php';
    $nameDb=new PDO('mysql:host='.$nameCfg['db_host'].';dbname='.$nameCfg['db_name'].';charset=utf8mb4',$nameCfg['db_user'],$nameCfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    normalizeProductCatalogFields($nameDb);
    normalizeUnifiedProductNamesV2($nameDb);
}catch(Throwable $e){}

require __DIR__.'/print_v13.php';
