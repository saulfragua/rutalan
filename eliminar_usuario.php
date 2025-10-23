<?php
include 'includes/conexion.php';
include 'includes/permisos.php';

// Iniciar sesión solo una vez
session_start();

$rol = $_SESSION['rol'];

// Verificar si el usuario tiene permiso para ver esta página
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['admin', 'cobrador', 'consultor'])) {
    header("Location: login.php");
    exit();
}
// Obtener el ID del usuario a eliminar
$id_usuario = $_GET['id'] ?? null;
if (!$id_usuario) {
    header("Location: registro.php");
    exit();
}

// Eliminar usuario de la base de datos
$sql = "DELETE FROM usuarios WHERE id_usuario = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();

header("Location: actualiza_usuario.php");
exit();
?>