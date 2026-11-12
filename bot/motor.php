<?php
// =========================================================
//  MOTOR DEL CHATBOT (común para Telegram y WhatsApp)
//
//  Recibe lo que escribió (o tocó) la persona y devuelve las
//  respuestas. No sabe nada de Telegram ni de WhatsApp: cada
//  canal convierte las respuestas a su formato.
//
//  Cada respuesta es: ['texto' => '...', 'opciones' => [
//        ['id' => 'n:3', 'titulo' => 'Barbería', 'desc' => '...'], ...]]
//  En el texto, *así* significa negrita.
// =========================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/bd.php';
require_once __DIR__ . '/../config/funciones.php';

const BOT_POR_PAGINA = 8;         // opciones por mensaje (WhatsApp permite 10 en total)
const BOT_MINUTOS_SESION = 60;    // después de este tiempo sin escribir, vuelve al menú

function urlSitio($ruta = '') {
    $base = env('URL_SITIO') ?: (env('VERCEL_PROJECT_PRODUCTION_URL') ? 'https://' . env('VERCEL_PROJECT_PRODUCTION_URL') : '');
    if (!$base && !empty($_SERVER['HTTP_HOST']) && PHP_SAPI !== 'cli') {     // chat de la página: el mismo sitio
        $https = !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $base = ($https ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . rtrim(BASE_URL, '/');
    }
    return $base ? rtrim($base, '/') . '/' . ltrim($ruta, '/') : '';
}

// ---------------------------------------------------------
//  Punto de entrada
// ---------------------------------------------------------
function botProcesar(PDO $pdo, string $canal, string $chatId, string $entrada, array $perfil = [], ?string $idMensaje = null): array
{
    $conv = botCargarConversacion($pdo, $canal, $chatId);

    // WhatsApp y Telegram a veces reenvían el mismo mensaje: se ignora
    if ($idMensaje !== null && $conv['ultimo_mensaje'] === $idMensaje) return [];
    $conv['ultimo_mensaje'] = $idMensaje;

    // Si pasó mucho tiempo, se empieza de nuevo
    if (strtotime($conv['actualizado']) < time() - BOT_MINUTOS_SESION * 60) {
        $conv['estado'] = 'menu';
        $conv['datos'] = [];
    }

    $bot = new Bot($pdo, $canal, $chatId, $conv, $perfil);
    $respuestas = $bot->atender(trim($entrada));
    botGuardarConversacion($pdo, $bot->conv);
    return $respuestas;
}

function botCargarConversacion(PDO $pdo, string $canal, string $chatId): array
{
    $sql = "SELECT * FROM bot_conversaciones WHERE canal = ? AND chat_id = ?";
    try {
        $st = $pdo->prepare($sql);
        $st->execute([$canal, $chatId]);
    } catch (PDOException $ex) {
        if ($ex->getCode() !== '42S02') throw $ex;   // la tabla todavía no existe: se crea sola
        botCrearTabla($pdo);
        $st = $pdo->prepare($sql);
        $st->execute([$canal, $chatId]);
    }
    $c = $st->fetch();
    if (!$c) {
        $pdo->prepare("INSERT INTO bot_conversaciones (canal, chat_id, estado, datos) VALUES (?, ?, 'menu', '{}')")
            ->execute([$canal, $chatId]);
        return botCargarConversacion($pdo, $canal, $chatId);
    }
    $c['datos'] = json_decode($c['datos'] ?: '{}', true) ?: [];
    return $c;
}

// Crea la tabla del chatbot si la base es vieja (igual que actualizar_vercel_bot.sql)
function botCrearTabla(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS bot_conversaciones (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        canal          ENUM('telegram','whatsapp','web') NOT NULL,
        chat_id        VARCHAR(40) NOT NULL,
        usuario_id     INT NULL,
        estado         VARCHAR(30) NOT NULL DEFAULT 'menu',
        datos          TEXT,
        ultimo_mensaje VARCHAR(120),
        actualizado    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_chat (canal, chat_id),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function botGuardarConversacion(PDO $pdo, array $c): void
{
    $pdo->prepare("UPDATE bot_conversaciones SET estado = ?, datos = ?, usuario_id = ?, ultimo_mensaje = ?,
                   actualizado = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$c['estado'], json_encode($c['datos'], JSON_UNESCAPED_UNICODE), $c['usuario_id'] ?: null,
                   $c['ultimo_mensaje'], $c['id']]);
}

// ---------------------------------------------------------
//  La conversación
// ---------------------------------------------------------
class Bot
{
    public array $conv;
    private array $salida = [];

    public function __construct(private PDO $pdo, private string $canal, private string $chatId, array $conv, private array $perfil)
    {
        $this->conv = $conv;
        // En la página web, si el cliente inició sesión, la cita va a su cuenta
        if ($canal === 'web' && !empty($perfil['usuario_id'])) $this->conv['usuario_id'] = (int)$perfil['usuario_id'];
    }

    // ---------- Utilidades ----------
    private function decir(string $texto, array $opciones = []): void
    {
        $this->salida[] = ['texto' => $texto, 'opciones' => $opciones];
        // Se guardan los id para aceptar también que escriban el número (1, 2, 3...)
        $this->conv['datos']['ops'] = array_column($opciones, 'id');
    }

    private function ir(string $estado): void { $this->conv['estado'] = $estado; }
    private function &d(): array { return $this->conv['datos']; }

    private static function op(string $id, string $titulo, string $desc = ''): array
    {
        return ['id' => $id, 'titulo' => $titulo, 'desc' => $desc];
    }

    private static function normalizar(string $t): string
    {
        $t = mb_strtolower(trim($t));
        return strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', '¿' => '', '?' => '', '¡' => '', '!' => '']);
    }

    private function opcionMenu(): array { return self::op('menu', '🏠 Menú principal'); }

    // ---------- Decide qué hacer con lo que llegó ----------
    public function atender(string $entrada): array
    {
        $t = self::normalizar($entrada);

        // Si escribió un número, se traduce a la opción que se le mostró
        $ops = $this->conv['datos']['ops'] ?? [];
        if (preg_match('/^\d{1,2}$/', $t) && isset($ops[(int)$t - 1]) && !in_array($this->conv['estado'], ['pedir_telefono'], true)) {
            $entrada = $t = $ops[(int)$t - 1];
        }

        // Comandos que funcionan en cualquier momento
        if (in_array($t, ['/start', 'menu', 'inicio', 'hola', 'buenas', 'buen dia', 'buenos dias', 'buenas tardes', 'buenas noches', 'cancelar', 'salir', '/menu'], true)) {
            $this->menu($t === '/start' || str_starts_with($t, 'hola') || str_starts_with($t, 'buen'));
            return $this->salida;
        }

        // Botones (id con formato "tipo:valor")
        [$tipo, $valor] = array_pad(explode(':', $entrada, 2), 2, '');
        switch ($tipo) {
            case 'reservar': $this->elegirNegocio(0); return $this->salida;
            case 'faq':      $this->faqMenu();        return $this->salida;
            case 'nmas':     $this->elegirNegocio((int)$valor, $this->conv['estado'] === 'info_negocio' ? 'i' : 'n'); return $this->salida;
            case 'n':        $this->elegirServicio((int)$valor); return $this->salida;
            case 's':        $this->elegirFecha((int)$valor);    return $this->salida;
            case 'f':        $this->elegirHora($valor, 0);       return $this->salida;
            case 'hmas':     $this->elegirHora($this->d()['fecha'] ?? '', (int)$valor); return $this->salida;
            case 'h':        $this->elegirHoraLista($valor);     return $this->salida;
            case 'si':       if ($this->conv['estado'] === 'confirmar') { $this->confirmar(); return $this->salida; } break;
            case 'no':       if ($this->conv['estado'] === 'confirmar') {
                                 $this->decir('Listo, no se guardó la cita.', [self::op('reservar', '📅 Reservar otra'), $this->opcionMenu()]);
                                 $this->ir('menu'); return $this->salida;
                             } break;
            case 'q':        $this->faqRespuesta($valor); return $this->salida;
            case 'i':        $this->infoNegocio((int)$valor); return $this->salida;
        }

        // Texto libre según el paso en que está
        switch ($this->conv['estado']) {
            case 'pedir_nombre':   $this->recibirNombre($entrada);   return $this->salida;
            case 'pedir_telefono': $this->recibirTelefono($entrada); return $this->salida;
        }

        // Preguntas escritas con palabras
        $this->interpretarTexto($t);
        return $this->salida;
    }

    // ---------- Menú ----------
    private function menu(bool $saludo = false): void
    {
        $this->conv['datos'] = [];
        $this->ir('menu');
        $nombre = $this->perfil['nombre'] ?? '';
        $texto = ($saludo ? '¡Hola' . ($nombre ? ' ' . $nombre : '') . "! 👋\n" : '')
               . 'Soy el asistente de *' . NOMBRE_SITIO . ' SCZ*. Te ayudo a reservar citas en negocios de ' . CIUDAD . ".\n\n¿Qué querés hacer?";
        $this->decir($texto, [
            self::op('reservar', '📅 Reservar una cita', 'Elegí negocio, servicio, día y hora'),
            self::op('faq', '❓ Preguntas frecuentes', 'Horarios, precios, pagos y más'),
        ]);
    }

    // ---------- Reserva: paso 1, negocio ----------
    private function elegirNegocio(int $pagina, string $prefijo = 'n'): void
    {
        $negocios = listarNegocios($this->pdo);
        if (!$negocios) {
            $this->decir('Por ahora no hay negocios disponibles. 😕', [$this->opcionMenu()]);
            return;
        }
        $this->ir($prefijo === 'n' ? 'elegir_negocio' : 'info_negocio');
        $total = count($negocios);
        $pagina = max(0, min($pagina, intdiv($total - 1, BOT_POR_PAGINA)));
        $ops = [];
        foreach (array_slice($negocios, $pagina * BOT_POR_PAGINA, BOT_POR_PAGINA) as $n) {
            $estrellas = $n['promedio'] !== null ? ' · ⭐ ' . $n['promedio'] : '';
            $ops[] = self::op("$prefijo:{$n['id']}", $n['nombre'], $n['categoria'] . ' · ' . $n['zona'] . $estrellas);
        }
        if (($pagina + 1) * BOT_POR_PAGINA < $total) $ops[] = self::op('nmas:' . ($pagina + 1), '➡️ Ver más negocios');
        $ops[] = $this->opcionMenu();
        $this->decir($prefijo === 'n' ? '¿En qué negocio querés reservar?' : '¿De qué negocio querés ver la información?', $ops);
    }

    // ---------- Reserva: paso 2, servicio ----------
    private function elegirServicio(int $idNegocio): void
    {
        $n = obtenerNegocio($this->pdo, $idNegocio);
        if (!$n) { $this->decir('Ese negocio ya no está disponible.'); $this->elegirNegocio(0); return; }
        if (!$n['servicios']) {
            $this->decir('*' . $n['nombre'] . '* todavía no tiene servicios cargados.', [self::op('reservar', '⬅️ Elegir otro negocio'), $this->opcionMenu()]);
            return;
        }
        $this->conv['datos'] = ['negocio' => $n['id']];
        $this->ir('elegir_servicio');
        $ops = [];
        foreach (array_slice($n['servicios'], 0, 9) as $s) {
            $ops[] = self::op("s:{$s['id']}", $s['nombre'], 'Bs ' . number_format($s['precio'], 0) . ' · ' . (int)$s['duracion_min'] . ' min');
        }
        $ops[] = $this->opcionMenu();
        $this->decir("*{$n['nombre']}*\n📍 {$n['direccion']}, {$n['zona']}\n\n¿Qué servicio querés?", $ops);
    }

    // ---------- Reserva: paso 3, día ----------
    private function elegirFecha(int $idServicio): void
    {
        $idNegocio = (int)($this->d()['negocio'] ?? 0);
        $st = $this->pdo->prepare("SELECT * FROM servicios WHERE id = ? AND negocio_id = ?");
        $st->execute([$idServicio, $idNegocio]);
        $s = $st->fetch();
        if (!$s) { $this->decir('Primero elegí un negocio.'); $this->elegirNegocio(0); return; }

        $this->conv['datos']['servicio'] = $s['id'];
        $this->ir('elegir_fecha');
        $dias = [1 => 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        $ops = [];
        for ($i = 0; $i < min(14, DIAS_MAX_RESERVA) && count($ops) < BOT_POR_PAGINA; $i++) {
            $f = date('Y-m-d', strtotime("+$i days"));
            $libres = horasDisponibles($this->pdo, $idNegocio, $f, (int)$s['duracion_min']);
            if (!$libres) continue;
            $ts = strtotime($f);
            $etiqueta = ($i === 0 ? 'Hoy' : ($i === 1 ? 'Mañana' : $dias[(int)date('N', $ts)])) . ' ' . date('d/m', $ts);
            $ops[] = self::op("f:$f", $etiqueta, count($libres) . ' horarios libres');
        }
        if (!$ops) {
            $this->decir('No hay horarios libres en los próximos días para *' . $s['nombre'] . '*. 😕', [self::op('n:' . $idNegocio, '⬅️ Otro servicio'), $this->opcionMenu()]);
            return;
        }
        $ops[] = $this->opcionMenu();
        $this->decir('*' . $s['nombre'] . '* (Bs ' . number_format($s['precio'], 0) . ")\n¿Qué día te queda bien?", $ops);
    }

    // ---------- Reserva: paso 4, hora ----------
    private function elegirHora(string $fecha, int $pagina): void
    {
        $s = $this->servicioElegido();
        if (!$s || !fechaValida($fecha)) { $this->decir('Empecemos de nuevo.'); $this->elegirNegocio(0); return; }

        $libres = horasDisponibles($this->pdo, $s['negocio_id'], $fecha, (int)$s['duracion_min']);
        if (!$libres) {
            $this->decir('Ese día ya no tiene horarios libres.');
            $this->elegirFecha((int)$s['id']);
            return;
        }
        $this->conv['datos']['fecha'] = $fecha;
        $this->ir('elegir_hora');
        $total = count($libres);
        $pagina = max(0, min($pagina, intdiv($total - 1, BOT_POR_PAGINA)));
        $ops = [];
        foreach (array_slice($libres, $pagina * BOT_POR_PAGINA, BOT_POR_PAGINA) as $h) {
            $ops[] = self::op("h:$h", '🕐 ' . $h, 'Termina ' . aHora(aMinutos($h) + (int)$s['duracion_min']));
        }
        if (($pagina + 1) * BOT_POR_PAGINA < $total) $ops[] = self::op('hmas:' . ($pagina + 1), '➡️ Más tarde');
        elseif ($pagina > 0) $ops[] = self::op('hmas:0', '⬅️ Más temprano');
        $ops[] = $this->opcionMenu();
        $this->decir('📅 ' . DIAS[(int)date('N', strtotime($fecha))] . ' ' . date('d/m/Y', strtotime($fecha)) . "\n¿A qué hora?", $ops);
    }

    private function elegirHoraLista(string $hora): void
    {
        if (!horaValida($hora) || empty($this->d()['fecha'])) { $this->elegirNegocio(0); return; }
        $this->conv['datos']['hora'] = $hora;
        $this->siguienteDatoCliente();
    }

    // ---------- Reserva: datos del cliente ----------
    private function siguienteDatoCliente(): void
    {
        // WhatsApp: el número ya lo sabemos y puede estar registrado en la web
        if (empty($this->conv['usuario_id']) && $this->canal === 'whatsapp') {
            $this->conv['usuario_id'] = $this->buscarUsuarioPorTelefono($this->chatId);
        }
        if (!empty($this->conv['usuario_id'])) { $this->resumen(); return; }

        if (empty($this->d()['nombre'])) {
            $this->ir('pedir_nombre');
            $sugerido = $this->perfil['nombre'] ?? '';
            $this->decir('¿A nombre de quién hacemos la reserva? Escribí tu *nombre y apellido*.',
                         $sugerido ? [self::op('usar_nombre', '👤 ' . mb_substr($sugerido, 0, 20))] : []);
            return;
        }
        if ($this->canal !== 'whatsapp' && empty($this->d()['telefono'])) {
            $this->ir('pedir_telefono');
            $this->decir('¿Cuál es tu número de celular? (ej: 70012345). El negocio lo usa para avisarte.');
            return;
        }
        $this->resumen();
    }

    private function recibirNombre(string $texto): void
    {
        if ($texto === 'usar_nombre') $texto = (string)($this->perfil['nombre'] ?? '');
        $texto = trim(preg_replace('/\s+/', ' ', $texto));
        if (mb_strlen($texto) < 3 || mb_strlen($texto) > 80 || preg_match('/[<>{}]/', $texto)) {
            $this->decir('Escribí tu nombre y apellido, por favor (ej: Ana Pérez).');
            return;
        }
        $this->conv['datos']['nombre'] = $texto;
        $this->siguienteDatoCliente();
    }

    private function recibirTelefono(string $texto): void
    {
        $num = preg_replace('/\D/', '', $texto);
        if (strlen($num) < 7 || strlen($num) > 15) {
            $this->decir('Ese número no parece válido. Escribilo solo con números, ej: 70012345.');
            return;
        }
        $this->conv['datos']['telefono'] = $num;
        $this->siguienteDatoCliente();
    }

    private function resumen(): void
    {
        $s = $this->servicioElegido();
        $n = $s ? obtenerNegocio($this->pdo, $s['negocio_id']) : null;
        $d = $this->d();
        if (!$s || !$n || empty($d['fecha']) || empty($d['hora'])) { $this->elegirNegocio(0); return; }

        $nombre = $d['nombre'] ?? $this->nombreUsuario();
        $this->ir('confirmar');
        $this->decir("Revisá tu reserva:\n\n"
            . "🏪 *{$n['nombre']}*\n"
            . "📝 {$s['nombre']} ({$s['duracion_min']} min)\n"
            . '📅 ' . DIAS[(int)date('N', strtotime($d['fecha']))] . ' ' . date('d/m/Y', strtotime($d['fecha'])) . "\n"
            . "🕐 {$d['hora']}\n"
            . '💵 Bs ' . number_format($s['precio'], 2) . " (se paga en el local)\n"
            . "👤 $nombre\n\n¿Confirmás?",
            [self::op('si', '✅ Sí, reservar'), self::op('no', '❌ No')]);
    }

    private function confirmar(): void
    {
        $s = $this->servicioElegido();
        $d = $this->d();
        if (!$s || empty($d['fecha']) || empty($d['hora'])) { $this->elegirNegocio(0); return; }

        $idUsuario = $this->conv['usuario_id'] ?: $this->crearUsuarioBot();
        $this->conv['usuario_id'] = $idUsuario;

        $r = crearCita($this->pdo, $idUsuario, $s['negocio_id'], $s['id'], $d['fecha'], $d['hora'],
                       'Reservado por ' . ['telegram' => 'Telegram', 'whatsapp' => 'WhatsApp', 'web' => 'el chat de la página'][$this->canal]);
        if (!$r['ok']) {
            $this->decir('😕 ' . $r['error']);
            $this->elegirHora($d['fecha'], 0);
            return;
        }
        $n = obtenerNegocio($this->pdo, $s['negocio_id']);
        $texto = "🎉 *¡Cita reservada!* (N° {$r['id']})\n\n"
               . "Te esperamos en *{$n['nombre']}* el " . date('d/m/Y', strtotime($d['fecha'])) . " a las {$d['hora']}.\n"
               . "📍 {$n['direccion']}, {$n['zona']}\n\n"
               . 'El negocio la va a confirmar pronto.';
        if ($n['whatsapp']) $texto .= "\n💬 WhatsApp del negocio: +" . normalizarWhatsapp($n['whatsapp']);
        $this->conv['datos'] = [];
        $this->ir('menu');
        $this->decir($texto, [self::op('reservar', '📅 Reservar otra cita'), $this->opcionMenu()]);
    }

    private function servicioElegido(): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM servicios WHERE id = ? AND negocio_id = ?");
        $st->execute([(int)($this->d()['servicio'] ?? 0), (int)($this->d()['negocio'] ?? 0)]);
        return $st->fetch() ?: null;
    }

    private function nombreUsuario(): string
    {
        $st = $this->pdo->prepare("SELECT nombre FROM usuarios WHERE id = ?");
        $st->execute([(int)$this->conv['usuario_id']]);
        return (string)$st->fetchColumn();
    }

    // Busca un cliente de la web con ese número (solo WhatsApp, donde el número es verificado)
    private function buscarUsuarioPorTelefono(string $numero): ?int
    {
        $completo = normalizarWhatsapp($numero);
        $corto = str_starts_with($completo, '591') ? substr($completo, 3) : $completo;
        $st = $this->pdo->prepare("SELECT id FROM usuarios WHERE activo = 1 AND rol = 'cliente'
                                   AND REPLACE(REPLACE(telefono, ' ', ''), '+', '') IN (?, ?) LIMIT 1");
        $st->execute([$completo, $corto]);
        $id = $st->fetchColumn();
        return $id ? (int)$id : null;
    }

    // Crea un cliente para quien reserva por el chat (sin contraseña usable)
    private function crearUsuarioBot(): int
    {
        $d = $this->d();
        $prefijo = ['telegram' => 'tg', 'whatsapp' => 'wa', 'web' => 'web'][$this->canal];
        $email = $prefijo . preg_replace('/[^a-z0-9]/i', '', $this->chatId) . '@bot.agendafacil.local';
        $st = $this->pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $st->execute([$email]);
        if ($id = $st->fetchColumn()) return (int)$id;

        $telefono = $this->canal === 'whatsapp' ? normalizarWhatsapp($this->chatId) : ($d['telefono'] ?? null);
        $this->pdo->prepare("INSERT INTO usuarios (nombre, email, telefono, password, rol) VALUES (?, ?, ?, ?, 'cliente')")
            ->execute([$d['nombre'] ?? 'Cliente del chat', $email, $telefono, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
        return (int)$this->pdo->lastInsertId();
    }

    // ---------- Preguntas frecuentes ----------
    private function faqMenu(): void
    {
        $this->ir('faq');
        $this->decir('❓ *Preguntas frecuentes*. Elegí una:', [
            self::op('q:negocios', '🏪 Horarios y precios', 'Servicios, horario y dirección de cada negocio'),
            self::op('q:como', '📅 ¿Cómo reservo?'),
            self::op('q:pago', '💵 ¿Cómo se paga?'),
            self::op('q:cancelar', '❌ Cancelar o cambiar'),
            self::op('q:registrar', '🏢 Registrar mi negocio'),
            self::op('q:que', 'ℹ️ ¿Qué es AgendaFácil?'),
            $this->opcionMenu(),
        ]);
    }

    private function faqRespuesta(string $clave): void
    {
        $volver = [self::op('faq', '❓ Otra pregunta'), self::op('reservar', '📅 Reservar'), $this->opcionMenu()];
        $web = urlSitio();
        switch ($clave) {
            case 'negocios':
                $this->elegirNegocio(0, 'i');
                return;
            case 'como':
                $this->decir("Es muy fácil:\n1️⃣ Tocá *Reservar una cita*\n2️⃣ Elegí el negocio y el servicio\n3️⃣ Elegí el día y la hora libre\n4️⃣ Confirmá y listo ✅\n\n"
                    . 'También podés reservar desde la página' . ($web ? ": $web" : '.'), $volver);
                return;
            case 'pago':
                $this->decir("💵 El pago se hace *directamente en el local*, el día de tu cita (efectivo o QR, según el negocio).\nReservar por aquí no tiene ningún costo.", $volver);
                return;
            case 'cancelar':
                $this->decir("Para cancelar o cambiar tu cita podés:\n• Escribirle al negocio por WhatsApp (el número aparece al reservar)\n"
                    . '• O entrar a *Mis citas* en la página' . ($web ? ": {$web}negocios/citas/mis_citas.php" : '.') . "\n\nTe pedimos avisar con anticipación 🙏", $volver);
                return;
            case 'registrar':
                $this->decir("🏢 ¿Tenés un negocio en Santa Cruz? Registralo gratis y recibí reservas las 24 horas.\n"
                    . ($web ? "👉 {$web}auth/registro.php?tipo=negocio" : 'Entrá a la página y elegí *Registrar mi negocio*.'), $volver);
                return;
            case 'que':
                $this->decir('*' . NOMBRE_SITIO . " SCZ* es una plataforma para reservar citas en barberías, spas, clínicas dentales, manicure y más negocios de "
                    . CIUDAD . ".\nVes los horarios libres en tiempo real y reservás en segundos, sin llamar. 📱", $volver);
                return;
        }
        $this->faqMenu();
    }

    private function infoNegocio(int $id): void
    {
        $n = obtenerNegocio($this->pdo, $id);
        if (!$n) { $this->elegirNegocio(0, 'i'); return; }
        $this->ir('info_negocio');

        $texto = "🏪 *{$n['nombre']}*\n{$n['categoria']} · {$n['zona']}\n📍 {$n['direccion']}\n";
        if ($n['promedio'] !== null) $texto .= "⭐ {$n['promedio']} ({$n['total']} opiniones)\n";
        $texto .= "\n🕐 *Horario*\n";
        $porDia = array_column($n['horarios'], null, 'dia');
        foreach (DIAS as $num => $dia) {
            $texto .= $dia . ': ' . (isset($porDia[$num]) ? $porDia[$num]['abre'] . ' a ' . $porDia[$num]['cierra'] : 'cerrado') . "\n";
        }
        if ($n['servicios']) {
            $texto .= "\n💵 *Servicios y precios*\n";
            foreach ($n['servicios'] as $s) {
                $texto .= "• {$s['nombre']}: Bs " . number_format($s['precio'], 0) . " ({$s['duracion_min']} min)\n";
            }
        }
        if ($n['whatsapp']) $texto .= "\n💬 WhatsApp: +" . normalizarWhatsapp($n['whatsapp']);
        if ($web = urlSitio()) $texto .= "\n🌐 {$web}negocios/ver.php?id={$n['id']}";

        $this->decir($texto, [
            self::op("n:{$n['id']}", '📅 Reservar aquí'),
            self::op('q:negocios', '🏪 Ver otro negocio'),
            $this->opcionMenu(),
        ]);
    }

    // ---------- Texto libre ----------
    private function buscarServicioMencionado(string $t): bool
    {
        $filas = $this->pdo->query("SELECT s.nombre, s.precio, s.duracion_min, n.id AS negocio, n.nombre AS negocio_nombre
                                    FROM servicios s JOIN negocios n ON n.id = s.negocio_id
                                    WHERE n.activo = 1 ORDER BY s.precio")->fetchAll();
        $ops = [];
        foreach ($filas as $f) {
            foreach (preg_split('/[^a-zñ]+/u', self::normalizar($f['nombre'])) as $palabra) {
                // "masaje" coincide con "masajes"; se ignoran palabras cortas como "de", "uñas"
                if (mb_strlen($palabra) >= 5 && str_contains($t, mb_substr($palabra, 0, -1))) {
                    $ops[$f['negocio'] . $f['nombre']] = self::op('n:' . $f['negocio'], $f['negocio_nombre'],
                        $f['nombre'] . ' · Bs ' . number_format($f['precio'], 0) . ' · ' . $f['duracion_min'] . ' min');
                    break;
                }
            }
        }
        if (!$ops) return false;
        $ops = array_slice(array_values($ops), 0, 9);
        $ops[] = $this->opcionMenu();
        $this->ir('elegir_negocio');
        $this->decir('Encontré estos servicios 👇 Tocá uno para reservar:', $ops);
        return true;
    }

    private function interpretarTexto(string $t): void
    {

        $reglas = [
            'reservar'   => '/reserv|cita|turno|agend|quiero un corte|sacar hora/',
            'q:pago'     => '/pago|pagar|qr|efectivo|tarjeta|cobr/',
            'q:cancelar' => '/cancel|cambiar|reprogram|anular/',
            'q:negocios' => '/horari|atienden|abren|cierran|precio|cuesta|cuanto|costo|tarifa|direcc|ubicac|donde|servicio/',
            'q:registrar'=> '/registr|mi negocio|vender|afiliar/',
            'q:como'     => '/como funciona|como reservo|ayuda|como hago/',
            'q:que'      => '/que es|quienes son|agendafacil/',
        ];
        foreach ($reglas as $accion => $patron) {
            if (preg_match($patron, $t)) {
                // ¿Mencionó un servicio? (ej: "cuánto cuesta un masaje") -> negocios que lo ofrecen
                if (in_array($accion, ['reservar', 'q:negocios'], true) && $this->buscarServicioMencionado($t)) return;
                $accion === 'reservar' ? $this->elegirNegocio(0) : $this->faqRespuesta(substr($accion, 2));
                return;
            }
        }
        if ($this->buscarServicioMencionado($t)) return;
        if (preg_match('/\b(gracias|genial|perfecto|ok|listo|dale|bueno)\b/u', $t)) {
            $this->decir('¡De nada! 😊 Si necesitás algo más, acá estoy.', [self::op('reservar', '📅 Reservar una cita'), $this->opcionMenu()]);
            return;
        }
        $this->decir('Perdón, no te entendí 🤔. Elegí una opción:', [
            self::op('reservar', '📅 Reservar una cita'),
            self::op('faq', '❓ Preguntas frecuentes'),
        ]);
    }
}
