<?php
// =========================================================
//  Instalador de la base de datos (local o en la nube)
//  Crea las tablas y los datos de prueba con config/esquema.sql
//
//  Uso:  /instalar_bd.php?clave=TU_CLAVE_INSTALACION
//  (la clave se define en la variable CLAVE_INSTALACION de Vercel)
// =========================================================
require __DIR__ . '/config/config.php';
require __DIR__ . '/config/bd.php';
header('Content-Type: text/html; charset=utf-8');

function salir($html, $color = '#b91c1c') {
    exit("<div style='font-family:sans-serif;max-width:560px;margin:80px auto'><h2 style='color:$color'>$html</div>");
}

if (CLAVE_INSTALACION === '' || !hash_equals(CLAVE_INSTALACION, (string)($_GET['clave'] ?? ''))) {
    http_response_code(403);
    salir("Acceso denegado</h2><p>Agregá <code>?clave=...</code> con el valor de la variable <b>CLAVE_INSTALACION</b>.</p>");
}

try {
    $pdo = conectarBD();
    $existe = $pdo->query("SELECT COUNT(*) FROM information_schema.tables
                           WHERE table_schema = DATABASE() AND table_name = 'usuarios'")->fetchColumn();
    if ($existe && ($_GET['forzar'] ?? '') !== '1' && ($_GET['actualizar'] ?? '') !== '1') {
        salir("La base ya está instalada</h2><p>No se tocó nada. Si querés agregar solo las tablas nuevas (sesiones, fotos y chatbot) sin borrar datos, entrá con <code>&amp;actualizar=1</code>.</p>", '#b45309');
    }

    $archivo = ($existe && ($_GET['actualizar'] ?? '') === '1') ? 'actualizar' : 'esquema';
    if ($archivo === 'actualizar') {
        // Solo las tablas nuevas (CREATE TABLE IF NOT EXISTS)
        $sql = file_get_contents(__DIR__ . '/config/esquema.sql');
        preg_match_all('/CREATE TABLE IF NOT EXISTS.*?ENGINE=InnoDB;/s', $sql, $m);
        $sql = implode("\n", $m[0]);
    } else {
        $sql = file_get_contents(__DIR__ . '/config/esquema.sql');
        if ($existe) {
            // forzar=1: borra las tablas y vuelve a crear todo con los datos de prueba
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            foreach ($pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()")
                         ->fetchAll(PDO::FETCH_COLUMN) as $t) {
                $pdo->exec("DROP TABLE IF EXISTS `" . str_replace('`', '', $t) . "`");
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        }
    }

    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $st = $pdo->prepare($sql);
    $st->execute();
    do { } while ($st->nextRowset());

    $tablas = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    salir("Base de datos lista</h2><p>Tablas en la base: <b>$tablas</b></p><p><a href='" . BASE_URL . "'>Ir a la página</a></p>", '#15803d');
} catch (Throwable $e) {
    salir("Error</h2><pre style='white-space:pre-wrap'>" . htmlspecialchars($e->getMessage()) . "</pre>");
}
