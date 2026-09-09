<?php
declare(strict_types=1);
// Render aislado: print_v4 exige sesión. Creamos una sesión efímera solo para esta solicitud
// después de que public_quote.php validó el token contra quote_public_links.
$id=(int)($_GET['id']??0);
if($id<1 || (int)($_SESSION['public_quote_access_id']??0)!==$id){http_response_code(403);exit('Acceso no autorizado.');}
$_SESSION['user']=['role'=>'public_quote'];
ob_start();require __DIR__.'/print_v8.php';$html=ob_get_clean();
unset($_SESSION['user'],$_SESSION['public_quote_access_id']);
// Vista cliente: sin navegación ni edición. Conserva Guardar PDF para descargar el documento completo.
$html=preg_replace('/<a class="light" href="\?a=quotes">.*?<\/a>/s','',$html)??$html;
$html=preg_replace('/<a class="dark" href="\?a=edit_quote&id=\d+">Editar<\/a>/','',$html)??$html;
$html=str_replace('<div class="screen-note">','<div class="screen-note" style="display:none">',$html);
echo $html;
