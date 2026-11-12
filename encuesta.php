<?php
require __DIR__ . '/config/conexion.php';
asegurarTablaEncuesta($pdo);

$errores = [];
$enviada = !empty($_SESSION['encuesta_enviada']);
$d = ['general' => 0, 'facilidad' => 0, 'diseno' => 0, 'usaria' => '', 'perfil' => '', 'dispositivo' => '', 'gusto' => '', 'mejora' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$enviada) {
    foreach ($d as $k => $v) $d[$k] = is_int($v) ? (int)($_POST[$k] ?? 0) : trim($_POST[$k] ?? '');

    // Trampa para robots: campo oculto que una persona deja vacío
    if (!empty($_POST['sitio_web'])) redirigir('index.php');

    foreach (['general' => '¿qué te pareció?', 'facilidad' => '¿fue fácil reservar?', 'diseno' => 'el diseño'] as $k => $txt) {
        if ($d[$k] < 1 || $d[$k] > 5) $errores[] = "Calificá $txt (1 a 5 estrellas).";
    }
    if (!in_array($d['usaria'], ['si', 'tal_vez', 'no'], true))                         $errores[] = 'Respondé si usarías la página.';
    if (!in_array($d['perfil'], ['cliente', 'negocio', 'estudiante', 'otro'], true))    $errores[] = 'Indicá quién sos.';
    if (!in_array($d['dispositivo'], ['celular', 'computadora', 'tablet'], true))       $errores[] = 'Indicá desde dónde entraste.';

    // Anti-spam: máximo 3 respuestas por hora desde la misma conexión
    $ipHash = hash('sha256', ipCliente() . DB_NAME);
    $st = $pdo->prepare("SELECT COUNT(*) FROM encuestas WHERE ip_hash = ? AND creado_en > NOW() - INTERVAL 1 HOUR");
    $st->execute([$ipHash]);
    if ($st->fetchColumn() >= 3) $errores[] = 'Ya recibimos varias respuestas desde esta conexión. ¡Gracias!';

    if (!$errores) {
        $pdo->prepare("INSERT INTO encuestas (general, facilidad, diseno, usaria, perfil, dispositivo, gusto, mejora, ip_hash)
                       VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$d['general'], $d['facilidad'], $d['diseno'], $d['usaria'], $d['perfil'], $d['dispositivo'],
                       mb_substr($d['gusto'], 0, 500), mb_substr($d['mejora'], 0, 500), $ipHash]);
        $_SESSION['encuesta_enviada'] = true;
        $enviada = true;
    }
}

// Estrellas seleccionables
function preguntaEstrellas($nombre, $pregunta, $valor) {
    echo '<fieldset class="pregunta"><legend>' . e($pregunta) . ' <span class="obligatorio">*</span></legend>';
    echo '<div class="selector-estrellas">';
    for ($i = 5; $i >= 1; $i--) {
        $id = $nombre . $i;
        echo '<input type="radio" name="' . $nombre . '" id="' . $id . '" value="' . $i . '"' . ($valor === $i ? ' checked' : '') . ' required>'
           . '<label for="' . $id . '" title="' . $i . ' de 5">' . icono('star') . '</label>';
    }
    echo '</div></fieldset>';
}

function preguntaOpciones($nombre, $pregunta, array $opciones, $valor) {
    echo '<fieldset class="pregunta"><legend>' . e($pregunta) . ' <span class="obligatorio">*</span></legend><div class="opciones-chip">';
    foreach ($opciones as $clave => [$icono, $texto]) {
        echo '<label class="chip-opcion"><input type="radio" name="' . $nombre . '" value="' . $clave . '"' . ($valor === $clave ? ' checked' : '') . ' required>'
           . '<span>' . icono($icono) . ' ' . e($texto) . '</span></label>';
    }
    echo '</div></fieldset>';
}

$titulo = 'Encuesta: ¿qué te pareció?';
require __DIR__ . '/includes/header.php';
?>

<section class="seccion-form">
    <div class="form-caja form-encuesta">
        <?php if ($enviada): ?>
            <div class="encuesta-gracias">
                <span class="icono-cuadro icono-grande"><?= icono('heart') ?></span>
                <h1>¡Gracias por tu opinión!</h1>
                <p class="form-sub">Tu respuesta nos ayuda a mejorar AgendaFácil para Santa Cruz.</p>
                <div class="hero-botones centrado">
                    <a href="<?= url('index.php') ?>" class="btn btn-primario"><?= icono('house') ?> Volver al inicio</a>
                    <a href="https://wa.me/?text=<?= rawurlencode('Probá AgendaFácil, una página para reservar citas en Santa Cruz y dejá tu opinión: ' . (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . url('encuesta.php')) ?>"
                       target="_blank" rel="noopener" class="btn btn-whatsapp"><?= icono('whatsapp', 'brands') ?> Compartir con un amigo</a>
                </div>
            </div>
        <?php else: ?>
            <span class="pildora"><?= icono('clipboard-list') ?> Encuesta · 1 minuto</span>
            <h1>¿Qué te pareció AgendaFácil?</h1>
            <p class="form-sub">Es un proyecto universitario. Tu opinión es anónima y nos ayuda muchísimo.</p>

            <?php mostrarErrores($errores); ?>

            <form method="POST" class="formulario">
                <?= csrfCampo() ?>
                <input type="text" name="sitio_web" class="campo-trampa" tabindex="-1" autocomplete="off" aria-hidden="true">

                <?php
                preguntaEstrellas('general', '¿Qué te pareció la página en general?', $d['general']);
                preguntaEstrellas('facilidad', '¿Qué tan fácil fue buscar un negocio y reservar?', $d['facilidad']);
                preguntaEstrellas('diseno', '¿Te gustó el diseño?', $d['diseno']);
                preguntaOpciones('usaria', '¿Usarías esta página para reservar tus citas?', [
                    'si' => ['face-smile', 'Sí'], 'tal_vez' => ['face-meh', 'Tal vez'], 'no' => ['face-frown', 'No'],
                ], $d['usaria']);
                preguntaOpciones('perfil', '¿Quién sos?', [
                    'cliente' => ['user', 'Cliente'], 'negocio' => ['store', 'Tengo un negocio'],
                    'estudiante' => ['graduation-cap', 'Estudiante'], 'otro' => ['circle-question', 'Otro'],
                ], $d['perfil']);
                preguntaOpciones('dispositivo', '¿Desde dónde entraste?', [
                    'celular' => ['mobile-screen', 'Celular'], 'computadora' => ['laptop', 'Computadora'], 'tablet' => ['tablet-screen-button', 'Tablet'],
                ], $d['dispositivo']);
                ?>

                <label>¿Qué es lo que más te gustó? (opcional)
                    <textarea name="gusto" rows="2" maxlength="500"><?= e($d['gusto']) ?></textarea>
                </label>
                <label>¿Qué mejorarías o qué le falta? (opcional)
                    <textarea name="mejora" rows="2" maxlength="500"><?= e($d['mejora']) ?></textarea>
                </label>

                <button class="btn btn-primario btn-bloque btn-grande"><?= icono('paper-plane') ?> Enviar mi opinión</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
