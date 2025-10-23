<?php
include 'includes/conexion.php';

// Establecer la zona horaria
date_default_timezone_set('America/Bogota');

session_start();
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['admin', 'cobrador'])) {
    header("Location: login.php");
    exit();
}

// Verificar la conexión a la base de datos
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

// Obtener los datos del formulario
$id_credito = isset($_POST['id_credito']) ? intval($_POST['id_credito']) : null;
$monto_pagado = isset($_POST['monto_pagado']) ? floatval($_POST['monto_pagado']) : 0;

if (!$id_credito || $monto_pagado <= 0) {
    echo "<div class='alert alert-danger'>Datos de pago inválidos.</div>";
    exit();
}

// Paso 1: Obtener las cuotas pendientes para el crédito
$sql_cuotas_pendientes = "SELECT * FROM planpagos 
                          WHERE id_credito = ? AND estado = 'pendiente' 
                          ORDER BY numero_cuota ASC";
$stmt_cuotas_pendientes = $conexion->prepare($sql_cuotas_pendientes);
$stmt_cuotas_pendientes->bind_param("i", $id_credito);
$stmt_cuotas_pendientes->execute();
$resultado = $stmt_cuotas_pendientes->get_result();
$cuotas_pendientes = $resultado->fetch_all(MYSQLI_ASSOC);

if (empty($cuotas_pendientes)) {
    echo "<div class='alert alert-warning'>No hay cuotas pendientes para este crédito.</div>";
    exit();
}

// Paso 2: Distribuir el monto pagado
foreach ($cuotas_pendientes as $cuota) {
    if ($monto_pagado <= 0) {
        break; // Si no queda monto disponible, detener el proceso
    }

    $monto_restante = $cuota['monto_cuota'] - $monto_pagado;

    if ($monto_restante <= 0) {
        // La cuota se paga completamente
        $sql_actualizar_cuota = "UPDATE planpagos 
                                 SET estado = 'pagado' 
                                 WHERE id_plan_pago = ?";
        $stmt_actualizar_cuota = $conexion->prepare($sql_actualizar_cuota);
        $stmt_actualizar_cuota->bind_param("i", $cuota['id_plan_pago']);
        $stmt_actualizar_cuota->execute();

        // Actualizar el monto restante
        $monto_pagado = abs($monto_restante); // Usar el excedente para la siguiente cuota
    } else {
        // El monto pagado no cubre completamente la cuota
        break; // Detener el proceso si no hay suficiente monto
    }
}

echo "<div class='alert alert-success'>Pago procesado correctamente.</div>";

// Cerrar la conexión
$conexion->close();
?>