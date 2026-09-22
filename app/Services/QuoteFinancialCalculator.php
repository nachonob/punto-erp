<?php
declare(strict_types=1);

function quoteFinancialBreakdown(PDO $db,array $quote):array{
    $materialsInput=(float)($quote['materials_amount']??0);
    $laborInput=(float)($quote['labor_amount']??0);
    $materialsMode=(string)($quote['materials_tax_mode']??$quote['tax_mode']??'sin_iva');
    $materialsRate=(float)($quote['materials_vat_rate']??$quote['vat_rate']??21);
    $laborMode=(string)($quote['labor_tax_mode']??$quote['tax_mode']??'sin_iva');
    $laborRate=(float)($quote['labor_vat_rate']??$quote['vat_rate']??21);
    $quoteId=(int)($quote['id']??0);

    if($quoteId>0){
        try{
            $items=$db->prepare("SELECT COUNT(*) item_count,COALESCE(SUM(subtotal),0) amount FROM quote_items WHERE quote_id=? AND COALESCE(sku,'')<>'__CONCEPT__'");
            $items->execute([$quoteId]);
            $itemTotals=$items->fetch(PDO::FETCH_ASSOC);
            if((int)($itemTotals['item_count']??0)>0)$materialsInput=(float)$itemTotals['amount'];
        }catch(Throwable $ignored){}

        try{
            $labor=$db->prepare("SELECT COUNT(*) item_count,COALESCE(SUM(amount),0) amount,COALESCE(SUM(CASE WHEN tax_mode='mas_iva' THEN ROUND(amount*vat_rate/100,2) ELSE 0 END),0) vat FROM quote_labor_items WHERE quote_id=?");
            $labor->execute([$quoteId]);
            $laborTotals=$labor->fetch(PDO::FETCH_ASSOC);
            if((int)($laborTotals['item_count']??0)>0){
                $laborInput=(float)$laborTotals['amount'];
                $laborVat=(float)$laborTotals['vat'];
            }
        }catch(Throwable $ignored){}
    }

    $materialsVat=$materialsMode==='mas_iva'?round($materialsInput*$materialsRate/100,2):0.0;
    if(!isset($laborVat))$laborVat=$laborMode==='mas_iva'?round($laborInput*$laborRate/100,2):0.0;
    return [
        'materials_input'=>$materialsInput,
        'materials_vat'=>$materialsVat,
        'materials_total'=>$materialsInput+$materialsVat,
        'labor_input'=>$laborInput,
        'labor_vat'=>$laborVat,
        'labor_total'=>$laborInput+$laborVat,
        'total'=>$materialsInput+$materialsVat+$laborInput+$laborVat,
    ];
}
