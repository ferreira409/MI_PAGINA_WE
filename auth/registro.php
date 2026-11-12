<?php
require __DIR__ . '/../config/conexion.php';

if (usuarioActual()) redirigir('index.php');

$tipo    = ($_GET['tipo'] ?? $_POST['tipo'] ?? 'cliente') === 'negocio' ? 'negocio' : 'cliente';
$errores = [];
$datos   = ['nombre' => '', 'email' => '', 'telefono' => '', 'negocio' => '', 'categoria' => '', 'zona' => '', 'direccion' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($datos as $k => $v) $datos[$k] = trim($_POST[$k] ?? '');
    $r = registrarUsuario($pdo, $datos + [
        'password'  => $_POST['password'] ?? '',
        'password2' => $_POST['password2'] ?? '',
    ], $tipo);

    if ($r['ok']) {
        flash('Cuenta creada. Ya podés iniciar sesión.');
        redirigir('auth/login.php');
    }
    $errores = $r['errores'];
}

$titulo = $tipo === 'negocio' ? 'Registrar negocio' : 'Crear cuenta';
require __DIR__ . '/../includes/header.php';
?>

<section class="seccion-form">
    <div class="form-con-imagen">
        <div class="form-imagen" style="--img:url('<?= e(imgRol($tipo)) ?>')">
            <div>
                <?php if ($tipo === 'negocio'): ?>
                    <h2>Hacé crecer tu negocio</h2>
                    <p>Recibí reservas 24/7 y organizá tu agenda desde un solo panel.</p>
                <?php else: ?>
                    <h2>Reservá sin esperas</h2>
                    <p>Creá tu cuenta y agendá en tus lugares favoritos de Santa Cruz.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-caja">
            <h1><?= $tipo === 'negocio' ? 'Registrá tu negocio' : 'Creá tu cuenta' ?></h1>
            <p class="form-sub"><?= $tipo === 'negocio' ? 'Empezá a recibir citas en línea' : 'Gratis y en menos de un minuto' ?></p>

            <div class="tabs">
                <a href="?tipo=cliente" class="<?= $tipo === 'cliente' ? 'activo' : '' ?>"><?= icono('user') ?> Soy cliente</a>
                <a href="?tipo=negocio" class="<?= $tipo === 'negocio' ? 'activo' : '' ?>"><?= icono('store') ?> Tengo un negocio</a>
            </div>

            <?php mostrarErrores($errores); ?>

            <form method="POST" class="formulario">
                <?= csrfCampo() ?>
                <input type="hidden" name="tipo" value="<?= $tipo ?>">

                <label>Nombre completo
                    <input type="text" name="nombre" value="<?= e($datos['nombre']) ?>" maxlength="100" required autocomplete="name">
                </label>
                <div class="fila-2">
                    <label>Correo electrónico
                        <input type="email" name="email" value="<?= e($datos['email']) ?>" required autocomplete="email">
                    </label>
                    <label>Celular
                        <input type="tel" name="telefono" value="<?= e($datos['telefono']) ?>" placeholder="70000000" autocomplete="tel">
                    </label>
                </div>

                <?php if ($tipo === 'negocio'): ?>
                    <label>Nombre del negocio
                        <input type="text" name="negocio" value="<?= e($datos['negocio']) ?>" required>
                    </label>
                    <div class="fila-2">
                        <label>Categoría
                            <select name="categoria" required><?= opcionesSelect(CATEGORIAS, $datos['categoria'], 'Seleccionar...') ?></select>
                        </label>
                        <label>Zona
                            <select name="zona" required><?= opcionesSelect(ZONAS, $datos['zona'], 'Seleccionar...') ?></select>
                        </label>
                    </div>
                    <label>Dirección
                        <input type="text" name="direccion" value="<?= e($datos['direccion']) ?>" placeholder="Ej: Av. San Martín, 3er anillo">
                    </label>
                <?php endif; ?>

                <div class="fila-2">
                    <label>Contraseña
                        <input type="password" name="password" minlength="8" required autocomplete="new-password">
                    </label>
                    <label>Repetir contraseña
                        <input type="password" name="password2" minlength="8" required autocomplete="new-password">
                    </label>
                </div>
                <small class="ayuda"><?= icono('circle-info') ?> Mínimo 8 caracteres, con letras y números.</small>

                <button type="submit" class="btn btn-primario btn-bloque btn-grande">Crear cuenta</button>
            </form>

            <p class="form-pie">¿Ya tenés cuenta? <a href="<?= url('auth/login.php') ?>">Iniciá sesión</a></p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
