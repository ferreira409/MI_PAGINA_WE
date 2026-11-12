<?php
require __DIR__ . '/_panel.php';

$idNegocio = negocioSeleccionado();
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $idNegocio) {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'eliminar') {
        $idServicio = (int)$_POST['id'];
        // No se elimina si tiene citas próximas activas
        $st = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE servicio_id = ? AND fecha >= CURDATE() AND estado IN ('pendiente','confirmada')");
        $st->execute([$idServicio]);
        if ($st->fetchColumn() > 0) {
            flash('No se puede eliminar: este servicio tiene citas próximas. Cancelalas o esperá a que pasen.', 'error');
        } else {
            $pdo->prepare("DELETE FROM servicios WHERE id = ? AND negocio_id = ?")->execute([$idServicio, $idNegocio]);
            flash('Servicio eliminado.');
        }
        redirigir('admin/servicios.php?negocio=' . $idNegocio);
    }

    // Agregar o editar
    $nombre   = trim($_POST['nombre'] ?? '');
    $duracion = (int)($_POST['duracion'] ?? 0);
    $precio   = (float)($_POST['precio'] ?? -1);
    if ($nombre === '' || mb_strlen($nombre) > 100) $errores[] = 'Escribí el nombre del servicio.';
    if ($duracion < 10 || $duracion > 480)          $errores[] = 'La duración debe estar entre 10 y 480 minutos.';
    if ($precio < 0)                                 $errores[] = 'El precio no puede ser negativo.';

    if (!$errores) {
        if ($accion === 'editar') {
            $pdo->prepare("UPDATE servicios SET nombre = ?, duracion_min = ?, precio = ? WHERE id = ? AND negocio_id = ?")
                ->execute([$nombre, $duracion, $precio, (int)$_POST['id'], $idNegocio]);
            flash('Servicio actualizado.');
        } else {
            $pdo->prepare("INSERT INTO servicios (negocio_id, nombre, duracion_min, precio) VALUES (?,?,?,?)")
                ->execute([$idNegocio, $nombre, $duracion, $precio]);
            flash('Servicio agregado.');
        }
        redirigir('admin/servicios.php?negocio=' . $idNegocio);
    }
}

$servicios = [];
if ($idNegocio) {
    $st = $pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM citas c WHERE c.servicio_id = s.id) AS total_citas
                         FROM servicios s WHERE negocio_id = ? ORDER BY nombre");
    $st->execute([$idNegocio]);
    $servicios = $st->fetchAll();
}

$titulo = 'Servicios';
require __DIR__ . '/../includes/header.php';
?>

<section class="seccion panel">
    <div class="contenedor panel-grid">
        <?php menuPanel('servicios.php', $esAdmin); ?>

        <div class="panel-contenido">
            <div class="encabezado-pagina">
                <div>
                    <h1>Servicios</h1>
                    <p class="form-sub">Lo que tus clientes pueden reservar. La duración define qué horarios se bloquean.</p>
                </div>
                <?php selectorNegocio($idNegocio); ?>
            </div>

            <?php mostrarFlash(); mostrarErrores($errores); ?>

            <?php if (!$idNegocio): ?>
                <div class="alerta alerta-error">No hay negocios para administrar.</div>
            <?php else: ?>

            <form method="POST" class="tarjeta form-inline">
                <?= csrfCampo() ?>
                <input type="hidden" name="accion" value="agregar">
                <input type="hidden" name="negocio" value="<?= $idNegocio ?>">
                <input type="text" name="nombre" placeholder="Nombre del servicio" required maxlength="100">
                <input type="number" name="duracion" placeholder="Minutos" value="30" min="10" max="480" step="5" title="Duración en minutos">
                <input type="number" name="precio" placeholder="Precio (Bs)" min="0" step="0.5" required title="Precio en Bs">
                <button class="btn btn-primario"><?= icono('plus') ?> Agregar</button>
            </form>

            <div class="tabla-caja">
                <table class="tabla">
                    <thead><tr><th>Servicio</th><th>Duración (min)</th><th>Precio (Bs)</th><th>Citas</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$servicios): ?>
                        <tr><td colspan="5" class="vacio">Sin servicios todavía.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($servicios as $s): $f = 'srv' . $s['id']; ?>
                        <tr>
                            <td data-label="Servicio"><input form="<?= $f ?>" type="text" name="nombre" value="<?= e($s['nombre']) ?>" class="input-tabla" required></td>
                            <td data-label="Duración"><input form="<?= $f ?>" type="number" name="duracion" value="<?= (int)$s['duracion_min'] ?>" min="10" max="480" step="5" class="input-tabla input-num"></td>
                            <td data-label="Precio"><input form="<?= $f ?>" type="number" name="precio" value="<?= e($s['precio']) ?>" min="0" step="0.5" class="input-tabla input-num"></td>
                            <td data-label="Citas"><?= (int)$s['total_citas'] ?></td>
                            <td data-label="">
                                <div class="acciones">
                                    <form method="POST" id="<?= $f ?>">
                                        <?= csrfCampo() ?>
                                        <input type="hidden" name="accion" value="editar">
                                        <input type="hidden" name="negocio" value="<?= $idNegocio ?>">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <button class="mini mini-ok"><?= icono('floppy-disk', 'regular') ?> Guardar</button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('¿Eliminar este servicio?')">
                                        <?= csrfCampo() ?>
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="negocio" value="<?= $idNegocio ?>">
                                        <button name="id" value="<?= $s['id'] ?>" class="mini mini-peligro"><?= icono('trash-can') ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
