<?php
declare(strict_types=1);

$a=$_GET['a']??'new_quote';

ob_start();
require __DIR__.'/module_v12.php';
$html=ob_get_clean();

$fix=<<<'HTML'
<style>
/* Permitir que los desplegables de búsqueda salgan visualmente del bloque de materiales */
.material-block,
.material-block .block-body,
.material-block .block-body > div,
.material-block table,
.material-block tbody,
.material-block tr,
.material-block td,
.material-block .suggest-wrap{
  overflow:visible !important;
}
.material-block{position:relative;z-index:1}
.material-block:focus-within{z-index:2000}
.material-block tr:focus-within{position:relative;z-index:2100}
.material-block .suggest-wrap{position:relative;z-index:2200}
.material-block .suggestions{
  z-index:99999 !important;
  max-height:320px;
  overflow-y:auto !important;
  overflow-x:hidden !important;
  box-shadow:0 14px 30px rgba(0,0,0,.16);
}
@media(max-width:900px){
  .material-block .block-body{overflow:visible !important}
  .material-block .block-body > div{overflow:visible !important}
  .material-block table{min-width:920px}
}
</style>
HTML;

if(str_contains($html,'</head>'))$html=str_replace('</head>',$fix.'</head>',$html);
else$html=$fix.$html;

echo $html;
