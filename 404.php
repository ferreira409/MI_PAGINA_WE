<?php
require_once __DIR__ . '/config/conexion.php';
http_response_code(404);
$titulo = 'Página no encontrada';
require __DIR__ . '/includes/header.php';
?>

<section class="seccion-form">
    <div class="form-caja error-404">
        <span class="numero-404">404</span>
        <h1>No encontramos esta página</h1>
        <p class="form-sub">Puede que el enlace esté mal escrito o que la página ya no exista.</p>
        <div class="hero-botones centrado">
            <a href="<?= url('index.php') ?>" class="btn btn-primario"><?= icono('house') ?> Ir al inicio</a>
            <a href="<?= url('negocios/index.php') ?>" class="btn btn-borde"><?= icono('magnifying-glass') ?> Buscar negocios</a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
