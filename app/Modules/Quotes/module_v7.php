<?php
declare(strict_types=1);

$a=$_GET['a']??'new_quote';

// Valores comerciales por defecto para presupuestos nuevos.
// Si por alguna razón el formulario no envía estos campos, el guardado conserva
// Materiales + IVA 21% y Mano de obra sin IVA.
if(in_array($a,['save_quote','update_quote'],true)){
    $_POST['materials_tax_mode'] = $_POST['materials_tax_mode'] ?? 'mas_iva';
    $_POST['materials_vat_rate'] = $_POST['materials_vat_rate'] ?? '21';
    $_POST['labor_tax_mode'] = $_POST['labor_tax_mode'] ?? 'sin_iva';
    $_POST['labor_vat_rate'] = $_POST['labor_vat_rate'] ?? '21';
    require __DIR__.'/module_v6.php';
    exit;
}

ob_start();
require __DIR__.'/module_v6.php';
$html=ob_get_clean();

// Sólo al crear un presupuesto nuevo: seleccionar automáticamente
// Materiales + IVA y Mano de obra sin IVA. Al editar, se respetan los valores guardados.
if($a==='new_quote'){
    $inject=<<<'HTML'
<script>
(function(){
  const materials=document.querySelector('select[name="materials_tax_mode"]');
  const labor=document.querySelector('select[name="labor_tax_mode"]');
  const materialsVat=document.querySelector('input[name="materials_vat_rate"]');
  if(materials) materials.value='mas_iva';
  if(labor) labor.value='sin_iva';
  if(materialsVat && (!materialsVat.value || Number(materialsVat.value)===0)) materialsVat.value='21';
})();
</script>
HTML;
    if(str_contains($html,'</body>')) $html=str_replace('</body>',$inject.'</body>',$html);
    else $html.=$inject;
}

echo $html;
