<?php
// =========================================================
//  WEBHOOK DE TELEGRAM
//  Telegram llama a esta página cada vez que alguien le escribe al bot.
//  URL: https://TU-PROYECTO.vercel.app/bot/telegram.php
//  (se registra una sola vez con bot/configurar.php)
// =========================================================
require __DIR__ . '/motor.php';

// Solo Telegram conoce el secreto (se manda en esta cabecera)
if (TELEGRAM_TOKEN === '' || TELEGRAM_SECRET === ''
    || !hash_equals(TELEGRAM_SECRET, $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '')) {
    http_response_code(403);
    exit('Acceso denegado');
}

function telegram(string $metodo, array $datos): ?array
{
    $ch = curl_init('https://api.telegram.org/bot' . TELEGRAM_TOKEN . '/' . $metodo);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($datos, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r ? json_decode($r, true) : null;
}

// *negrita* -> <b>negrita</b> (Telegram usa HTML)
function aHtmlTelegram(string $texto): string
{
    $texto = htmlspecialchars($texto, ENT_NOQUOTES, 'UTF-8');
    return preg_replace('/\*([^*\n]+)\*/u', '<b>$1</b>', $texto);
}

function enviarTelegram($chatId, array $respuesta): void
{
    $datos = ['chat_id' => $chatId, 'text' => aHtmlTelegram($respuesta['texto']), 'parse_mode' => 'HTML',
              'link_preview_options' => ['is_disabled' => true]];
    if ($respuesta['opciones']) {
        $filas = [];
        foreach ($respuesta['opciones'] as $o) {
            $texto = $o['titulo'] . ($o['desc'] !== '' ? ' — ' . $o['desc'] : '');
            $filas[] = [['text' => mb_substr($texto, 0, 60), 'callback_data' => substr($o['id'], 0, 64)]];
        }
        $datos['reply_markup'] = ['inline_keyboard' => $filas];
    }
    telegram('sendMessage', $datos);
}

// ---------- Procesar la actualización ----------
$update = json_decode(file_get_contents('php://input'), true) ?: [];
$pdo = conectarBD();

if (isset($update['callback_query'])) {                 // tocó un botón
    $cb = $update['callback_query'];
    telegram('answerCallbackQuery', ['callback_query_id' => $cb['id']]);
    $chatId  = (string)$cb['message']['chat']['id'];
    $entrada = (string)($cb['data'] ?? '');
    $de      = $cb['from'] ?? [];
} elseif (isset($update['message'])) {                  // escribió un mensaje
    $m = $update['message'];
    $chatId  = (string)$m['chat']['id'];
    $entrada = (string)($m['text'] ?? '');
    $de      = $m['from'] ?? [];
    if ($entrada === '') {
        enviarTelegram($chatId, ['texto' => 'Por ahora solo entiendo mensajes de texto 🙂 Escribí *menu* para empezar.', 'opciones' => []]);
        exit;
    }
} else {
    exit;                                               // otro tipo de actualización: se ignora
}

$perfil = ['nombre' => trim(($de['first_name'] ?? '') . ' ' . ($de['last_name'] ?? ''))];
foreach (botProcesar($pdo, 'telegram', $chatId, $entrada, $perfil, 'tg' . ($update['update_id'] ?? '')) as $respuesta) {
    enviarTelegram($chatId, $respuesta);
}
