<?php
// Conexión a la base de datos
include 'includes/conexion.php';
// Verificar la conexión
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
// Validar que los parámetros estén presentes en la URL
if (isset($_GET['id_usuario']) && isset($_GET['estado'])) {
    // Capturar el ID y el nuevo estado del usuario
    $id_usuario = intval($_GET['id_usuario']); // Convertir a entero para seguridad
    $estado = $conexion->real_escape_string($_GET['estado']); // Escapar el valor

    // Validar que el estado sea "activo" o "inactivo"
    if ($estado === 'activo' || $estado === 'inactivo') {
        // Actualizar el estado del usuario
        $sql = "UPDATE usuarios SET estado = '$estado' WHERE id_usuario = $id_usuario";

        if ($conexion->query($sql)) {
            // Redireccionar si la consulta fue exitosa
            header("Location: gestion_usuarios.php");
            exit(); // Terminar la ejecución del script
        } else {
            echo "Error al cambiar el estado del usuario: " . $conexion->error;
        }
    } else {
        echo "Estado no válido. Debe ser 'activo' o 'inactivo'.";
    }
} else {
    echo "Parámetros 'id_usuario' y 'estado' no proporcionados.";
}

// Cerrar la conexión
$conexion->close();
?>