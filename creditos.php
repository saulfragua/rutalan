<?php
include 'includes/header.php';
include 'includes/conexion.php';

session_start();
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['admin', 'cobrador'])) {
    header("Location: login.php");
    exit();
}

// Obtener el término de búsqueda si existe
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Construir la consulta SQL base
$sql = "SELECT c.*, cl.nombres, cl.apellidos 
        FROM creditos c
        JOIN clientes cl ON c.id_cliente = cl.id_cliente
        WHERE c.saldo_actual > 0";

// Si hay un término de búsqueda, agregar la condición a la consulta
if (!empty($searchTerm)) {
    $sql .= " AND (cl.nombres LIKE '%$searchTerm%' OR cl.apellidos LIKE '%$searchTerm%')";
}

$result = $conexion->query($sql);
?>

<div class="container mt-5">
    <h2><i class="bi bi-cash-stack"></i> Gestión de Créditos</h2>
    
    <!-- Botón para crear nuevo crédito -->
    <a href="gestion_credito.php?accion=crear" class="btn btn-primary mb-3">
        <i class="bi bi-plus-circle"></i> Nuevo Crédito
    </a>
    
    <!-- Botón para volver al menú principal -->
    <a href="dashboard.php" class="btn btn-secondary mb-3">
        <i class="bi bi-arrow-left-circle"></i> Volver al Menú Principal
    </a>

    <!-- Barra de búsqueda -->
    <form method="GET" action="" class="mb-3">
        <div class="input-group">
            <input type="text" class="form-control" name="search" placeholder="Buscar cliente..." value="<?= htmlspecialchars($searchTerm) ?>">
            <button class="btn btn-outline-secondary" type="submit">
                <i class="bi bi-search"></i> Buscar
            </button>
        </div>
    </form>

    <div class="card">
        <div class="card-body">
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <th><i class="bi bi-key"></i> ID Crédito</th>
                        <th><i class="bi bi-person"></i> Cliente</th>
                        <th><i class="bi bi-cash"></i> Monto</th>
                        <th><i class="bi bi-calendar"></i> Cuotas</th>
                        <th><i class="bi bi-percent"></i> Tasa de Interés</th>
                        <th><i class="bi bi-clock"></i> Frecuencia</th>
                        <th><i class="bi bi-shield-lock"></i> Seguro</th>
                        <th><i class="bi bi-wallet2"></i> Saldo</th>
                        <th><i class="bi bi-gear"></i> Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($credito = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $credito['id_credito'] ?></td>
                        <td><?= $credito['nombres'] . ' ' . $credito['apellidos'] ?></td>
                        <td>$<?= number_format($credito['monto_credito'], 2) ?></td>
                        <td><?= $credito['cuotas'] ?> días</td>
                        <td><?= $credito['tasa_interes'] ?>%</td>
                        <td><?= ucfirst($credito['frecuencia_pago']) ?></td>
                        <td><?= $credito['seguro'] ? '$' . number_format($credito['seguro'], 2) : 'No' ?></td>
                        <td>$<?= number_format($credito['saldo_actual'], 2) ?></td>
                        <td>
                            <a href="gestion_credito.php?accion=editar&id=<?= $credito['id_credito'] ?>" 
                               class="btn btn-sm btn-warning">
                                <i class="bi bi-pencil-square"></i> Editar
                            </a>
                            <a href="plan_pagos.php?id=<?= $credito['id_credito'] ?>" 
                               class="btn btn-sm btn-info">
                                <i class="bi bi-list-task"></i> Ver Plan
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>