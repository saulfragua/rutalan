<?php
include 'includes/conexion.php';

// Establecer la zona horaria
date_default_timezone_set('America/Bogota');

// Obtener la fecha actual del servidor
$fecha_actual = date('Y-m-d');

// Consulta para actualizar cuotas pendientes a vencidas
$sql_actualizar_vencidas = "UPDATE planpagos 
                            SET estado = 'vencida' 
                            WHERE estado = 'pendiente' AND fecha_pago < ?";

// Preparar la consulta
$stmt_actualizar_vencidas = $conexion->prepare($sql_actualizar_vencidas);
$stmt_actualizar_vencidas->bind_param("s", $fecha_actual);

// Ejecutar la consulta
if ($stmt_actualizar_vencidas->execute()) {
    echo "Cuotas vencidas actualizadas correctamente.\n";
} else {
    echo "Error al actualizar cuotas vencidas: " . $conexion->error . "\n";
}

// Cerrar la conexión
$stmt_actualizar_vencidas->close();
$conexion->close();
?>