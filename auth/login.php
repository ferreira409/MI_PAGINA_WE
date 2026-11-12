<?php
require __DIR__ . '/../config/conexion.php';

if (usuarioActual()) redirigir('index.php');

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $r = intentarLogin($pdo, $email, $_POST['password'] ?? '', ipCliente());

    if ($r['ok']) {
        iniciarSesion($r['usuario']);
        flash('¡Bienvenido, ' . $r['usuario']['nombre'] . '!');
        redirigir($r['usuario']['rol'] === 'cliente' ? 'index.php' : 'admin/index.php');
    }
    $error = $r['error'];
}

$titulo = 'Iniciar sesión';
require __DIR__ . '/../includes/header.php';
?>

<section class="seccion-form">
    <div class="form-con-imagen">
        <div class="form-imagen" style="--img:url('<?= e(imgScz('catedral')) ?>')">
            <div>
                <h2>Qué gusto verte de nuevo</h2>
                <p>Tus citas en Santa Cruz te esperan. Entrá y reservá en segundos.</p>
            </div>
        </div>
        <div class="form-caja">
            <h1>Iniciar sesión</h1>
            <p class="form-sub">Ingresá para gestionar tus citas</p>

            <?php mostrarFlash(); ?>
            <?php if ($error): ?><div class="alerta alerta-error"><?= e($error) ?></div><?php endif; ?>

            <form method="POST" class="formulario">
                <?= csrfCampo() ?>
                <label>Correo electrónico
                    <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="email">
                </label>
                <label>Contraseña
                    <span class="campo-password">
                        <input type="password" name="password" required autocomplete="current-password">
                        <button type="button" class="ver-password" aria-label="Mostrar contraseña"><?= icono('eye', 'regular') ?></button>
                    </span>
                </label>
                <a href="<?= url('auth/recuperar.php') ?>" class="enlace-chico">¿Olvidaste tu contraseña?</a>
                <button type="submit" class="btn btn-primario btn-bloque btn-grande"><?= icono('right-to-bracket') ?> Entrar</button>
            </form>

            <p class="form-pie">¿No tenés cuenta? <a href="<?= url('auth/registro.php') ?>">Registrate</a></p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
