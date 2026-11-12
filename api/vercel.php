<?php
// =========================================================
//  Punto de entrada en VERCEL
//  Vercel manda todas las visitas a este archivo (ver vercel.json)
//  y aquí se decide qué página PHP ejecutar, igual que router.php
//  hace en local con "php -S localhost:8000 router.php".
// =========================================================
$raiz = dirname(__DIR__);
$ruta = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

// Vercel atiende con HTTPS y pasa la IP real en cabeceras
if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// Bloquear carpetas y archivos internos
if (preg_match('#(^|/)\.|^/(config|includes|docs|mysql-[^/]*)(/|$)|\.(sql|md|zip|ini|log)$|^/api/vercel\.php#i', $ruta)
    || str_contains($ruta, '..')) {
    http_response_code(403);
    exit('Acceso denegado');
}

// API REST: /api/... -> api/index.php
if (preg_match('#^/api(/|$)#', $ruta)) {
    $_SERVER['SCRIPT_NAME'] = '/api/index.php';
    chdir($raiz . '/api');
    require $raiz . '/api/index.php';
    return;
}

$archivo = $raiz . $ruta;
if ($ruta === '/' || is_dir($archivo)) {
    $archivo = rtrim($archivo, '/') . '/index.php';
}

if (is_file($archivo) && str_ends_with($archivo, '.php')) {
    $_SERVER['SCRIPT_NAME']     = substr($archivo, strlen($raiz));
    $_SERVER['SCRIPT_FILENAME'] = $archivo;
    $_SERVER['PHP_SELF']        = $_SERVER['SCRIPT_NAME'];
    chdir(dirname($archivo));
    require $archivo;
    return;
}

http_response_code(404);
require $raiz . '/404.php';
