<?php
require __DIR__ . '/../config/conexion.php';

if (usuarioActual()) redirigir('index.php');

$enviado = false;
$enlace  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = crearTokenRecuperacion($pdo, $_POST['email'] ?? '');
    $enviado = true;
    if ($token) {
        $esquema = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
        $enlace  = $esquema . '://' . $_SERVER['HTTP_HOST'] . url('auth/restablecer.php?token=' . $token);
        // En producción aquí se enviaría $enlace por correo electrónico.
    }
}

$titulo = 'Recuperar contraseña';
require __DIR__ . '/../includes/header.php';
?>

<section class="seccion-form">
    <div class="form-caja">
        <span class="icono-cuadro icono-grande"><?= icono('key') ?></span>
        <h1>Recuperar contraseña</h1>
        <p class="form-sub">Te damos un enlace para crear una contraseña nueva. Vence en 1 hora.</p>

        <?php if ($enviado): ?>
            <div class="alerta alerta-exito">Si el correo está registrado, se generó un enlace de recuperación.</div>

            <?php if (MODO_DEMO && $enlace): ?>
                <div class="caja-demo">
                    <strong><?= icono('flask') ?> Modo demostración</strong>
                    <p>Como el proyecto no envía correos, el enlace se muestra aquí:</p>
                    <a href="<?= e($enlace) ?>" class="btn btn-primario btn-bloque"><?= icono('lock-open') ?> Crear nueva contraseña</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <form method="POST" class="formulario">
                <?= csrfCampo() ?>
                <label>Correo de tu cuenta
                    <input type="email" name="email" required autofocus>
                </label>
                <button class="btn btn-primario btn-bloque btn-grande">Generar enlace</button>
            </form>
        <?php endif; ?>

        <p class="form-pie"><a href="<?= url('auth/login.php') ?>"><?= icono('arrow-left') ?> Volver al inicio de sesión</a></p>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
