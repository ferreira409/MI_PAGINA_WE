<?php
require __DIR__ . '/_panel.php';

if (!$esAdmin) {
    flash('Solo el administrador puede ver esa página.', 'error');
    redirigir('admin/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id === (int)$u['id']) {
        flash('No podés modificar tu propia cuenta desde aquí.', 'error');
    } elseif (($_POST['accion'] ?? '') === 'toggle') {
        $pdo->prepare("UPDATE usuarios SET activo = 1 - activo WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM api_tokens WHERE usuario_id = ?")->execute([$id]);
        flash('Estado del usuario actualizado.');
    } elseif (($_POST['accion'] ?? '') === 'rol' && in_array($_POST['rol'] ?? '', ['cliente', 'negocio', 'admin'], true)) {
        $pdo->prepare("UPDATE usuarios SET rol = ? WHERE id = ?")->execute([$_POST['rol'], $id]);
        flash('Rol actualizado.');
    }
    redirigir('admin/usuarios.php?' . http_build_query(['rol' => $_GET['rol'] ?? '', 'q' => $_GET['q'] ?? '']));
}

$filtroRol = in_array($_GET['rol'] ?? '', ['cliente', 'negocio', 'admin'], true) ? $_GET['rol'] : '';
$q = trim($_GET['q'] ?? '');

$sql = "SELECT u.*, (SELECT COUNT(*) FROM citas c WHERE c.usuario_id = u.id) AS total_citas FROM usuarios u WHERE 1 = 1";
$p = [];
if ($filtroRol) { $sql .= " AND u.rol = ?"; $p[] = $filtroRol; }
if ($q !== '')  { $sql .= " AND (u.nombre LIKE ? OR u.email LIKE ?)"; $p[] = "%$q%"; $p[] = "%$q%"; }
$sql .= " ORDER BY u.creado_en DESC";
$st = $pdo->prepare($sql);
$st->execute($p);
$usuarios = $st->fetchAll();

$conteo = array_fill_keys(['cliente', 'negocio', 'admin'], 0);
foreach ($pdo->query("SELECT rol, COUNT(*) t FROM usuarios GROUP BY rol") as $f) $conteo[$f['rol']] = (int)$f['t'];

$titulo = 'Usuarios';
require __DIR__ . '/../includes/header.php';
?>

<section class="seccion panel">
    <div class="contenedor panel-grid">
        <?php menuPanel('usuarios.php', $esAdmin); ?>

        <div class="panel-contenido">
            <div class="encabezado-pagina">
                <div>
                    <h1>Usuarios</h1>
                    <p class="form-sub"><?= array_sum($conteo) ?> cuentas · <?= $conteo['cliente'] ?> clientes · <?= $conteo['negocio'] ?> vendedores · <?= $conteo['admin'] ?> administradores</p>
                </div>
            </div>

            <?php mostrarFlash(); ?>

            <form method="GET" class="filtros">
                <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre o correo">
                <select name="rol">
                    <option value="">Todos los roles</option>
                    <?php foreach (['cliente', 'negocio', 'admin'] as $r): ?>
                        <option value="<?= $r ?>" <?= $filtroRol === $r ? 'selected' : '' ?>><?= etiquetaRol($r) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primario"><?= icono('filter') ?> Filtrar</button>
                <a href="<?= url('admin/usuarios.php') ?>" class="btn btn-borde">Limpiar</a>
            </form>

            <div class="tabla-caja">
                <table class="tabla">
                    <thead><tr><th>Usuario</th><th>Teléfono</th><th>Rol</th><th>Citas</th><th>Alta</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$usuarios): ?><tr><td colspan="7" class="vacio">Sin resultados.</td></tr><?php endif; ?>
                    <?php foreach ($usuarios as $x): $esYo = (int)$x['id'] === (int)$u['id']; ?>
                        <tr>
                            <td data-label="Usuario">
                                <div class="celda-usuario">
                                    <?= avatarHtml($x) ?>
                                    <div><strong><?= e($x['nombre']) ?></strong><br><small><?= e($x['email']) ?></small></div>
                                </div>
                            </td>
                            <td data-label="Teléfono"><?= e($x['telefono']) ?></td>
                            <td data-label="Rol">
                                <?php if ($esYo): ?>
                                    <?= etiquetaRol($x['rol']) ?> <small>(vos)</small>
                                <?php else: ?>
                                    <form method="POST">
                                        <?= csrfCampo() ?>
                                        <input type="hidden" name="accion" value="rol">
                                        <input type="hidden" name="id" value="<?= $x['id'] ?>">
                                        <select name="rol" class="select-chico" onchange="if(confirm('¿Cambiar el rol de este usuario?')) this.form.submit(); else this.form.reset();">
                                            <?php foreach (['cliente', 'negocio', 'admin'] as $r): ?>
                                                <option value="<?= $r ?>" <?= $x['rol'] === $r ? 'selected' : '' ?>><?= etiquetaRol($r) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td data-label="Citas"><?= (int)$x['total_citas'] ?></td>
                            <td data-label="Alta"><?= date('d/m/Y', strtotime($x['creado_en'])) ?></td>
                            <td data-label="Estado"><?= $x['activo'] ? '<span class="estado estado-confirmada">Activo</span>' : '<span class="estado estado-cancelada">Bloqueado</span>' ?></td>
                            <td>
                                <?php if (!$esYo): ?>
                                    <form method="POST">
                                        <?= csrfCampo() ?>
                                        <input type="hidden" name="accion" value="toggle">
                                        <button name="id" value="<?= $x['id'] ?>" class="mini <?= $x['activo'] ? 'mini-peligro' : 'mini-ok' ?>">
                                            <?= $x['activo'] ? icono('ban') . ' Bloquear' : icono('unlock') . ' Activar' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
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
