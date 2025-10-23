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

// Obtener parámetros de filtro
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$id_usuario = $_GET['id_usuario'] ?? '';

// Obtener lista de usuarios para el select (solo para admin)
$usuarios = [];
if ($_SESSION['rol'] == 'admin') {
    $queryUsuarios = "SELECT id_usuario, nombre_completo FROM usuarios WHERE rol = 'cobrador' AND estado = 'activo'";
    $resultUsuarios = $conexion->query($queryUsuarios);
    while ($usuario = $resultUsuarios->fetch_assoc()) {
        $usuarios[] = $usuario;
    }
}

// Consulta SQL para obtener los créditos
$sql = "SELECT 
        c.id_cliente,
        c.nombres, c.apellidos, c.direccion, c.telefono,
        cr.fecha_toma_credito, cr.hora_toma_credito, 
        cr.monto_credito, cr.saldo_actual, cr.seguro,
        cr.tipo_credito,
        u.nombre_completo AS usuario_registrador
        FROM creditos cr
        INNER JOIN clientes c ON cr.id_cliente = c.id_cliente
        INNER JOIN usuarios u ON cr.id_usuario = u.id_usuario
        WHERE cr.fecha_toma_credito BETWEEN ? AND ?";

// Agregar condición para filtro por usuario
if (!empty($id_usuario)) {
    $sql .= " AND cr.id_usuario = ?";
}

// Si el usuario no es admin, solo mostrar sus propios créditos
if ($_SESSION['rol'] == 'cobrador') {
    $sql .= " AND cr.id_usuario = ?";
}

$sql .= " ORDER BY cr.fecha_toma_credito DESC";

// Preparar la consulta
$stmt = $conexion->prepare($sql);
if (!$stmt) {
    die("Error en la preparación de la consulta: " . $conexion->error);
}

// Vincular parámetros según los filtros aplicados
if ($_SESSION['rol'] == 'cobrador') {
    $stmt->bind_param("ssi", $fecha_inicio, $fecha_fin, $_SESSION['id_usuario']);
} elseif (!empty($id_usuario)) {
    $stmt->bind_param("ssi", $fecha_inicio, $fecha_fin, $id_usuario);
} else {
    $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
}

// Ejecutar consulta
if (!$stmt->execute()) {
    die("Error al ejecutar la consulta: " . $stmt->error);
}

$resultado = $stmt->get_result();

// Variables para los totales
$total_valor_prestado = 0;
$total_a_pagar = 0;
$total_seguros = 0;
$creditos_data = [];

if ($resultado->num_rows > 0) {
    $creditos_data = $resultado->fetch_all(MYSQLI_ASSOC);
    $total_valor_prestado = array_sum(array_column($creditos_data, 'monto_credito'));
    $total_a_pagar = array_sum(array_column($creditos_data, 'saldo_actual'));
    $total_seguros = array_sum(array_column($creditos_data, 'seguro'));
}
?>

<div class="container mt-5">
    <h2><i class="bi bi-list me-2"></i>Listado de Créditos</h2>
    
    <!-- Filtro por rango de fechas -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-filter"></i> Filtros
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" 
                           value="<?= htmlspecialchars($fecha_inicio) ?>" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" name="fecha_fin" 
                           value="<?= htmlspecialchars($fecha_fin) ?>" required>
                </div>
                
                <?php if ($_SESSION['rol'] == 'admin' && !empty($usuarios)): ?>
                <div class="col-md-3">
                    <label class="form-label">Cobrador:</label>
                    <select name="id_usuario" class="form-select">
                        <option value="">Todos los cobradores</option>
                        <?php foreach ($usuarios as $usuario): ?>
                            <option value="<?= $usuario['id_usuario'] ?>" <?= $id_usuario == $usuario['id_usuario'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($usuario['nombre_completo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-filter"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Listado de créditos -->
    <div class="card">
        <div class="card-header bg-success text-white">
            <i class="bi bi-list-ul"></i> Listado de Créditos
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID Cliente</th>
                            <th>Cliente</th>
                            <th>Dirección</th>
                            <th>Teléfono</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th class="text-end">Valor Prestado</th>
                            <th>Tipo de Crédito</th>
                            <th class="text-end">Total a Pagar</th>
                            <th class="text-end">Seguro</th>
                            <th>Registrado por</th>
                        </tr>
                        <a href="reportes.php" class="btn btn-secondary mb-1">
                            <i class="bi bi-arrow-left-circle"></i> Volver a Reportes
                        </a>
                    </thead>
                    <tbody>
                        <?php if (!empty($creditos_data)): ?>
                            <?php foreach($creditos_data as $credito): ?>
                                <tr>
                                    <td><?= htmlspecialchars($credito['id_cliente']) ?></td>
                                    <td><?= htmlspecialchars($credito['nombres'] . ' ' . $credito['apellidos']) ?></td>
                                    <td><?= htmlspecialchars($credito['direccion']) ?></td>
                                    <td><?= htmlspecialchars($credito['telefono']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($credito['fecha_toma_credito'])) ?></td>
                                    <td><?= htmlspecialchars($credito['hora_toma_credito']) ?></td>
                                    <td class="text-end">$<?= number_format($credito['monto_credito'], 2) ?></td>
                                    <td><?= htmlspecialchars($credito['tipo_credito']) ?></td>
                                    <td class="text-end">$<?= number_format($credito['saldo_actual'], 2) ?></td>
                                    <td class="text-end">$<?= number_format($credito['seguro'], 2) ?></td>
                                    <td><?= htmlspecialchars($credito['usuario_registrador']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center">No se encontraron créditos con los filtros aplicados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="fw-bold">
                        <tr>
                            <td colspan="6" class="text-end">Totales:</td>
                            <td class="text-end">$<?= number_format($total_valor_prestado, 2) ?></td>
                            <td></td>
                            <td class="text-end">$<?= number_format($total_a_pagar, 2) ?></td>
                            <td class="text-end">$<?= number_format($total_seguros, 2) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>