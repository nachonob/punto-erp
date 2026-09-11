<?php
declare(strict_types=1);
session_start();
if(empty($_SESSION['user'])){http_response_code(401);exit;}
$url='https://puntodomotica.com/assets/images/cropped-logo-blanco-63892739ae.png';
$data=false;
if(function_exists('curl_init')){
 $ch=curl_init($url);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>10,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_USERAGENT=>'PuntoERP/1.0']);
 $data=curl_exec($ch);
 $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
 $type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);
 curl_close($ch);
 if($code<200||$code>=300)$data=false;
}else{
 $ctx=stream_context_create(['http'=>['timeout'=>10,'header'=>"User-Agent: PuntoERP/1.0\r\n"]]);
 $data=@file_get_contents($url,false,$ctx);
 $type='image/png';
}
if($data===false||$data===''){http_response_code(404);exit;}
header('Content-Type: '.(($type??'')?:'image/png'));
header('Cache-Control: private, max-age=86400');
header('Content-Length: '.strlen($data));
echo $data;
