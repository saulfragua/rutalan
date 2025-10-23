<?php
include 'includes/header.php';
include 'includes/conexion.php';

// Establecer la zona horaria
date_default_timezone_set('America/Bogota'); // Cambia esto según tu zona horaria

// Configurar el idioma local para fechas en español
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es');

session_start();
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['admin', 'cobrador', 'consultor'])) {
    header("Location: login.php");
    exit();
}

// Lógica para generar el Reporte General
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reporte_general'])) {
    // Consulta para obtener los créditos con saldo actual
    $sql = "SELECT c.id_credito, c.id_cliente, c.saldo_actual, cl.nombres, cl.apellidos 
            FROM creditos c
            JOIN clientes cl ON c.id_cliente = cl.id_cliente
            WHERE c.activo = 1 AND c.saldo_actual > 0
            ORDER BY cl.nombres ASC";
    $resultado = $conexion->query($sql);

    if ($resultado === false) {
        die("Error en la consulta: " . $conexion->error);
    }

    $creditos = $resultado->fetch_all(MYSQLI_ASSOC);

    // Calcular el total general
    $total_general = 0;
    foreach ($creditos as $credito) {
        $total_general += $credito['saldo_actual'];
    }
}

// Obtener métricas generales
$metricas = [
    'total_clientes' => 0,
    'con_credito' => 0,
    'sin_credito' => 0
];

try {
    // Total de clientes
    $query = "SELECT COUNT(*) as total FROM clientes";
    $result = $conexion->query($query);
    $metricas['total_clientes'] = $result->fetch_assoc()['total'];

    // Clientes con crédito activo
    $query = "SELECT COUNT(DISTINCT c.id_cliente) as total 
              FROM clientes c
              JOIN creditos cr ON c.id_cliente = cr.id_cliente
              WHERE cr.activo = 1 AND cr.saldo_actual > 0";
    $result = $conexion->query($query);
    $metricas['con_credito'] = $result->fetch_assoc()['total'];

    // Clientes sin crédito
    $metricas['sin_credito'] = $metricas['total_clientes'] - $metricas['con_credito'];

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error al obtener métricas: " . $e->getMessage() . "</div>";
}
?>

<div class="container mt-4">
    <!-- Tarjetas de Métricas -->
    <div class="row mb-4">
        <!-- Tarjeta Total Clientes -->
        <div class="col-md-4 mb-3">
            <div class="card text-white bg-primary h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-people-fill me-2"></i>Total Clientes</h5>
                    <h2 class="card-text"><?= $metricas['total_clientes'] ?></h2>
                </div>
            </div>
        </div>

        <!-- Tarjeta Con Crédito -->
        <div class="col-md-4 mb-3">
            <div class="card text-white bg-success h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-wallet2 me-2"></i>Con Crédito Activo</h5>
                    <h2 class="card-text"><?= $metricas['con_credito'] ?></h2>
                </div>
            </div>
        </div>

        <!-- Tarjeta Sin Crédito -->
        <div class="col-md-4 mb-3">
            <div class="card text-white bg-danger h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-wallet me-2"></i>Crédito Cancelados</h5>
                    <h2 class="card-text"><?= $metricas['sin_credito'] ?></h2>
                </div>
            </div>
        </div>
    </div>

    <h2 class="mb-4"><i class="bi bi-file-earmark-text me-2"></i> Reporte General</h2>

    <!-- Botón para generar el Reporte General -->
    <form method="POST" class="mb-4">
        <button type="submit" name="reporte_general" class="btn btn-primary">
            <i class="bi bi-file-earmark-text"></i> Generar Reporte General
        </button>
    </form>
    <!-- Botón para volver a reportes -->
    <a href="reportes.php" class="btn btn-secondary mb-3">
        <i class="bi bi-arrow-left-circle"></i> Volver a Reportes
    </a>

    <!-- Mostrar el Reporte General -->
    <?php if (isset($creditos)): ?>
        <div class="card shadow">
            <div class="card-body">
                <h4 class="card-title">Detalle de Créditos</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>ID Crédito</th>
                                <th>Cliente</th>
                                <th>Saldo Actual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($creditos as $credito): ?>
                                <tr>
                                    <td><?= $credito['id_credito'] ?></td>
                                    <td><?= $credito['nombres'] ?> <?= $credito['apellidos'] ?></td>
                                    <td>$<?= number_format($credito['saldo_actual'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    <h4 class="text-end">Total General: <strong>$<?= number_format($total_general, 2) ?></strong></h4>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>