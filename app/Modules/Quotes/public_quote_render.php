<?php
declare(strict_types=1);
// Vista pública exclusiva para el cliente. El token ya fue validado por public_quote.php.
$id=(int)($_GET['id']??0);
if($id<1 || (int)($_SESSION['public_quote_access_id']??0)!==$id){http_response_code(403);exit('Acceso no autorizado.');}
$_SESSION['user']=['role'=>'public_quote'];
ob_start();require __DIR__.'/print_v8.php';$html=ob_get_clean();
unset($_SESSION['user'],$_SESSION['public_quote_access_id']);

// El cliente no debe ver navegación interna, edición ni acciones de envío.
$html=preg_replace('/<a class="light" href="\?a=quotes">.*?<\/a>/s','',$html)??$html;
$html=preg_replace('/<a class="dark" href="\?a=edit_quote&id=\d+">Editar<\/a>/','',$html)??$html;
$html=preg_replace('/<button id="mailQuoteBtn"[^>]*>Enviar por mail<\/button>/','',$html)??$html;
$html=preg_replace('/<button id="waQuoteBtn"[^>]*>Enviar por WhatsApp<\/button>/','',$html)??$html;
$html=preg_replace('/<a id="mailQuoteBtn"[^>]*>Enviar por mail<\/a>/','',$html)??$html;
$html=preg_replace('/<a id="waQuoteBtn"[^>]*>Enviar por WhatsApp<\/a>/','',$html)??$html;
$html=str_replace('<div class="screen-note">','<div class="screen-note" style="display:none">',$html);

// En la vista pública dejamos una única acción clara. generatePdf() ya arma el PDF comercial
// completo en el orden configurado: prólogo -> presupuesto -> forma de pago.
$html=str_replace('>Guardar PDF</button>','>Descargar PDF completo</button>',$html);
$html=str_replace('>Generar PDF</button>','>Descargar PDF completo</button>',$html);
$html=str_replace('</head>','<style>.toolbar{justify-content:flex-end}.toolbar #generatePdfBtn{background:#ff6702}.toolbar:empty{display:none}</style></head>',$html);

echo $html;
