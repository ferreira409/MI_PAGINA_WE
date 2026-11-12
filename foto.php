<?php
// =========================================================
//  Muestra la foto de un negocio guardada en la base de datos
//  Uso: foto.php?id=5
// =========================================================
require __DIR__ . '/config/config.php';
require __DIR__ . '/config/bd.php';

$id = (int)($_GET['id'] ?? 0);
$st = conectarBD()->prepare("SELECT mime, datos FROM fotos WHERE id = ?");
$st->execute([$id]);
$foto = $st->fetch();

if (!$foto || !in_array($foto['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $foto['mime']);
header('Content-Length: ' . strlen($foto['datos']));
header('Cache-Control: public, max-age=31536000, immutable');   // el id cambia si se sube otra foto
header('X-Content-Type-Options: nosniff');
echo $foto['datos'];
