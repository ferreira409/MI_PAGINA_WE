<?php
require __DIR__ . '/_panel.php';

$idNegocio = negocioSeleccionado();
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $idNegocio) {
    $accion = $_POST['accion'] ?? '';
    $r = ['ok' => false, 'errores' => ['Acción no válida.']];

    if ($accion === 'datos') {
        $r = guardarDatosNegocio($pdo, $idNegocio, $_POST);
        $msg = 'Datos del negocio guardados.';
    } elseif ($accion === 'foto') {
        $r = subirFotoNegocio($pdo, $idNegocio, $_FILES['foto'] ?? []);
        $msg = 'Foto actualizada.';
    } elseif ($accion === 'quitar_foto') {
        $pdo->prepare("UPDATE negocios SET foto = NULL WHERE id = ?")->execute([$idNegocio]);
        $r = ['ok' => true];
        $msg = 'Se quitó la foto. Se mostrará la imagen de la categoría.';
    } elseif ($accion === 'horario') {
        $r = guardarHorario($pdo, $idNegocio, $_POST['dias'] ?? []);
        $msg = 'Horario guardado.';
    } elseif ($accion === 'agregar_libre') {
        $f = $_POST['fecha'] ?? '';
        if (fechaValida($f) && $f >= date('Y-m-d')) {
            $pdo->prepare("INSERT IGNORE INTO dias_libres (negocio_id, fecha, motivo) VALUES (?,?,?)")
                ->execute([$idNegocio, $f, mb_substr(trim($_POST['motivo'] ?? ''), 0, 120)]);
            $r = ['ok' => true];
            $msg = 'Día libre agregado.';
        } else {
            $r = ['ok' => false, 'errores' => ['Elegí una fecha válida (hoy o después).']];
        }
    } elseif ($accion === 'quitar_libre') {
        $pdo->prepare("DELETE FROM dias_libres WHERE id = ? AND negocio_id = ?")->execute([(int)$_POST['id'], $idNegocio]);
        $r = ['ok' => true];
        $msg = 'Día libre eliminado.';
    }

    if ($r['ok']) {
        flash($msg);
        redirigir('admin/mi_negocio.php?negocio=' . $idNegocio);
    }
    $errores = $r['errores'];
}

$n = $idNegocio ? obtenerNegocio($pdo, $idNegocio, false) : null;
$horario = $n ? array_column($n['horarios'], null, 'dia') : [];
$libres = [];
if ($n) {
    $st = $pdo->prepare("SELECT * FROM dias_libres WHERE negocio_id = ? AND fecha >= CURDATE() ORDER BY fecha");
    $st->execute([$idNegocio]);
    $libres = $st->fetchAll();
}

$titulo = 'Mi negocio';
require __DIR__ . '/../includes/header.php';
?>

<section class="seccion panel">
    <div class="contenedor panel-grid">
        <?php menuPanel('mi_negocio.php', $esAdmin); ?>

        <div class="panel-contenido">
            <div class="encabezado-pagina">
                <div>
                    <h1><?= $esAdmin ? 'Editar negocio' : 'Mi negocio' ?></h1>
                    <p class="form-sub">Datos, foto y horario que ven tus clientes</p>
                </div>
                <div class="encabezado-acciones">
                    <?php selectorNegocio($idNegocio); ?>
                    <?php if ($n): ?><a href="<?= url('negocios/ver.php?id=' . $n['id']) ?>" class="btn btn-borde" target="_blank"><?= icono('eye', 'regular') ?> Ver página pública</a><?php endif; ?>
                </div>
            </div>

            <?php mostrarFlash(); mostrarErrores($errores); ?>

            <?php if (!$n): ?>
                <div class="alerta alerta-error">No hay negocios para administrar.</div>
            <?php else: ?>

            <div class="grid-negocio-admin">
                <!-- Datos -->
                <form method="POST" class="tarjeta formulario caja-form">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="accion" value="datos">
                    <input type="hidden" name="negocio" value="<?= $n['id'] ?>">
                    <h2><?= icono('pen-to-square', 'regular') ?> Datos del negocio</h2>
                    <label>Nombre
                        <input type="text" name="nombre" value="<?= e($n['nombre']) ?>" required maxlength="120">
                    </label>
                    <div class="fila-2">
                        <label>Categoría
                            <select name="categoria" required><?= opcionesSelect(CATEGORIAS, $n['categoria']) ?></select>
                        </label>
                        <label>Zona
                            <select name="zona" required><?= opcionesSelect(ZONAS, $n['zona']) ?></select>
                        </label>
                    </div>
                    <label>Dirección
                        <input type="text" name="direccion" value="<?= e($n['direccion']) ?>" maxlength="200">
                    </label>
                    <div class="fila-2">
                        <label>Teléfono
                            <input type="tel" name="telefono" value="<?= e($n['telefono']) ?>">
                        </label>
                        <label>WhatsApp
                            <input type="tel" name="whatsapp" value="<?= e($n['whatsapp']) ?>" placeholder="70000000">
                        </label>
                    </div>
                    <label>Descripción
                        <textarea name="descripcion" rows="4" maxlength="1000"><?= e($n['descripcion']) ?></textarea>
                    </label>
                    <button class="btn btn-primario"><?= icono('floppy-disk', 'regular') ?> Guardar datos</button>
                </form>

                <!-- Foto -->
                <div class="tarjeta caja-form">
                    <h2><?= icono('image', 'regular') ?> Foto de portada</h2>
                    <div class="foto-vista" style="--foto:url('<?= e(imgNegocio($n)) ?>')"></div>
                    <form method="POST" enctype="multipart/form-data" class="formulario">
                        <?= csrfCampo() ?>
                        <input type="hidden" name="accion" value="foto">
                        <input type="hidden" name="negocio" value="<?= $n['id'] ?>">
                        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" required>
                        <small class="ayuda">JPG, PNG o WEBP. Máximo <?= MAX_FOTO_MB ?> MB.</small>
                        <button class="btn btn-primario"><?= icono('upload') ?> Subir foto</button>
                    </form>
                    <?php if ($n['foto']): ?>
                        <form method="POST">
                            <?= csrfCampo() ?>
                            <input type="hidden" name="accion" value="quitar_foto">
                            <input type="hidden" name="negocio" value="<?= $n['id'] ?>">
                            <button class="mini mini-peligro"><?= icono('trash-can') ?> Quitar foto</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Horario -->
            <form method="POST" class="tarjeta caja-form">
                <?= csrfCampo() ?>
                <input type="hidden" name="accion" value="horario">
                <input type="hidden" name="negocio" value="<?= $n['id'] ?>">
                <h2><?= icono('clock', 'regular') ?> Horario de atención</h2>
                <p class="form-sub">Los clientes solo podrán reservar dentro de este horario.</p>
                <div class="tabla-horario">
                    <?php foreach (DIAS as $num => $nombre): $h = $horario[$num] ?? null; ?>
                        <div class="fila-horario">
                            <label class="interruptor">
                                <input type="checkbox" name="dias[<?= $num ?>][abierto]" value="1" <?= $h ? 'checked' : '' ?>>
                                <span class="interruptor-pista"></span>
                                <strong><?= $nombre ?></strong>
                            </label>
                            <input type="time" name="dias[<?= $num ?>][abre]" value="<?= e($h['abre'] ?? '09:00') ?>" step="900">
                            <span>a</span>
                            <input type="time" name="dias[<?= $num ?>][cierra]" value="<?= e($h['cierra'] ?? '18:00') ?>" step="900">
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-primario"><?= icono('floppy-disk', 'regular') ?> Guardar horario</button>
            </form>

            <!-- Días libres -->
            <div class="tarjeta caja-form">
                <h2><?= icono('calendar-xmark', 'regular') ?> Días libres y feriados</h2>
                <p class="form-sub">En estos días no se podrá reservar (ej: 24 de septiembre, carnaval, vacaciones).</p>
                <form method="POST" class="form-inline">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="accion" value="agregar_libre">
                    <input type="hidden" name="negocio" value="<?= $n['id'] ?>">
                    <input type="date" name="fecha" min="<?= date('Y-m-d') ?>" required>
                    <input type="text" name="motivo" placeholder="Motivo (opcional)" maxlength="120">
                    <button class="btn btn-primario"><?= icono('plus') ?> Agregar</button>
                </form>
                <?php if (!$libres): ?>
                    <p class="vacio">No hay días libres programados.</p>
                <?php else: ?>
                    <ul class="lista-libres">
                        <?php foreach ($libres as $l): ?>
                            <li>
                                <span><?= icono('calendar', 'regular') ?> <?= DIAS[(int)date('N', strtotime($l['fecha']))] ?> <?= date('d/m/Y', strtotime($l['fecha'])) ?>
                                    <?php if ($l['motivo']): ?><small>· <?= e($l['motivo']) ?></small><?php endif; ?></span>
                                <form method="POST">
                                    <?= csrfCampo() ?>
                                    <input type="hidden" name="accion" value="quitar_libre">
                                    <input type="hidden" name="negocio" value="<?= $n['id'] ?>">
                                    <button name="id" value="<?= $l['id'] ?>" class="mini mini-peligro"><?= icono('trash-can') ?></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
