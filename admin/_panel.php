<?php
// Código común del panel: sesión, permisos y negocios que puede administrar el usuario.
require_once __DIR__ . '/../config/conexion.php';

$u = requiereLogin(['admin', 'negocio']);
$esAdmin = $u['rol'] === 'admin';

$misNegocios = negociosDeUsuario($pdo, $u);
$idsNegocios = array_map('intval', array_column($misNegocios, 'id'));

// Negocio seleccionado (para "Mi negocio" y "Servicios")
function negocioSeleccionado() {
    global $idsNegocios;
    $id = (int)($_GET['negocio'] ?? $_POST['negocio'] ?? ($idsNegocios[0] ?? 0));
    return in_array($id, $idsNegocios, true) ? $id : ($idsNegocios[0] ?? 0);
}

// Selector de negocio (solo si administra más de uno)
function selectorNegocio($idActual) {
    global $misNegocios;
    if (count($misNegocios) < 2) return;
    echo '<form method="GET" class="selector-negocio"><label>' . icono('store') . ' Negocio: <select name="negocio" onchange="this.form.submit()">';
    foreach ($misNegocios as $n) {
        echo '<option value="' . (int)$n['id'] . '"' . ((int)$n['id'] === $idActual ? ' selected' : '') . '>' . e($n['nombre']) . '</option>';
    }
    echo '</select></label></form>';
}

// Menú lateral del panel
function menuPanel($activo, $esAdmin) {
    global $u;
    echo '<aside class="panel-lateral">';
    echo '<div class="perfil-card">'
       . '<div class="perfil-portada"></div>'
       . avatarHtml($u, 'avatar avatar-grande')
       . '<strong>' . e($u['nombre']) . '</strong>'
       . '<span class="banner-etiqueta">' . e(etiquetaRol($u['rol'])) . '</span>'
       . '</div>';

    $items = [
        'index.php'      => icono('chart-pie') . ' Resumen y citas',
        'mi_negocio.php' => icono('store') . ($esAdmin ? ' Editar negocios' : ' Mi negocio'),
        'servicios.php'  => icono('list-check') . ' Servicios',
    ];
    if ($esAdmin) {
        $items['negocios.php'] = icono('shop') . ' Todos los negocios';
        $items['usuarios.php'] = icono('users') . ' Usuarios';
        $items['encuesta.php'] = icono('square-poll-vertical') . ' Encuesta';
    }
    echo '<nav class="panel-menu">';
    foreach ($items as $archivo => $texto) {
        echo '<a href="' . url('admin/' . $archivo) . '" class="' . ($archivo === $activo ? 'activo' : '') . '">' . $texto . '</a>';
    }
    echo '<a href="' . url('cuenta/perfil.php') . '">' . icono('user-gear') . ' Mi cuenta</a>';
    echo '</nav></aside>';
}
