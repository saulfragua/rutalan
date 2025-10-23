<?php
include 'includes/header.php';
include 'includes/conexion.php';

session_start();
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['admin', 'cobrador', 'consultor'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: gestion_clientes.php");
    exit();
}

$id_cliente = $_GET['id'];

// Obtener información del cliente
$sql_cliente = "SELECT * FROM clientes WHERE id_cliente = ?";
$stmt_cliente = $conexion->prepare($sql_cliente);
$stmt_cliente->bind_param("i", $id_cliente);
$stmt_cliente->execute();
$result_cliente = $stmt_cliente->get_result();
$cliente = $result_cliente->fetch_assoc();

if (!$cliente) {
    header("Location: gestion_clientes.php");
    exit();
}

// Obtener historial de créditos del cliente
$sql_creditos = "SELECT c.*, 
                (SELECT COUNT(*) FROM planpagos pp WHERE pp.id_credito = c.id_credito AND pp.estado = 'pendiente') as cuotas_pendientes,
                (SELECT COUNT(*) FROM planpagos pp WHERE pp.id_credito = c.id_credito AND pp.estado = 'vencida') as cuotas_vencidas
                FROM creditos c 
                WHERE c.id_cliente = ?
                ORDER BY c.fecha_toma_credito DESC";
$stmt_creditos = $conexion->prepare($sql_creditos);
$stmt_creditos->bind_param("i", $id_cliente);
$stmt_creditos->execute();
$result_creditos = $stmt_creditos->get_result();

// Obtener historial de pagos del cliente
$sql_pagos = "SELECT p.*, c.monto_credito, c.fecha_toma_credito, c.cuotas 
              FROM pagos p
              JOIN creditos c ON p.id_credito = c.id_credito
              WHERE p.id_cliente = ?
              ORDER BY p.fecha_pago DESC, p.hora_pago DESC";
$stmt_pagos = $conexion->prepare($sql_pagos);
$stmt_pagos->bind_param("i", $id_cliente);
$stmt_pagos->execute();
$result_pagos = $stmt_pagos->get_result();
?>

<div class="container mt-5">
    <!-- Botón para volver -->
    <a href="clientes.php" class="btn btn-secondary mb-3">
        <i class="bi bi-arrow-left-circle"></i> Volver a Gestión de Clientes
    </a>

    <h2><i class="bi bi-clock-history"></i> Historial de <?php echo htmlspecialchars($cliente['nombres'] . ' ' . $cliente['apellidos']); ?></h2>
    
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-info-circle"></i> Información del Cliente
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Documento:</strong> <?php echo htmlspecialchars($cliente['documento']); ?></p>
                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($cliente['telefono']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Dirección:</strong> <?php echo htmlspecialchars($cliente['direccion']); ?></p>
                    <p><strong>Estado:</strong> 
                        <span class="badge <?php echo $cliente['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo $cliente['activo'] ? 'Activo' : 'Inactivo'; ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Sección de Créditos Activos -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <i class="bi bi-credit-card"></i> Historial de Créditos
        </div>
        <div class="card-body">
            <?php if ($result_creditos->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID Crédito</th>
                                <th>Fecha Inicio</th>
                                <th>Monto</th>
                                <th>Cuotas</th>
                                <th>Interés</th>
                                <th>Frecuencia</th>
                                <th>Saldo Actual</th>
                                <th>Estado</th>
                                <th>Cuotas Pendientes</th>
                                <th>Cuotas Vencidas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($credito = $result_creditos->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $credito['id_credito']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($credito['fecha_toma_credito'])); ?></td>
                                    <td><?php echo number_format($credito['monto_credito'], 2); ?></td>
                                    <td><?php echo $credito['cuotas']; ?></td>
                                    <td><?php echo $credito['tasa_interes']; ?>%</td>
                                    <td><?php echo ucfirst($credito['frecuencia_pago']); ?></td>
                                    <td><?php echo number_format($credito['saldo_actual'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo $credito['activo'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $credito['activo'] ? 'Activo' : 'Finalizado'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $credito['cuotas_pendientes'] > 0 ? 'bg-warning' : 'bg-success'; ?>">
                                            <?php echo $credito['cuotas_pendientes']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $credito['cuotas_vencidas'] > 0 ? 'bg-danger' : 'bg-success'; ?>">
                                            <?php echo $credito['cuotas_vencidas']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">El cliente no tiene historial de créditos.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sección de Pagos Realizados -->
    <div class="card">
        <div class="card-header bg-success text-white">
            <i class="bi bi-cash-stack"></i> Historial de Pagos
        </div>
        <div class="card-body">
            <?php if ($result_pagos->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID Pago</th>
                                <th>ID Crédito</th>
                                <th>Fecha Pago</th>
                                <th>Hora Pago</th>
                                <th>Monto Pagado</th>
                                <th>Monto Excedente</th>
                                <th>Descuento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($pago = $result_pagos->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $pago['id_pago']; ?></td>
                                    <td><?php echo $pago['id_credito']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($pago['fecha_pago'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($pago['hora_pago'])); ?></td>
                                    <td><?php echo number_format($pago['monto_pagado'], 2); ?></td>
                                    <td><?php echo number_format($pago['monto_excedente'], 2); ?></td>
                                    <td><?php echo number_format($pago['descuento'], 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">El cliente no tiene historial de pagos registrados.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>