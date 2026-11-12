<?php
require __DIR__ . '/../config/conexion.php';

$token   = $_GET['token'] ?? $_POST['token'] ?? '';
$valido  = usuarioPorTokenRecuperacion($pdo, $token) !== null;
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valido) {
    $r = restablecerPassword($pdo, $token, $_POST['password'] ?? '', $_POST['password2'] ?? '');
    if ($r['ok']) {
        flash('Tu contraseña fue cambiada. Ya podés iniciar sesión.');
        redirigir('auth/login.php');
    }
    $errores = $r['errores'];
}

$titulo = 'Nueva contraseña';
require __DIR__ . '/../includes/header.php';
?>

<section class="seccion-form">
    <div class="form-caja">
        <span class="icono-cuadro icono-grande"><?= icono('lock') ?></span>
        <h1>Nueva contraseña</h1>

        <?php if (!$valido): ?>
            <div class="alerta alerta-error">El enlace no es válido o ya venció.</div>
            <a href="<?= url('auth/recuperar.php') ?>" class="btn btn-primario btn-bloque">Pedir un enlace nuevo</a>
        <?php else: ?>
            <p class="form-sub">Elegí una contraseña segura.</p>
            <?php mostrarErrores($errores); ?>
            <form method="POST" class="formulario">
                <?= csrfCampo() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <label>Nueva contraseña
                    <input type="password" name="password" minlength="8" required autocomplete="new-password">
                </label>
                <label>Repetir contraseña
                    <input type="password" name="password2" minlength="8" required autocomplete="new-password">
                </label>
                <small class="ayuda"><?= icono('circle-info') ?> Mínimo 8 caracteres, con letras y números.</small>
                <button class="btn btn-primario btn-bloque btn-grande">Guardar contraseña</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
