<?php
// =========================================================
//  WEBHOOK DE WHATSAPP (WhatsApp Cloud API de Meta)
//  URL de devolución de llamada en Meta:
//      https://TU-PROYECTO.vercel.app/bot/whatsapp.php
//  Token de verificación: el valor de WA_VERIFY_TOKEN
// =========================================================
require __DIR__ . '/motor.php';

// ---------- 1. Verificación del webhook (Meta llama con GET una sola vez) ----------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (($_GET['hub_mode'] ?? '') === 'subscribe' && WA_VERIFY_TOKEN !== ''
        && hash_equals(WA_VERIFY_TOKEN, (string)($_GET['hub_verify_token'] ?? ''))) {
        header('Content-Type: text/plain');
        exit((string)($_GET['hub_challenge'] ?? ''));
    }
    http_response_code(403);
    exit('Acceso denegado');
}

// ---------- 2. Validar que el mensaje viene de Meta (firma con el App Secret) ----------
$cuerpo = file_get_contents('php://input');
if (WA_APP_SECRET !== '') {
    $firma = 'sha256=' . hash_hmac('sha256', $cuerpo, WA_APP_SECRET);
    if (!hash_equals($firma, $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '')) {
        http_response_code(403);
        exit('Firma no válida');
    }
}
if (WA_TOKEN === '' || WA_PHONE_ID === '') exit;

function whatsapp(array $datos): ?array
{
    $ch = curl_init('https://graph.facebook.com/' . WA_API_VERSION . '/' . WA_PHONE_ID . '/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($datos, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . WA_TOKEN],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
    ]);
    $r = curl_exec($ch);
    if ($r && curl_getinfo($ch, CURLINFO_HTTP_CODE) >= 400) error_log('WhatsApp API: ' . $r);
    curl_close($ch);
    return $r ? json_decode($r, true) : null;
}

function cortar(string $t, int $max): string
{
    return mb_strlen($t) <= $max ? $t : mb_substr($t, 0, $max - 1) . '…';
}

// Convierte una respuesta del motor al formato de WhatsApp:
//  - sin opciones: texto
//  - hasta 3 opciones: botones
//  - 4 a 10 opciones: lista desplegable
function enviarWhatsapp(string $numero, array $r): void
{
    $base = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $numero];
    $ops  = array_slice($r['opciones'], 0, 10);

    if (!$ops) {
        whatsapp($base + ['type' => 'text', 'text' => ['body' => cortar($r['texto'], 4096), 'preview_url' => false]]);
        return;
    }
    if (count($ops) <= 3) {
        $botones = array_map(fn($o) => ['type' => 'reply', 'reply' => ['id' => $o['id'], 'title' => cortar($o['titulo'], 20)]], $ops);
        whatsapp($base + ['type' => 'interactive', 'interactive' => [
            'type'   => 'button',
            'body'   => ['text' => cortar($r['texto'], 1024)],
            'action' => ['buttons' => $botones],
        ]]);
        return;
    }
    $filas = array_map(fn($o) => array_filter([
        'id'          => $o['id'],
        'title'       => cortar($o['titulo'], 24),
        'description' => $o['desc'] !== '' ? cortar($o['desc'], 72) : null,
    ]), $ops);
    whatsapp($base + ['type' => 'interactive', 'interactive' => [
        'type'   => 'list',
        'body'   => ['text' => cortar($r['texto'], 1024)],
        'action' => ['button' => 'Ver opciones', 'sections' => [['title' => 'Elegí una opción', 'rows' => array_values($filas)]]],
    ]]);
}

// ---------- 3. Procesar los mensajes recibidos ----------
$datos = json_decode($cuerpo, true) ?: [];
$pdo = conectarBD();

foreach ($datos['entry'] ?? [] as $entry) {
    foreach ($entry['changes'] ?? [] as $cambio) {
        $valor = $cambio['value'] ?? [];
        $nombres = [];
        foreach ($valor['contacts'] ?? [] as $c) $nombres[$c['wa_id']] = $c['profile']['name'] ?? '';

        foreach ($valor['messages'] ?? [] as $m) {      // (los "statuses" de entregado/leído se ignoran)
            $numero = (string)$m['from'];
            $entrada = match ($m['type'] ?? '') {
                'text'        => $m['text']['body'] ?? '',
                'interactive' => $m['interactive']['button_reply']['id'] ?? $m['interactive']['list_reply']['id'] ?? '',
                'button'      => $m['button']['payload'] ?? $m['button']['text'] ?? '',
                default       => '',
            };
            if ($entrada === '') {
                enviarWhatsapp($numero, ['texto' => 'Por ahora solo entiendo mensajes de texto 🙂 Escribí *menu* para empezar.', 'opciones' => []]);
                continue;
            }
            // Marca el mensaje como leído (los dos ticks azules)
            whatsapp(['messaging_product' => 'whatsapp', 'status' => 'read', 'message_id' => $m['id']]);

            $perfil = ['nombre' => $nombres[$numero] ?? ''];
            foreach (botProcesar($pdo, 'whatsapp', $numero, $entrada, $perfil, $m['id']) as $respuesta) {
                enviarWhatsapp($numero, $respuesta);
            }
        }
    }
}
http_response_code(200);
echo 'OK';
