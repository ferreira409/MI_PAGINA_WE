<?php
require __DIR__ . '/config/conexion.php';

// Negocios activos (mejor calificados primero)
$negocios = listarNegocios($pdo);

// Números reales para la franja de estadísticas
$totNegocios  = count($negocios);
$totServicios = (int)$pdo->query("SELECT COUNT(*) FROM servicios")->fetchColumn();
$totCitas     = (int)$pdo->query("SELECT COUNT(*) FROM citas")->fetchColumn();

// Próxima cita del cliente (si inició sesión)
$proxima = null;
$u = usuarioActual();
if ($u && $u['rol'] === 'cliente') {
    $st = $pdo->prepare("
        SELECT c.*, n.nombre AS negocio, n.categoria, s.nombre AS servicio, s.duracion_min
        FROM citas c
        JOIN negocios n  ON n.id = c.negocio_id
        JOIN servicios s ON s.id = c.servicio_id
        WHERE c.usuario_id = ? AND c.estado IN ('pendiente','confirmada')
          AND CONCAT(c.fecha,' ',c.hora) >= NOW()
        ORDER BY c.fecha, c.hora LIMIT 1");
    $st->execute([$u['id']]);
    $proxima = $st->fetch();
}

$titulo = 'Reserva tus citas en línea';
$rolVista = $u['rol'] ?? 'visitante';
require __DIR__ . '/includes/header.php';
?>

<!-- ===== HERO CON LA BANDERA CRUCEÑA ===== -->
<section class="hero-bandera">
    <div class="contenedor hero-grid">
        <div class="hero-panel">
            <?php mostrarFlash(); ?>
            <span class="pildora"><?= icono('location-dot') ?> Santa Cruz de la Sierra, Bolivia</span>
            <h1>Agendá tus citas <span class="resaltado">sin hacer fila</span></h1>
            <p>Barberías, spas, clínicas y más negocios de Santa Cruz en un solo lugar. Elegí tu horario y confirmá tu cita en segundos, las 24 horas.</p>

            <div class="hero-botones">
                <a href="<?= url('negocios/index.php') ?>" class="btn btn-primario btn-grande"><?= icono('calendar-plus') ?> Reservar una cita</a>
                <a href="#como-funciona" class="btn btn-borde btn-grande">Cómo funciona <?= icono('arrow-right') ?></a>
            </div>

            <form action="<?= url('negocios/index.php') ?>" method="GET" class="hero-buscador">
                <?= icono('magnifying-glass') ?>
                <input type="search" name="q" placeholder="¿Qué buscás? Ej: corte, masaje, dentista">
                <select name="zona" aria-label="Zona"><?= opcionesSelect(ZONAS, '', 'Toda Santa Cruz') ?></select>
                <button class="btn btn-primario btn-chico">Buscar</button>
            </form>

            <ul class="hero-ventajas">
                <li><?= icono('circle-check') ?> Sin costo para clientes</li>
                <li><?= icono('circle-check') ?> Confirmación inmediata</li>
                <li><?= icono('circle-check') ?> Disponible 24/7</li>
            </ul>
        </div>

        <div class="hero-media">
            <img src="<?= e(imgRol($rolVista)) ?>" alt="Santa Cruz de la Sierra" onerror="this.remove()">

            <div class="cita-card">
                <div class="cita-card-top">
                    <span class="cita-card-titulo"><?= icono('calendar', 'regular') ?> Próxima cita</span>
                    <?php if ($proxima): ?><?= badgeEstado($proxima['estado']) ?><?php endif; ?>
                </div>
                <?php if ($proxima): ?>
                    <div class="cita-card-negocio">
                        <span class="icono-cuadro"><?= iconoCategoria($proxima['categoria']) ?></span>
                        <div>
                            <strong><?= e($proxima['negocio']) ?></strong>
                            <small><?= e($proxima['servicio']) ?> · <?= (int)$proxima['duracion_min'] ?> min</small>
                        </div>
                    </div>
                    <div class="cita-card-datos">
                        <span><?= icono('calendar-day') ?> <?= date('d/m/Y', strtotime($proxima['fecha'])) ?></span>
                        <span><?= icono('clock', 'regular') ?> <?= substr($proxima['hora'], 0, 5) ?></span>
                    </div>
                <?php else: ?>
                    <p class="cita-card-vacia">
                        <?= $u ? 'No tenés citas próximas. Elegí un negocio para reservar.' : 'Iniciá sesión para ver y administrar tus citas.' ?>
                    </p>
                    <a href="<?= url($u ? 'negocios/index.php' : 'auth/login.php') ?>" class="btn btn-primario btn-bloque btn-chico">
                        <?= $u ? 'Ver negocios' : 'Iniciar sesión' ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ===== ESTADÍSTICAS (datos reales de la BD) ===== -->
<section class="franja-stats">
    <div class="contenedor stats-grid">
        <div class="stat-item"><span class="stat-icono"><?= icono('store') ?></span><div><strong data-contar="<?= $totNegocios ?>">0</strong><small>Negocios</small></div></div>
        <div class="stat-item"><span class="stat-icono"><?= icono('list-check') ?></span><div><strong data-contar="<?= $totServicios ?>">0</strong><small>Servicios</small></div></div>
        <div class="stat-item"><span class="stat-icono"><?= icono('calendar-check') ?></span><div><strong data-contar="<?= $totCitas ?>">0</strong><small>Citas agendadas</small></div></div>
        <div class="stat-item"><span class="stat-icono"><?= icono('clock') ?></span><div><strong>24/7</strong><small>Disponible</small></div></div>
    </div>
</section>

<!-- ===== CÓMO FUNCIONA ===== -->
<section class="seccion" id="como-funciona">
    <div class="contenedor">
        <span class="sobretitulo">Cómo funciona</span>
        <h2 class="titulo-seccion">Reservá en 3 pasos</h2>
        <p class="subtitulo-seccion">Sin llamadas, sin filas, sin complicaciones</p>

        <div class="grid-3">
            <div class="paso">
                <div class="card-foto" style="--foto:url('<?= e(imgCategoria('Barbería')) ?>')"><span class="paso-num">01</span></div>
                <span class="icono-cuadro"><?= icono('magnifying-glass') ?></span>
                <h3>Elegí el negocio</h3>
                <p>Buscá entre barberías, spas, clínicas y más.</p>
            </div>
            <div class="paso">
                <div class="card-foto" style="--foto:url('<?= e(unsplash('1512941937669-90a1b58e7e9c', 600)) ?>')"><span class="paso-num">02</span></div>
                <span class="icono-cuadro"><?= icono('calendar-days') ?></span>
                <h3>Escogé fecha y hora</h3>
                <p>Mirá los horarios libres en tiempo real.</p>
            </div>
            <div class="paso">
                <div class="card-foto" style="--foto:url('<?= e(unsplash('1560066984-138dadb4c035', 600)) ?>')"><span class="paso-num">03</span></div>
                <span class="icono-cuadro"><?= icono('circle-check') ?></span>
                <h3>Confirmá tu cita</h3>
                <p>Recibí la confirmación y llegá a tu hora.</p>
            </div>
        </div>
    </div>
</section>

<!-- ===== NEGOCIOS ===== -->
<section class="seccion seccion-gris" id="negocios">
    <div class="contenedor">
        <span class="sobretitulo">Negocios</span>
        <h2 class="titulo-seccion">Negocios disponibles</h2>
        <p class="subtitulo-seccion">Los mejores negocios de Santa Cruz, a un clic</p>

        <?php if (!$negocios): ?>
            <p class="vacio">Todavía no hay negocios registrados.</p>
        <?php endif; ?>

        <div class="grid-4">
            <?php foreach (array_slice($negocios, 0, 8) as $n) include __DIR__ . '/includes/tarjeta_negocio.php'; ?>
        </div>
        <div class="centrado">
            <a href="<?= url('negocios/index.php') ?>" class="btn btn-borde btn-grande">Ver todos los negocios <?= icono('arrow-right') ?></a>
        </div>
    </div>
</section>

<!-- ===== GALERÍA SANTA CRUZ ===== -->
<section class="seccion">
    <div class="contenedor">
        <span class="sobretitulo">Santa Cruz de la Sierra</span>
        <h2 class="titulo-seccion">Hecho para Santa Cruz</h2>
        <p class="subtitulo-seccion">Del Centro a Equipetrol, del Cristo al Urubó: tus citas donde estés</p>

        <div class="galeria">
            <?php foreach (['plaza', 'cristo', 'equipetrol', 'catedral', 'botanico', 'lomas'] as $i => $clave): ?>
                <figure class="galeria-item g-<?= $i ?>">
                    <img src="<?= e($IMG_SCZ[$clave]['url']) ?>" alt="<?= e($IMG_SCZ[$clave]['titulo']) ?>" loading="lazy"
                         onerror="this.parentNode.classList.add('sin-foto')">
                    <figcaption><?= icono('location-dot') ?> <?= e($IMG_SCZ[$clave]['titulo']) ?></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== BENEFICIOS ===== -->
<section class="seccion seccion-gris">
    <div class="contenedor">
        <span class="sobretitulo">Beneficios</span>
        <h2 class="titulo-seccion">Todo lo que necesitás</h2>
        <p class="subtitulo-seccion">Pensado para clientes y para negocios</p>

        <div class="grid-4">
            <div class="beneficio"><div class="card-foto" style="--foto:url('<?= e(imgCategoria('Spa')) ?>')"></div><span class="icono-cuadro"><?= icono('stopwatch') ?></span><h3>Ahorrá tiempo</h3><p>Reservá en segundos desde tu celular.</p></div>
            <div class="beneficio"><div class="card-foto" style="--foto:url('<?= e(imgScz('noche')) ?>')"></div><span class="icono-cuadro"><?= icono('mobile-screen') ?></span><h3>Siempre disponible</h3><p>Agendá de día o de noche, cuando quieras.</p></div>
            <div class="beneficio"><div class="card-foto" style="--foto:url('<?= e(unsplash('1600880292203-757bb62b4baf', 600)) ?>')"></div><span class="icono-cuadro"><?= icono('folder-open') ?></span><h3>Todo ordenado</h3><p>Tus citas en un solo lugar, fáciles de cancelar.</p></div>
            <div class="beneficio"><div class="card-foto" style="--foto:url('<?= e(unsplash('1551288049-bebda4e38f71', 600)) ?>')"></div><span class="icono-cuadro"><?= icono('chart-line') ?></span><h3>Para negocios</h3><p>Panel para confirmar citas y manejar servicios.</p></div>
        </div>
    </div>
</section>

<!-- ===== ENCUESTA ===== -->
<section class="seccion seccion-encuesta">
    <div class="contenedor encuesta-caja">
        <span class="icono-cuadro icono-grande"><?= icono('clipboard-list') ?></span>
        <div>
            <h2>¿Qué te pareció AgendaFácil?</h2>
            <p>Es un proyecto universitario hecho en Santa Cruz. Respondé una encuesta de 1 minuto y ayudanos a mejorar.</p>
        </div>
        <a href="<?= url('encuesta.php') ?>" class="btn btn-primario btn-grande"><?= icono('star') ?> Dar mi opinión</a>
    </div>
</section>

<!-- ===== CTA ===== -->
<section class="cta-bandera">
    <div class="contenedor cta-caja">
        <div>
            <h2>¿Tenés un negocio en Santa Cruz?</h2>
            <p>Registralo gratis y empezá a recibir citas en línea hoy mismo.</p>
        </div>
        <a href="<?= url('auth/registro.php?tipo=negocio') ?>" class="btn btn-primario btn-grande"><?= icono('store') ?> Registrar mi negocio</a>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
