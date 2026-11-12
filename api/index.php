<?php
// =========================================================
//  API REST de AgendaFácil (JSON) — para la app móvil
//
//  Autenticación: enviar el token en la cabecera
//      Authorization: Bearer <token>
//  El token se obtiene con POST /api/auth/login
//
//  Documentación: docs/API.md  (o abrir /api/ en el navegador)
// =========================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/bd.php';
require_once __DIR__ . '/../config/funciones.php';
require_once __DIR__ . '/../config/imagenes.php';

// ---------- Cabeceras ----------
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');                       // la app puede llamar desde cualquier origen
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ---------- Respuestas ----------
function responder($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

function error($mensaje, $codigo = 400, $extra = []) {
    responder(['ok' => false, 'error' => $mensaje] + $extra, $codigo);
}

function cuerpo() {
    static $datos = null;
    if ($datos === null) {
        $datos = json_decode(file_get_contents('php://input'), true);
        if (!is_array($datos)) $datos = $_POST;
    }
    return $datos;
}

function tokenDeCabecera() {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!$h && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) if (strtolower($k) === 'authorization') $h = $v;
    }
    return preg_match('/^Bearer\s+(\S+)$/i', $h, $m) ? $m[1] : null;
}

function requiereUsuario(PDO $pdo, $roles = []) {
    $u = usuarioPorTokenApi($pdo, tokenDeCabecera());
    if (!$u) error('No autenticado. Iniciá sesión y enviá el token.', 401);
    if ($roles && !in_array($u['rol'], (array)$roles, true)) error('No tenés permiso para esta acción.', 403);
    return $u;
}

function urlAbsoluta($ruta) {
    if (preg_match('#^https?://#', $ruta)) return $ruta;
    $esquema = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    return $esquema . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $ruta;
}

// Formato público de un negocio
function negocioJson(array $n) {
    return [
        'id'          => (int)$n['id'],
        'nombre'      => $n['nombre'],
        'categoria'   => $n['categoria'],
        'zona'        => $n['zona'],
        'direccion'   => $n['direccion'],
        'telefono'    => $n['telefono'],
        'whatsapp'    => $n['whatsapp'],
        'descripcion' => $n['descripcion'],
        'foto'        => urlAbsoluta(imgNegocio($n)),
        'promedio'    => isset($n['promedio']) && $n['promedio'] !== null ? (float)$n['promedio'] : null,
        'total_calificaciones' => (int)($n['total_calificaciones'] ?? $n['total'] ?? 0),
        'precio_desde'=> isset($n['desde']) && $n['desde'] !== null ? (float)$n['desde'] : null,
    ];
}

function citaJson(array $c) {
    return [
        'id'        => (int)$c['id'],
        'estado'    => $c['estado'],
        'fecha'     => $c['fecha'],
        'hora'      => $c['hora'],
        'hora_fin'  => $c['hora_fin'],
        'notas'     => $c['notas'],
        'negocio'   => ['id' => (int)$c['negocio_id'], 'nombre' => $c['negocio'], 'direccion' => $c['direccion'], 'whatsapp' => $c['negocio_whatsapp']],
        'servicio'  => ['id' => (int)$c['servicio_id'], 'nombre' => $c['servicio'], 'precio' => (float)$c['precio'], 'duracion_min' => (int)$c['duracion_min']],
        'cliente'   => ['id' => (int)$c['usuario_id'], 'nombre' => $c['cliente'], 'telefono' => $c['cliente_telefono']],
        'calificacion' => $c['calificacion'] !== null ? (int)$c['calificacion'] : null,
    ];
}

// ---------- Conexión ----------
try {
    $pdo = conectarBD();
} catch (PDOException $ex) {
    error('No se pudo conectar a la base de datos.', 500);
}

// ---------- Ruta ----------
// Funciona con /api/negocios (router o .htaccess) y con /api/index.php?ruta=negocios
$ruta = $_GET['ruta'] ?? '';
if ($ruta === '') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $ruta = preg_replace('#^.*?/api(/index\.php)?/?#', '', $path);
}
$ruta = trim($ruta, '/');
$partes = $ruta === '' ? [] : explode('/', $ruta);
$metodo = $_SERVER['REQUEST_METHOD'];

// Página de bienvenida al abrir /api/ en el navegador
if (!$partes) {
    responder([
        'ok' => true,
        'api' => NOMBRE_SITIO . ' API',
        'version' => '1.0',
        'documentacion' => 'Ver docs/API.md en el proyecto',
        'endpoints' => [
            'POST /api/auth/login', 'POST /api/auth/registro', 'POST /api/auth/logout', 'GET /api/yo',
            'GET /api/categorias', 'GET /api/zonas',
            'GET /api/negocios', 'GET /api/negocios/{id}', 'GET /api/negocios/{id}/disponibilidad?servicio=&fecha=',
            'GET /api/citas', 'POST /api/citas', 'PATCH /api/citas/{id}', 'POST /api/citas/{id}/calificar',
        ],
    ]);
}

// =========================================================
//  ENDPOINTS
// =========================================================
$recurso = $partes[0];
$id      = isset($partes[1]) && ctype_digit($partes[1]) ? (int)$partes[1] : null;
$sub     = $partes[2] ?? ($id === null ? ($partes[1] ?? null) : null);

// ---------- AUTENTICACIÓN ----------
if ($recurso === 'auth') {
    if ($sub === 'login' && $metodo === 'POST') {
        $d = cuerpo();
        $r = intentarLogin($pdo, $d['email'] ?? '', $d['password'] ?? '', ipCliente());
        if (!$r['ok']) error($r['error'], 401);
        responder(['ok' => true, 'token' => crearTokenApi($pdo, $r['usuario']['id']),
                   'expira_en_dias' => DIAS_TOKEN_API, 'usuario' => $r['usuario']]);
    }
    if ($sub === 'registro' && $metodo === 'POST') {
        $d = cuerpo();
        $r = registrarUsuario($pdo, $d, $d['tipo'] ?? 'cliente');
        if (!$r['ok']) error('No se pudo registrar.', 422, ['errores' => $r['errores']]);
        responder(['ok' => true, 'id' => $r['id']], 201);
    }
    if ($sub === 'logout' && $metodo === 'POST') {
        borrarTokenApi($pdo, tokenDeCabecera());
        responder(['ok' => true]);
    }
    error('Ruta no encontrada.', 404);
}

// ---------- USUARIO ACTUAL ----------
if ($recurso === 'yo' && $metodo === 'GET') {
    responder(['ok' => true, 'usuario' => requiereUsuario($pdo)]);
}

// ---------- LISTAS ----------
if ($recurso === 'categorias' && $metodo === 'GET') responder(['ok' => true, 'categorias' => CATEGORIAS]);
if ($recurso === 'zonas'      && $metodo === 'GET') responder(['ok' => true, 'zonas' => ZONAS]);

// ---------- NEGOCIOS ----------
if ($recurso === 'negocios' && $metodo === 'GET') {
    // Lista con filtros
    if ($id === null) {
        $lista = listarNegocios($pdo, ['q' => $_GET['q'] ?? '', 'categoria' => $_GET['categoria'] ?? '', 'zona' => $_GET['zona'] ?? '']);
        responder(['ok' => true, 'total' => count($lista), 'negocios' => array_map('negocioJson', $lista)]);
    }

    $n = obtenerNegocio($pdo, $id);
    if (!$n) error('Negocio no encontrado.', 404);

    // Horas libres de un servicio en una fecha
    if ($sub === 'disponibilidad') {
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $servicio = null;
        foreach ($n['servicios'] as $s) if ((int)$s['id'] === (int)($_GET['servicio'] ?? 0)) $servicio = $s;
        if (!$servicio)           error('Indicá un servicio válido de este negocio (?servicio=ID).', 422);
        if (!fechaValida($fecha)) error('Fecha no válida. Formato: AAAA-MM-DD.', 422);
        $horario = horarioDelDia($pdo, $id, $fecha);
        responder([
            'ok' => true,
            'fecha' => $fecha,
            'abierto' => $horario !== null,
            'horario' => $horario ? ['abre' => substr($horario['abre'], 0, 5), 'cierra' => substr($horario['cierra'], 0, 5)] : null,
            'servicio' => ['id' => (int)$servicio['id'], 'duracion_min' => (int)$servicio['duracion_min']],
            'horas' => horasDisponibles($pdo, $id, $fecha, (int)$servicio['duracion_min']),
        ]);
    }

    if ($sub === null) {
        responder(['ok' => true, 'negocio' => negocioJson($n) + [
            'servicios' => array_map(fn($s) => ['id' => (int)$s['id'], 'nombre' => $s['nombre'],
                                                 'duracion_min' => (int)$s['duracion_min'], 'precio' => (float)$s['precio']], $n['servicios']),
            'horarios'  => array_map(fn($h) => ['dia' => (int)$h['dia'], 'nombre_dia' => DIAS[(int)$h['dia']],
                                                 'abre' => $h['abre'], 'cierra' => $h['cierra']], $n['horarios']),
            'resenas'   => array_map(fn($r) => ['estrellas' => (int)$r['estrellas'], 'comentario' => $r['comentario'],
                                                 'nombre' => explode(' ', $r['nombre'])[0], 'fecha' => substr($r['creado_en'], 0, 10)], $n['resenas']),
        ]]);
    }
    error('Ruta no encontrada.', 404);
}

// ---------- CITAS ----------
if ($recurso === 'citas') {
    $u = requiereUsuario($pdo);

    // Listar: cliente ve las suyas; vendedor/admin las de sus negocios
    if ($metodo === 'GET' && $id === null) {
        $filtro = ['estado' => $_GET['estado'] ?? '', 'fecha' => $_GET['fecha'] ?? ''];
        if ($u['rol'] === 'cliente') $filtro['usuario_id'] = $u['id'];
        else $filtro['negocios'] = array_column(negociosDeUsuario($pdo, $u), 'id');
        $citas = listarCitas($pdo, $filtro);
        responder(['ok' => true, 'total' => count($citas), 'citas' => array_map('citaJson', $citas)]);
    }

    // Crear (solo clientes)
    if ($metodo === 'POST' && $id === null) {
        if ($u['rol'] !== 'cliente') error('Solo los clientes pueden reservar.', 403);
        $d = cuerpo();
        $r = crearCita($pdo, $u['id'], (int)($d['negocio_id'] ?? 0), (int)($d['servicio_id'] ?? 0),
                       $d['fecha'] ?? '', $d['hora'] ?? '', $d['notas'] ?? '');
        if (!$r['ok']) error($r['error'], 409);
        $cita = array_values(array_filter(listarCitas($pdo, ['usuario_id' => $u['id']]), fn($c) => (int)$c['id'] === $r['id']))[0];
        responder(['ok' => true, 'cita' => citaJson($cita)], 201);
    }

    // Cambiar estado: {"estado": "confirmada" | "completada" | "cancelada"}
    if ($metodo === 'PATCH' && $id !== null && $sub === null) {
        $r = cambiarEstadoCita($pdo, $id, cuerpo()['estado'] ?? '', $u);
        if (!$r['ok']) error($r['error'], 422);
        responder(['ok' => true]);
    }

    // Calificar: {"estrellas": 1-5, "comentario": "..."}
    if ($metodo === 'POST' && $id !== null && $sub === 'calificar') {
        if ($u['rol'] !== 'cliente') error('Solo los clientes pueden calificar.', 403);
        $d = cuerpo();
        $r = calificarCita($pdo, $u['id'], $id, $d['estrellas'] ?? 0, $d['comentario'] ?? '');
        if (!$r['ok']) error($r['error'], 422);
        responder(['ok' => true], 201);
    }
    error('Ruta no encontrada.', 404);
}

error('Ruta no encontrada.', 404);
