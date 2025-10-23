<?php
include 'includes/conexion.php';
session_start();
// Verificar si el usuario está autenticado y tiene un rol válido
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['admin', 'cobrador', 'consultor'])) {
    header("Location: login.php");
    exit();
}
// Verificar si se enviaron los datos del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_usuario'])) {
    $id_usuario = intval($_POST['id_usuario']);
    $id_ruta = !empty($_POST['id_ruta']) ? intval($_POST['id_ruta']) : NULL;

    // Actualizar la ruta del usuario
    $sql = "UPDATE usuarios SET id_ruta = ? WHERE id_usuario = ?";
    $stmt = $conexion->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $id_ruta, $id_usuario);
        if ($stmt->execute()) {
            // Redirigir con mensaje de éxito
            header("Location: actualiza_usuario.php?exito=1");
            exit();
        } else {
            // Redirigir con mensaje de error
            header("Location: actualiza_usuario.php?error=1");
            exit();
        }
    } else {
        // Redirigir con mensaje de error
        header("Location: actualiza_usuario.php?error=1");
        exit();
    }
} else {
    // Redirigir si no se enviaron los datos correctamente
    header("Location: actualiza_usuario.php");
    exit();
}
?>