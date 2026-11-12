<?php
// =========================================================
//  Configura y revisa el chatbot
//  Uso:  /bot/configurar.php?clave=TU_CLAVE_INSTALACION
//  - Registra el webhook de Telegram (una sola vez)
//  - Muestra qué variables de WhatsApp faltan
// =========================================================
require __DIR__ . '/motor.php';
header('Content-Type: text/html; charset=utf-8');

if (CLAVE_INSTALACION === '' || !hash_equals(CLAVE_INSTALACION, (string)($_GET['clave'] ?? ''))) {
    http_response_code(403);
    exit('Acceso denegado: agregá ?clave=... con el valor de CLAVE_INSTALACION');
}

$https = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || !empty($_SERVER['HTTPS'])) ? 'https' : 'http';
$base  = $https . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;

function fila($ok, $texto) {
    echo '<li style="margin:6px 0">' . ($ok ? '✅' : '❌') . ' ' . $texto . '</li>';
}

function llamarTelegram($metodo, $datos = []) {
    $ch = curl_init('https://api.telegram.org/bot' . TELEGRAM_TOKEN . '/' . $metodo);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($datos),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $r = json_decode((string)curl_exec($ch), true);
    curl_close($ch);
    return $r ?: ['ok' => false, 'description' => 'Sin respuesta de Telegram'];
}
?>
<!doctype html>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Configurar chatbot</title>
<body style="font-family:sans-serif;max-width:680px;margin:40px auto;padding:0 16px;line-height:1.5">
<h1>🤖 Chatbot de <?= NOMBRE_SITIO ?></h1>

<h2>Base de datos</h2>
<ul>
<?php
try {
    $pdo = conectarBD();
    $pdo->query("SELECT 1 FROM bot_conversaciones LIMIT 1");
    fila(true, 'Conectada y con la tabla <code>bot_conversaciones</code>');
} catch (Throwable $e) {
    fila(false, 'Falta la tabla del bot. Entrá a <code>/instalar_bd.php?clave=...&amp;actualizar=1</code>');
}
?>
</ul>

<h2>Telegram</h2>
<ul>
<?php
if (TELEGRAM_TOKEN === '' || TELEGRAM_SECRET === '') {
    fila(false, 'Faltan las variables <code>TELEGRAM_TOKEN</code> y/o <code>TELEGRAM_SECRET</code> en Vercel.');
} else {
    $me = llamarTelegram('getMe');
    fila($me['ok'] ?? false, ($me['ok'] ?? false) ? 'Token válido: bot <b>@' . htmlspecialchars($me['result']['username']) . '</b>'
                                                  : 'Token no válido: ' . htmlspecialchars($me['description'] ?? ''));
    if ($me['ok'] ?? false) {
        $r = llamarTelegram('setWebhook', [
            'url'             => $base . 'bot/telegram.php',
            'secret_token'    => TELEGRAM_SECRET,
            'allowed_updates' => ['message', 'callback_query'],
            'drop_pending_updates' => true,
        ]);
        fila($r['ok'] ?? false, 'Webhook: ' . htmlspecialchars($base . 'bot/telegram.php') . ' — ' . htmlspecialchars($r['description'] ?? ''));
        llamarTelegram('setMyCommands', ['commands' => [
            ['command' => 'start', 'description' => 'Empezar'],
            ['command' => 'menu',  'description' => 'Menú principal'],
        ]]);
        echo '<li>👉 Probalo: <a href="https://t.me/' . htmlspecialchars($me['result']['username']) . '">t.me/' . htmlspecialchars($me['result']['username']) . '</a></li>';
    }
}
?>
</ul>

<h2>WhatsApp (Meta)</h2>
<ul>
<?php
foreach (['WA_TOKEN', 'WA_PHONE_ID', 'WA_VERIFY_TOKEN', 'WA_APP_SECRET'] as $v) {
    fila(constant($v) !== '', "<code>$v</code>" . (constant($v) !== '' ? ' configurada' : ' falta'));
}
?>
<li>En Meta &gt; WhatsApp &gt; Configuración &gt; Webhook poné:<br>
    URL: <code><?= htmlspecialchars($base . 'bot/whatsapp.php') ?></code><br>
    Token de verificación: el mismo valor de <code>WA_VERIFY_TOKEN</code><br>
    Y suscribite al campo <b>messages</b>.</li>
</ul>
</body>
