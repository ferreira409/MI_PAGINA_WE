<?php
require __DIR__ . '/_panel.php';
asegurarTablaEncuesta($pdo);

if (!$esAdmin) {
    flash('Solo el administrador puede ver la encuesta.', 'error');
    redirigir('admin/index.php');
}

// ---------- Descargar respuestas en CSV (Excel) ----------
if (($_GET['exportar'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="encuesta_agendafacil_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");                       // para que Excel lea bien los acentos
    fputcsv($out, ['Fecha', 'General', 'Facilidad', 'Diseño', '¿Lo usaría?', 'Perfil', 'Dispositivo', 'Lo que más gustó', 'Qué mejoraría'], ';');
    foreach ($pdo->query("SELECT * FROM encuestas ORDER BY creado_en DESC") as $r) {
        fputcsv($out, [$r['creado_en'], $r['general'], $r['facilidad'], $r['diseno'], $r['usaria'], $r['perfil'], $r['dispositivo'], $r['gusto'], $r['mejora']], ';');
    }
    exit;
}

$res = $pdo->query("SELECT COUNT(*) total, ROUND(AVG(general),1) general, ROUND(AVG(facilidad),1) facilidad,
                           ROUND(AVG(diseno),1) diseno, ROUND(100 * AVG(usaria = 'si')) pct_si
                    FROM encuestas")->fetch();

function conteo(PDO $pdo, $campo, array $claves) {
    $c = array_fill_keys($claves, 0);
    foreach ($pdo->query("SELECT $campo k, COUNT(*) t FROM encuestas GROUP BY $campo") as $f) $c[$f['k']] = (int)$f['t'];
    return $c;
}
$distGeneral = conteo($pdo, 'general', [1, 2, 3, 4, 5]);
$usaria      = conteo($pdo, 'usaria', ['si', 'tal_vez', 'no']);
$perfil      = conteo($pdo, 'perfil', ['cliente', 'negocio', 'estudiante', 'otro']);
$dispositivo = conteo($pdo, 'dispositivo', ['celular', 'computadora', 'tablet']);
$comentarios = $pdo->query("SELECT * FROM encuestas WHERE gusto <> '' OR mejora <> '' ORDER BY creado_en DESC LIMIT 50")->fetchAll();

$datos = [
    'general'     => ['etiquetas' => ['1 ★', '2 ★', '3 ★', '4 ★', '5 ★'], 'valores' => array_values($distGeneral)],
    'usaria'      => ['etiquetas' => ['Sí', 'Tal vez', 'No'], 'valores' => array_values($usaria)],
    'perfil'      => ['etiquetas' => ['Cliente', 'Negocio', 'Estudiante', 'Otro'], 'valores' => array_values($perfil)],
    'dispositivo' => ['etiquetas' => ['Celular', 'Computadora', 'Tablet'], 'valores' => array_values($dispositivo)],
];
$scriptsExtra = '<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>'
              . '<script>window.DATOS_ENCUESTA = ' . json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>'
              . '<script src="' . url('js/graficos.js') . '"></script>';

$esquema = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
$linkEncuesta = $esquema . '://' . $_SERVER['HTTP_HOST'] . url('encuesta.php');

$titulo = 'Resultados de la encuesta';
require __DIR__ . '/../includes/header.php';
?>

<section class="seccion panel">
    <div class="contenedor panel-grid">
        <?php menuPanel('encuesta.php', $esAdmin); ?>

        <div class="panel-contenido">
            <div class="encabezado-pagina">
                <div>
                    <h1>Encuesta de satisfacción</h1>
                    <p class="form-sub"><?= (int)$res['total'] ?> respuestas recibidas</p>
                </div>
                <div class="encabezado-acciones">
                    <a href="?exportar=csv" class="btn btn-borde"><?= icono('file-excel', 'regular') ?> Descargar Excel</a>
                </div>
            </div>

            <div class="tarjeta caja-form compartir-encuesta">
                <h2><?= icono('share-nodes') ?> Link para compartir la encuesta</h2>
                <div class="copiar-link">
                    <input type="text" value="<?= e($linkEncuesta) ?>" id="linkEncuesta" readonly>
                    <button class="btn btn-primario btn-chico" data-copiar="linkEncuesta"><?= icono('copy', 'regular') ?> Copiar</button>
                    <a class="btn btn-whatsapp btn-chico" target="_blank" rel="noopener"
                       href="https://wa.me/?text=<?= rawurlencode('Hola! Estoy haciendo una página para reservar citas en Santa Cruz. ¿Me ayudás probándola y respondiendo una encuesta de 1 minuto? ' . $linkEncuesta) ?>">
                        <?= icono('whatsapp', 'brands') ?> WhatsApp</a>
                </div>
                <?php if (strpos($linkEncuesta, 'localhost') !== false || strpos($linkEncuesta, '127.0.0.1') !== false): ?>
                    <small class="ayuda"><?= icono('triangle-exclamation') ?> Este link es de tu computadora (localhost) y solo funciona en ella. Cuando subas la página al hosting, aquí aparecerá el link público.</small>
                <?php endif; ?>
            </div>

            <?php if (!$res['total']): ?>
                <div class="tarjeta vacio-caja">
                    <span class="icono-cuadro icono-grande"><?= icono('clipboard-list') ?></span>
                    <h3>Todavía no hay respuestas</h3>
                    <p>Compartí el link de arriba para empezar a recibir opiniones.</p>
                </div>
            <?php else: ?>
                <div class="stats">
                    <div class="stat"><span><?= icono('star') ?> General</span><strong><?= e($res['general']) ?> / 5</strong></div>
                    <div class="stat"><span><?= icono('hand-pointer') ?> Facilidad</span><strong><?= e($res['facilidad']) ?> / 5</strong></div>
                    <div class="stat"><span><?= icono('palette') ?> Diseño</span><strong><?= e($res['diseno']) ?> / 5</strong></div>
                    <div class="stat"><span><?= icono('thumbs-up') ?> La usarían</span><strong><?= (int)$res['pct_si'] ?>%</strong></div>
                </div>

                <div class="graficos graficos-3">
                    <div class="tarjeta grafico grafico-ancho"><h3>Calificación general</h3><div class="grafico-lienzo"><canvas id="encGeneral"></canvas></div></div>
                    <div class="tarjeta grafico"><h3>¿La usarían?</h3><div class="grafico-lienzo"><canvas id="encUsaria"></canvas></div></div>
                    <div class="tarjeta grafico"><h3>¿Desde dónde entraron?</h3><div class="grafico-lienzo"><canvas id="encDispositivo"></canvas></div></div>
                    <div class="tarjeta grafico grafico-ancho"><h3>¿Quiénes respondieron?</h3><div class="grafico-lienzo"><canvas id="encPerfil"></canvas></div></div>
                </div>

                <div class="tarjeta caja-form">
                    <h2><?= icono('comments', 'regular') ?> Comentarios</h2>
                    <?php if (!$comentarios): ?><p class="vacio">Nadie dejó comentarios todavía.</p><?php endif; ?>
                    <?php foreach ($comentarios as $c): ?>
                        <div class="resena">
                            <div class="resena-top">
                                <?= estrellasHtml($c['general']) ?>
                                <small><?= e(ucfirst($c['perfil'])) ?> · <?= e($c['dispositivo']) ?> · <?= date('d/m/Y H:i', strtotime($c['creado_en'])) ?></small>
                            </div>
                            <?php if ($c['gusto']): ?><p><b class="texto-verde"><?= icono('thumbs-up') ?> Le gustó:</b> <?= e($c['gusto']) ?></p><?php endif; ?>
                            <?php if ($c['mejora']): ?><p><b class="texto-naranja"><?= icono('lightbulb', 'regular') ?> Mejoraría:</b> <?= e($c['mejora']) ?></p><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
