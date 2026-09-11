<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);$id=(int)($_GET['id']??0);$discounts=[];$discountTotal=0.0;$baseTotal=0.0;
try{
 $cfg=require $root.'/config.php';$db=new PDO('mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',$cfg['db_user'],$cfg['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $s=$db->prepare('SELECT total,materials_amount,labor_amount FROM quotes WHERE id=?');$s->execute([$id]);$qr=$s->fetch()?:[];$baseTotal=(float)($qr['total']??0);$mat=(float)($qr['materials_amount']??0);$lab=(float)($qr['labor_amount']??0);
 $s=$db->prepare('SELECT * FROM quote_discounts WHERE quote_id=? ORDER BY sort_order,id');$s->execute([$id]);$discounts=$s->fetchAll();
 foreach($discounts as &$d){$scope=(string)$d['scope'];$base=$scope==='materials'?$mat:($scope==='labor'?$lab:$baseTotal);if($scope==='item'){$base=0; if(($d['item_type']??'')==='material'&&(int)$d['item_id']>0){$x=$db->prepare('SELECT subtotal FROM quote_items WHERE quote_id=? AND product_id=? ORDER BY id LIMIT 1');$x->execute([$id,(int)$d['item_id']]);$base=(float)($x->fetchColumn()?:0);}elseif(($d['item_type']??'')==='labor'&&(int)$d['item_id']>0){$x=$db->prepare('SELECT amount FROM quote_labor_items WHERE quote_id=? AND id=?');$x->execute([$id,(int)$d['item_id']]);$base=(float)($x->fetchColumn()?:0);}}
   $amt=$d['discount_type']==='percentage'?$base*min(100,(float)$d['value'])/100:min($base,(float)$d['value']);$d['_amount']=round(max(0,$amt),2);$discountTotal+=$d['_amount'];}
 unset($d);
}catch(Throwable $e){}
ob_start();require __DIR__.'/print_v7.php';$html=ob_get_clean();
if($discounts){
 $rows='';foreach($discounts as $d){$concept=trim((string)($d['description']??''));if($concept==='')$concept='Descuento';$detail=$d['discount_type']==='percentage'?' ('.rtrim(rtrim(number_format((float)$d['value'],2,'.',''),'0'),'.').'%)':'';$rows.='<div class="r discount-line"><span>'.htmlspecialchars($concept.$detail,ENT_QUOTES,'UTF-8').'</span><b>-US$ '.number_format((float)$d['_amount'],2,',','.').'</b></div>';}
 $final=max(0,$baseTotal-$discountTotal);
 $html=str_replace('<div class="r grand"><span>TOTAL</span><span>'.('US$ '.number_format($baseTotal,2,',','.')).'</span></div>',$rows.'<div class="r grand"><span>TOTAL</span><span>US$ '.number_format($final,2,',','.').'</span></div>',$html);
 $html=str_replace('</head>','<style>.summary .discount-line{color:#b42318}.summary .discount-line b{color:#b42318}</style></head>',$html);
}
echo $html;
