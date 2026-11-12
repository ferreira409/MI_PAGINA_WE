<?php
// =========================================================
//  Router para el servidor de PHP en desarrollo:
//      php -S localhost:8000 router.php
//  (En Apache / hosting se usa el archivo .htaccess)
// =========================================================
$ruta = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Bloquear carpetas internas
if (preg_match('#^/(config|includes|docs)/|\.sql$|^/mysql-#', $ruta)) {
    http_response_code(403);
    exit('Acceso denegado');
}

// API: todo lo que empiece con /api va a api/index.php
if (preg_match('#^/api(/|$)#', $ruta)) {
    $_SERVER['SCRIPT_NAME'] = '/api/index.php';
    require __DIR__ . '/api/index.php';
    return true;
}

$archivo = __DIR__ . $ruta;
if ($ruta === '/' || is_dir($archivo)) {
    $archivo = rtrim($archivo, '/') . '/index.php';
}

if (is_file($archivo)) {
    if (str_ends_with($archivo, '.php')) {
        $_SERVER['SCRIPT_NAME'] = substr($archivo, strlen(__DIR__));
        chdir(dirname($archivo));
        require $archivo;
        return true;
    }
    if (str_ends_with($archivo, '.webmanifest')) {
        header('Content-Type: application/manifest+json');
        readfile($archivo);
        return true;
    }
    return false; // archivos estáticos (css, js, imágenes): los sirve PHP directamente
}

require __DIR__ . '/404.php';
