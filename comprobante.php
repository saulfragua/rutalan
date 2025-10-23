<?php
<?php
include 'includes/header.php';
include 'includes/conexion.php';

// Establecer la zona horaria
date_default_timezone_set('America/Bogota'); // Cambia esto según tu zona horaria

// Configurar el idioma local para fechas en español
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es');

session_start();
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['admin', 'cobrador'])) {
    header("Location: login.php");
    exit();
}


// Verificar si se ha proporcionado el ID del pago
if (!isset($_GET['id_pago']) {
    die("ID de pago no proporcionado.");
}

$id_pago = $_GET['id_pago'];
$ruta = $_GET['ruta'] ?? null;
$indice = $_GET['indice'] ?? 0;

// Lógica para generar el comprobante de pago
// ...

// Redirigir de vuelta a la gestión de pagos
if ($ruta !== null) {
    header("Location: gestion_pago.php?ruta=$ruta&indice=$indice");
    exit();
} else {
    die("No se pudo redirigir: falta el parámetro 'ruta'.");
}
?>