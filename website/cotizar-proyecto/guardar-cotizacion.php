<?php
declare(strict_types=1);

function fail(string $message, int $status = 400): never {
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>No se pudo enviar</title><style>body{font:16px system-ui;background:#f4f6f8;color:#27303b;padding:40px}.box{max-width:680px;margin:auto;background:#fff;border:1px solid #e1e5e9;border-radius:14px;padding:28px}a{color:#ff6702}</style><div class="box"><h1>No se pudo guardar la solicitud</h1><p>'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p><p><a href="./">Volver al formulario</a></p></div>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Método no permitido.', 405);

$publicRoot = dirname(__DIR__);
$erpRoots = [dirname(__DIR__,2).'/erp', dirname(__DIR__,2).'/erp-dev'];
$cfg = null;
$erpRoot = null;
foreach ($erpRoots as $candidate) {
    if (is_file($candidate.'/config.php')) {
        $erpRoot = $candidate;
        $cfg = require $candidate.'/config.php';
        break;
    }
}
if (!$cfg || !$erpRoot) fail('No se encontró la configuración del ERP.', 500);

date_default_timezone_set($cfg['timezone'] ?? 'America/Argentina/Buenos_Aires');
try {
    $db = new PDO(
        'mysql:host='.$cfg['db_host'].';dbname='.$cfg['db_name'].';charset=utf8mb4',
        $cfg['db_user'],
        $cfg['db_pass'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    fail('No se pudo conectar con la base de datos.', 500);
}

function clean(string $key): string { return trim((string)($_POST[$key] ?? '')); }
function intOrNull(string $key): ?int {
    $v = $_POST[$key] ?? null;
    if ($v === null || $v === '') return null;
    $n = filter_var($v, FILTER_VALIDATE_INT);
    return $n === false ? null : (int)$n;
}
function arr(string $key): array {
    $v = $_POST[$key] ?? [];
    if (!is_array($v)) $v = [$v];
    return array_values(array_filter(array_map(static fn($x)=>trim((string)$x), $v), static fn($x)=>$x!==''));
}

$name = clean('nombre');
$whatsapp = preg_replace('/\s+/', '', clean('telefono'));
$email = strtolower(clean('email'));
$location = clean('ubicacion');
if ($name === '' || $whatsapp === '' || $location === '') fail('Completá nombre, WhatsApp y ciudad/localidad.');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('El email ingresado no es válido.');

$rooms = arr('ambientes');
$systems = arr('sistemas');
$climateTypes = arr('tipos_climatizacion');
$plansAvailable = clean('planos') === 'si' ? 1 : 0;
$plansPath = null;

if ($plansAvailable) {
    if (empty($_FILES['archivo_planos']) || $_FILES['archivo_planos']['error'] !== UPLOAD_ERR_OK) {
        fail('Elegiste que tenés planos disponibles, pero no adjuntaste un PDF.');
    }
    if ((int)$_FILES['archivo_planos']['size'] > 15 * 1024 * 1024) fail('El PDF de planos supera el máximo de 15 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['archivo_planos']['tmp_name']);
    if ($mime !== 'application/pdf') fail('El archivo de planos debe ser PDF.');
    $uploadDir = dirname(__DIR__,2).'/uploads/cotizaciones-planos';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) fail('No se pudo preparar la carpeta de planos.', 500);
    $safeName = 'planos-'.date('Ymd-His').'-'.bin2hex(random_bytes(6)).'.pdf';
    $full = $uploadDir.'/'.$safeName;
    if (!move_uploaded_file($_FILES['archivo_planos']['tmp_name'], $full)) fail('No se pudo guardar el PDF de planos.', 500);
    $plansPath = 'uploads/cotizaciones-planos/'.$safeName;
}

try {
    $db->beginTransaction();

    // Reutiliza cliente existente por WhatsApp o email para evitar duplicados.
    $client = null;
    if ($whatsapp !== '') {
        $s = $db->prepare('SELECT * FROM clients WHERE whatsapp=? ORDER BY id DESC LIMIT 1');
        $s->execute([$whatsapp]);
        $client = $s->fetch() ?: null;
    }
    if (!$client && $email !== '') {
        $s = $db->prepare('SELECT * FROM clients WHERE email=? ORDER BY id DESC LIMIT 1');
        $s->execute([$email]);
        $client = $s->fetch() ?: null;
    }

    if ($client) {
        $clientId = (int)$client['id'];
        $db->prepare('UPDATE clients SET business_name=?, contact_name=?, email=?, whatsapp=?, city=?, country=COALESCE(NULLIF(country,\'\'),\'Argentina\'), active=1 WHERE id=?')
           ->execute([$name,$name,$email,$whatsapp,$location,$clientId]);
        $clientNumber = (int)($client['client_number'] ?? 0);
        if ($clientNumber <= 0) {
            $next = (int)$db->query('SELECT COALESCE(MAX(client_number),0)+1 FROM clients FOR UPDATE')->fetchColumn();
            $db->prepare('UPDATE clients SET client_number=? WHERE id=?')->execute([$next,$clientId]);
            $clientNumber = $next;
        }
    } else {
        $next = (int)$db->query('SELECT COALESCE(MAX(client_number),0)+1 FROM clients FOR UPDATE')->fetchColumn();
        $db->prepare('INSERT INTO clients(client_number,business_name,contact_name,cuit,iva_condition,email,whatsapp,address,city,province,country,notes,active) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,1)')
           ->execute([$next,$name,$name,'','consumidor_final',$email,$whatsapp,'',$location,'','Argentina','Alta automática desde cotizar-proyecto']);
        $clientId = (int)$db->lastInsertId();
        $clientNumber = $next;
    }

    $requestSeq = (int)$db->query('SELECT COALESCE(MAX(id),0)+1 FROM web_quote_requests FOR UPDATE')->fetchColumn();
    $requestNumber = 'WEB-'.date('Y').'-'.str_pad((string)$requestSeq,5,'0',STR_PAD_LEFT);

    $payload = [
        'estado'=>clean('estado'),'tipo'=>clean('tipo'),'superficie'=>intOrNull('superficie'),'plantas'=>intOrNull('plantas'),'fecha'=>clean('fecha'),
        'dormitorios'=>intOrNull('dormitorios'),'banos'=>intOrNull('banos'),'ambientes'=>$rooms,'sistemas'=>$systems,'tipos_climatizacion'=>$climateTypes,
        'cantidad_teclas'=>intOrNull('cantidad_teclas'),'cantidad_splits'=>intOrNull('cantidad_splits'),'cantidad_termostatos'=>intOrNull('cantidad_termostatos'),
        'cantidad_cortinas'=>intOrNull('cantidad_cortinas'),'cantidad_camaras'=>intOrNull('cantidad_camaras'),'cantidad_cerraduras'=>intOrNull('cantidad_cerraduras'),
        'cantidad_zonas_audio'=>intOrNull('cantidad_zonas_audio'),'planos'=>$plansAvailable,'presupuesto'=>clean('presupuesto'),'comentarios'=>clean('comentarios')
    ];

    $sql = 'INSERT INTO web_quote_requests(request_number,client_id,contact_name,whatsapp,email,location,project_stage,property_type,surface_m2,floors,estimated_date,bedrooms,bathrooms,rooms,systems,climate_types,switches_qty,splits_qty,thermostats_qty,curtains_qty,cameras_qty,smart_locks_qty,audio_zones_qty,plans_available,plans_file,budget_range,comments,payload_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
    $db->prepare($sql)->execute([
        $requestNumber,$clientId,$name,$whatsapp,$email,$location,clean('estado'),clean('tipo'),intOrNull('superficie'),intOrNull('plantas'),clean('fecha'),intOrNull('dormitorios'),intOrNull('banos'),
        implode(', ',$rooms),implode(', ',$systems),implode(', ',$climateTypes),intOrNull('cantidad_teclas'),intOrNull('cantidad_splits'),intOrNull('cantidad_termostatos'),intOrNull('cantidad_cortinas'),intOrNull('cantidad_camaras'),intOrNull('cantidad_cerraduras'),intOrNull('cantidad_zonas_audio'),$plansAvailable,$plansPath,clean('presupuesto'),clean('comentarios'),json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
    ]);

    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    if ($plansPath) @unlink(dirname(__DIR__,2).'/'.$plansPath);
    fail('La solicitud no pudo guardarse en el ERP. '.$e->getMessage(), 500);
}

$lines = [
    'Hola Punto Domótica, completé el formulario de cotización.',
    '',
    'Solicitud: '.$requestNumber,
    'Cliente N°: '.str_pad((string)$clientNumber,5,'0',STR_PAD_LEFT),
    'Nombre: '.$name,
    'WhatsApp: '.$whatsapp,
    $email!=='' ? 'Email: '.$email : null,
    'Ubicación: '.$location,
    clean('estado')!=='' ? 'Etapa: '.clean('estado') : null,
    clean('tipo')!=='' ? 'Propiedad: '.clean('tipo') : null,
    intOrNull('superficie') ? 'Superficie: '.intOrNull('superficie').' m²' : null,
    $systems ? 'Sistemas: '.implode(', ',$systems) : null,
    in_array('Cerradura inteligente',$systems,true) && intOrNull('cantidad_cerraduras') ? 'Cerraduras inteligentes: '.intOrNull('cantidad_cerraduras') : null,
    $plansAvailable ? 'Planos: adjuntados en PDF' : 'Planos: no adjuntados',
    clean('presupuesto')!=='' ? 'Presupuesto orientativo: '.clean('presupuesto') : null,
    clean('comentarios')!=='' ? 'Comentarios: '.clean('comentarios') : null,
];
$lines = array_values(array_filter($lines, static fn($v)=>$v!==null));
$text = implode("\n", $lines);
$wa = 'https://wa.me/5493417448197?text='.rawurlencode($text);
header('Location: '.$wa, true, 303);
exit;
