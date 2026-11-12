<?php
require __DIR__ . '/_panel.php';

// ---------- Cambiar estado de una cita ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $r = cambiarEstadoCita($pdo, (int)($_POST['cita'] ?? 0), $_POST['estado'] ?? '', $u);
    flash($r['ok'] ? 'Cita marcada como ' . $_POST['estado'] . '.' : $r['error'], $r['ok'] ? 'exito' : 'error');
    redirigir('admin/index.php?' . http_build_query(['estado' => $_GET['estado'] ?? '', 'fecha' => $_GET['fecha'] ?? '']));
}

$filtroEstado = $_GET['estado'] ?? '';
$filtroFecha  = $_GET['fecha'] ?? '';
$citas = listarCitas($pdo, ['negocios' => $idsNegocios, 'estado' => $filtroEstado, 'fecha' => $filtroFecha]);

// ---------- Estadísticas ----------
$in = implode(',', $idsNegocios ?: [0]);
$stats = $pdo->query("SELECT
        SUM(fecha = CURDATE() AND estado IN ('pendiente','confirmada')) hoy,
        SUM(estado = 'pendiente') pendientes,
        SUM(estado = 'confirmada' AND fecha >= CURDATE()) confirmadas,
        COALESCE(SUM(CASE WHEN c.estado = 'completada' AND c.fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN s.precio END), 0) ingresos_mes
    FROM citas c JOIN servicios s ON s.id = c.servicio_id WHERE c.negocio_id IN ($in)")->fetch();

// Citas por día (últimos 7 días + próximos 7)
$porDia = [];
for ($i = -7; $i <= 7; $i++) $porDia[date('Y-m-d', strtotime("$i days"))] = 0;
foreach ($pdo->query("SELECT fecha, COUNT(*) total FROM citas
                      WHERE negocio_id IN ($in) AND estado <> 'cancelada'
                        AND fecha BETWEEN CURDATE() - INTERVAL 7 DAY AND CURDATE() + INTERVAL 7 DAY
                      GROUP BY fecha") as $f) {
    $porDia[$f['fecha']] = (int)$f['total'];
}

// Citas por estado
$porEstado = array_fill_keys(ESTADOS, 0);
foreach ($pdo->query("SELECT estado, COUNT(*) total FROM citas WHERE negocio_id IN ($in) GROUP BY estado") as $f) {
    $porEstado[$f['estado']] = (int)$f['total'];
}

// Admin: citas por negocio
$porNegocio = $esAdmin ? $pdo->query("SELECT n.nombre, COUNT(c.id) total FROM negocios n
                                      LEFT JOIN citas c ON c.negocio_id = n.id AND c.estado <> 'cancelada'
                                      GROUP BY n.id ORDER BY total DESC LIMIT 8")->fetchAll() : [];

$datosGraficos = [
    'dias'      => ['etiquetas' => array_map(fn($f) => date('d/m', strtotime($f)), array_keys($porDia)), 'valores' => array_values($porDia),
                    'hoy' => array_search(date('Y-m-d'), array_keys($porDia))],
    'estados'   => ['etiquetas' => array_map('ucfirst', array_keys($porEstado)), 'valores' => array_values($porEstado)],
    'negocios'  => ['etiquetas' => array_column($porNegocio, 'nombre'), 'valores' => array_map('intval', array_column($porNegocio, 'total'))],
];

$scriptsExtra = '<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>'
              . '<script>window.DATOS_GRAFICOS = ' . json_encode($datosGraficos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>'
              . '<script src="' . url('js/graficos.js') . '"></script>';

$titulo = 'Panel';
require __DIR__ . '/../includes/header.php';
?>

<section class="seccion panel">
    <div class="contenedor panel-grid">
        <?php menuPanel('index.php', $esAdmin); ?>

        <div class="panel-contenido">
            <?php bannerRol($u, 'Resumen y citas',
                $esAdmin ? 'Administrás todos los negocios de la plataforma'
                         : 'Tu negocio: ' . implode(', ', array_column($misNegocios, 'nombre'))); ?>

            <?php mostrarFlash(); ?>
            <?php if (!$idsNegocios): ?>
                <div class="alerta alerta-error">Tu cuenta no tiene un negocio asociado.</div>
            <?php endif; ?>

            <div class="stats">
                <div class="stat"><span><?= icono('calendar-day') ?> Hoy</span><strong><?= (int)$stats['hoy'] ?></strong></div>
                <div class="stat"><span><?= icono('hourglass-half') ?> Pendientes</span><strong><?= (int)$stats['pendientes'] ?></strong></div>
                <div class="stat"><span><?= icono('circle-check') ?> Confirmadas</span><strong><?= (int)$stats['confirmadas'] ?></strong></div>
                <div class="stat"><span><?= icono('money-bill-wave') ?> Ingresos del mes</span><strong>Bs <?= number_format($stats['ingresos_mes'], 0) ?></strong></div>
            </div>

            <div class="graficos <?= $esAdmin ? 'graficos-3' : '' ?>">
                <div class="tarjeta grafico grafico-ancho"><h3>Citas por día</h3><div class="grafico-lienzo"><canvas id="graficoDias"></canvas></div></div>
                <div class="tarjeta grafico"><h3>Por estado</h3><div class="grafico-lienzo"><canvas id="graficoEstados"></canvas></div></div>
                <?php if ($esAdmin): ?>
                    <div class="tarjeta grafico"><h3>Por negocio</h3><div class="grafico-lienzo"><canvas id="graficoNegocios"></canvas></div></div>
                <?php endif; ?>
            </div>

            <form method="GET" class="filtros">
                <select name="estado">
                    <option value="">Todos los estados</option>
                    <?php foreach (ESTADOS as $es): ?>
                        <option value="<?= $es ?>" <?= $filtroEstado === $es ? 'selected' : '' ?>><?= ucfirst($es) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="fecha" value="<?= e($filtroFecha) ?>">
                <button class="btn btn-primario"><?= icono('filter') ?> Filtrar</button>
                <a href="<?= url('admin/index.php') ?>" class="btn btn-borde">Limpiar</a>
            </form>

            <div class="tabla-caja">
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Fecha</th><th>Hora</th><th>Cliente</th>
                            <?php if ($esAdmin): ?><th>Negocio</th><?php endif; ?>
                            <th>Servicio</th><th>Estado</th><th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$citas): ?>
                        <tr><td colspan="7" class="vacio">No hay citas con esos filtros.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($citas as $c):
                        $wa = enlaceWhatsapp($c['cliente_telefono'], mensajeRecordatorio($c)); ?>
                        <tr>
                            <td data-label="Fecha"><?= date('d/m/Y', strtotime($c['fecha'])) ?></td>
                            <td data-label="Hora"><?= $c['hora'] ?> – <?= $c['hora_fin'] ?></td>
                            <td data-label="Cliente">
                                <?= e($c['cliente']) ?><br>
                                <small><?= e($c['cliente_telefono']) ?></small>
                                <?php if ($c['notas']): ?><br><small class="nota"><?= icono('note-sticky', 'regular') ?> <?= e($c['notas']) ?></small><?php endif; ?>
                            </td>
                            <?php if ($esAdmin): ?><td data-label="Negocio"><?= e($c['negocio']) ?></td><?php endif; ?>
                            <td data-label="Servicio"><?= e($c['servicio']) ?></td>
                            <td data-label="Estado"><?= badgeEstado($c['estado']) ?></td>
                            <td data-label="Acciones">
                                <form method="POST" class="acciones">
                                    <?= csrfCampo() ?>
                                    <input type="hidden" name="cita" value="<?= $c['id'] ?>">
                                    <?php if ($c['estado'] === 'pendiente'): ?>
                                        <button name="estado" value="confirmada" class="mini mini-ok"><?= icono('check') ?> Confirmar</button>
                                    <?php endif; ?>
                                    <?php if ($c['estado'] === 'confirmada'): ?>
                                        <button name="estado" value="completada" class="mini mini-ok"><?= icono('flag-checkered') ?> Completar</button>
                                    <?php endif; ?>
                                    <?php if (in_array($c['estado'], ['pendiente', 'confirmada'])): ?>
                                        <?php if ($wa && $c['fecha'] >= date('Y-m-d')): ?>
                                            <a href="<?= e($wa) ?>" target="_blank" rel="noopener" class="mini mini-whatsapp" title="Enviar recordatorio por WhatsApp"><?= icono('whatsapp', 'brands') ?> Recordar</a>
                                        <?php endif; ?>
                                        <button name="estado" value="cancelada" class="mini mini-peligro" onclick="return confirm('¿Cancelar esta cita?')"><?= icono('xmark') ?> Cancelar</button>
                                    <?php endif; ?>
                                    <?php if ($c['calificacion']): ?><?= estrellasHtml($c['calificacion']) ?><?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
