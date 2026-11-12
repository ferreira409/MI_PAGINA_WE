<?php
// =========================================================
//  CHAT DE LA PÁGINA WEB
//  El globito del asistente (js/chatbot.js) manda aquí cada mensaje.
//  Usa el mismo motor que Telegram y WhatsApp.
// =========================================================
require __DIR__ . '/../config/conexion.php';   // sesión + CSRF (el token viaja en el campo "csrf")
require __DIR__ . '/motor.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => 'Usá POST']));
}

// Límite anti-spam: 30 mensajes por minuto por visitante
$ahora = time();
$_SESSION['chat_tiempos'] = array_values(array_filter($_SESSION['chat_tiempos'] ?? [], fn($t) => $t > $ahora - 60));
if (count($_SESSION['chat_tiempos']) >= 30) {
    http_response_code(429);
    exit(json_encode(['ok' => false, 'error' => 'Estás escribiendo muy rápido. Esperá un momento.'], JSON_UNESCAPED_UNICODE));
}
$_SESSION['chat_tiempos'][] = $ahora;

// Cada visitante tiene su propia conversación (se guarda en la sesión)
if (empty($_SESSION['chat_web'])) $_SESSION['chat_web'] = bin2hex(random_bytes(12));

$mensaje = mb_substr(trim((string)($_POST['mensaje'] ?? '')), 0, 300);
if ($mensaje === '') exit(json_encode(['ok' => true, 'respuestas' => []]));

$u = usuarioActual();
$perfil = [
    'nombre'     => $u['nombre'] ?? '',
    'usuario_id' => ($u && $u['rol'] === 'cliente') ? (int)$u['id'] : null,   // si es cliente, la cita va a su cuenta
];

$respuestas = botProcesar($pdo, 'web', $_SESSION['chat_web'], $mensaje, $perfil, 'web' . bin2hex(random_bytes(6)));
echo json_encode(['ok' => true, 'respuestas' => $respuestas], JSON_UNESCAPED_UNICODE);
