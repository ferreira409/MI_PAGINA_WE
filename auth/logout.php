<?php
require __DIR__ . '/../config/conexion.php';

// Solo por POST (el formulario del menú lleva el código CSRF)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirigir('index.php');

$_SESSION = [];
session_destroy();

session_start();
flash('Cerraste sesión correctamente.');
redirigir('index.php');
