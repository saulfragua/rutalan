<?php
include 'includes/header.php';
include 'includes/conexion.php';

// Establecer la zona horaria
date_default_timezone_set('America/Bogota');
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es');

session_start();
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['admin', 'cobrador', 'consultor'])) {
    header("Location: login.php");
    exit();
}

// Obtener los filtros del formulario
$fechaInicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fechaFin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
$idUsuario = isset($_GET['id_usuario']) ? $_GET['id_usuario'] : '';

// Consulta SQL base
$sql = "SELECT p.id_pago, cl.id_cliente, CONCAT(cl.nombres, ' ', cl.apellidos) AS nombre_cliente, 
               cl.direccion, p.fecha_pago, p.hora_pago, p.monto_pagado, p.descuento,
               u.id_usuario, u.nombre_completo AS nombre_usuario
        FROM pagos p
        JOIN creditos cr ON p.id_credito = cr.id_credito
        JOIN clientes cl ON cr.id_cliente = cl.id_cliente
        LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
        WHERE 1=1";

// Aplicar filtros
if (!empty($fechaInicio) && !empty($fechaFin)) {
    $sql .= " AND p.fecha_pago BETWEEN '$fechaInicio' AND '$fechaFin'";
}

if (!empty($idUsuario)) {
    $sql .= " AND p.id_usuario = '$idUsuario'";
}

// Si el usuario no es admin, solo mostrar sus propios pagos
if ($_SESSION['rol'] == 'cobrador') {
    $sql .= " AND p.id_usuario = '".$_SESSION['id_usuario']."'";
}

$result = $conexion->query($sql);

// Obtener lista de usuarios para el select (solo para admin)
$usuarios = [];
if ($_SESSION['rol'] == 'admin') {
    $queryUsuarios = "SELECT id_usuario, nombre_completo FROM usuarios WHERE rol = 'cobrador' AND estado = 'activo'";
    $resultUsuarios = $conexion->query($queryUsuarios);
    while ($usuario = $resultUsuarios->fetch_assoc()) {
        $usuarios[] = $usuario;
    }
}

// Verificar si la consulta fue exitosa
if ($result === false) {
    die("Error en la consulta SQL: " . $conexion->error);
}

// Calcular el total de pagos y descuentos
$totalPagos = 0;
$totalDescuentos = 0;
$pagosData = [];
if ($result->num_rows > 0) {
    $pagosData = $result->fetch_all(MYSQLI_ASSOC);
    $totalPagos = array_sum(array_column($pagosData, 'monto_pagado'));
    $totalDescuentos = array_sum(array_column($pagosData, 'descuento'));
}
?>

<div class="container mt-5">
    <h2><i class="bi bi-list me-2"></i>Listado de Pagos</h2>
    
    <!-- Formulario de Filtros -->
    <form method="GET" action="" class="mb-4">
        <div class="row">
            <div class="col-md-3">
                <label for="fecha_inicio">Fecha de Inicio:</label>
                <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fechaInicio) ?>">
            </div>
            <div class="col-md-3">
                <label for="fecha_fin">Fecha de Fin:</label>
                <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fechaFin) ?>">
            </div>
            <?php if ($_SESSION['rol'] == 'admin' && !empty($usuarios)): ?>
            <div class="col-md-3">
                <label for="id_usuario">Cobrador:</label>
                <select name="id_usuario" class="form-select">
                    <option value="">Todos los cobradores</option>
                    <?php foreach ($usuarios as $usuario): ?>
                        <option value="<?= $usuario['id_usuario'] ?>" <?= $idUsuario == $usuario['id_usuario'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($usuario['nombre_completo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary mt-4">
                    <i class="bi bi-filter"></i> Filtrar
                </button>
            </div>
        </div>
    </form>

    <!-- Tabla de Resultados -->
    <div class="card">
        <div class="card-body">
            <table class="table table-striped">
                <thead class="bg-dark text-white">
                    <tr>
                        <th>ID Cliente</th>
                        <th>Nombre del Cliente</th>
                        <th>Dirección</th>
                        <th>Fecha de Pago</th>
                        <th>Hora de Pago</th>
                        <th>Valor Pagado</th>
                        <th>Descuento</th>
                        <?php if ($_SESSION['rol'] == 'admin'): ?>
                        <th>Cobrador</th>
                        <?php endif; ?>
                    </tr>
                    <a href="reportes.php" class="btn btn-secondary mb-1">
                        <i class="bi bi-arrow-left-circle"></i> Volver a Reportes
                    </a>
                </thead>
                <tbody>
                    <?php if (!empty($pagosData)): ?>
                        <?php foreach($pagosData as $pago): ?>
                            <tr>
                                <td><?= $pago['id_cliente'] ?></td>
                                <td><?= $pago['nombre_cliente'] ?></td>
                                <td><?= $pago['direccion'] ?></td>
                                <td><?= date('d/m/Y', strtotime($pago['fecha_pago'])) ?></td>
                                <td><?= date('H:i:s', strtotime($pago['hora_pago'])) ?></td>
                                <td>$<?= number_format($pago['monto_pagado'], 2) ?></td>
                                <td><?= $pago['descuento'] > 0 ? '$'.number_format($pago['descuento'], 2) : '-' ?></td>
                                <?php if ($_SESSION['rol'] == 'admin'): ?>
                                <td><?= $pago['nombre_usuario'] ?? 'Sistema' ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $_SESSION['rol'] == 'admin' ? '8' : '7' ?>" class="text-center">No se encontraron resultados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="fw-bold">
                    <tr>
                        <td colspan="<?= $_SESSION['rol'] == 'admin' ? '5' : '4' ?>">Total General:</td>
                        <td>$<?= number_format($totalPagos, 2) ?></td>
                        <td><?= $totalDescuentos > 0 ? '$'.number_format($totalDescuentos, 2) : '-' ?></td>
                        <?php if ($_SESSION['rol'] == 'admin'): ?>
                        <td></td>
                        <?php endif; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>