<?php
require __DIR__ . '/_panel.php';

if (!$esAdmin) {
    flash('Solo el administrador puede ver esa página.', 'error');
    redirigir('admin/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle'])) {
    $pdo->prepare("UPDATE negocios SET activo = 1 - activo WHERE id = ?")->execute([(int)$_POST['toggle']]);
    flash('Estado del negocio actualizado.');
    redirigir('admin/negocios.php');
}

$negocios = listarNegocios($pdo, [], false);
$duenios = [];
foreach ($pdo->query("SELECT n.id, u.nombre, u.email FROM negocios n LEFT JOIN usuarios u ON u.id = n.usuario_id") as $f) $duenios[$f['id']] = $f;
$citasPorNegocio = [];
foreach ($pdo->query("SELECT negocio_id, COUNT(*) t FROM citas GROUP BY negocio_id") as $f) $citasPorNegocio[$f['negocio_id']] = $f['t'];

$titulo = 'Negocios';
require __DIR__ . '/../includes/header.php';
?>

<section class="seccion panel">
    <div class="contenedor panel-grid">
        <?php menuPanel('negocios.php', $esAdmin); ?>

        <div class="panel-contenido">
            <div class="encabezado-pagina">
                <div>
                    <h1>Todos los negocios</h1>
                    <p class="form-sub"><?= count($negocios) ?> negocios registrados</p>
                </div>
            </div>

            <?php mostrarFlash(); ?>

            <div class="tabla-caja">
                <table class="tabla">
                    <thead><tr><th>Negocio</th><th>Zona</th><th>Dueño</th><th>Calificación</th><th>Citas</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($negocios as $n): $d = $duenios[$n['id']] ?? null; ?>
                        <tr>
                            <td data-label="Negocio"><span class="icono-celda"><?= iconoCategoria($n['categoria']) ?></span> <?= e($n['nombre']) ?><br><small><?= e($n['categoria']) ?></small></td>
                            <td data-label="Zona"><?= e($n['zona']) ?></td>
                            <td data-label="Dueño"><?= e($d['nombre'] ?? '—') ?><br><small><?= e($d['email'] ?? '') ?></small></td>
                            <td data-label="Calificación"><?= estrellasHtml($n['promedio'], $n['total_calificaciones']) ?></td>
                            <td data-label="Citas"><?= (int)($citasPorNegocio[$n['id']] ?? 0) ?></td>
                            <td data-label="Estado"><?= $n['activo'] ? '<span class="estado estado-confirmada">Activo</span>' : '<span class="estado estado-cancelada">Inactivo</span>' ?></td>
                            <td>
                                <div class="acciones">
                                    <a href="<?= url('admin/mi_negocio.php?negocio=' . $n['id']) ?>" class="mini mini-ok"><?= icono('pen-to-square', 'regular') ?> Editar</a>
                                    <form method="POST">
                                        <?= csrfCampo() ?>
                                        <button name="toggle" value="<?= $n['id'] ?>" class="mini <?= $n['activo'] ? 'mini-peligro' : 'mini-ok' ?>">
                                            <?= $n['activo'] ? icono('eye-slash', 'regular') . ' Desactivar' : icono('eye', 'regular') . ' Activar' ?>
                                        </button>
                                    </form>
                                </div>
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
