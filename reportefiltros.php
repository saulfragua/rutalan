<?php
include 'includes/header.php';
include 'includes/conexion.php';

// Establecer la zona horaria
date_default_timezone_set('America/Bogota'); // Cambia esto según tu zona horaria

// Configurar el idioma local para fechas en español
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es');

session_start();
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['admin', 'cobrador'])) {
    header("Location: login.php");
    exit();
}

// Variables de filtro
$filtro_fecha_inicio = $_POST['fecha_inicio'] ?? '';
$filtro_fecha_fin = $_POST['fecha_fin'] ?? '';
$filtro_usuario = $_POST['usuario'] ?? '';
$filtro_ruta = $_POST['ruta'] ?? '';

// Construcción de la consulta con filtros dinámicos
$condiciones = "WHERE c.activo = 1 AND c.saldo_actual > 0";
$params = [];
$types = '';

if (!empty($filtro_fecha_inicio) && !empty($filtro_fecha_fin)) {
    $condiciones .= " AND c.fecha_toma_credito BETWEEN ? AND ?";
    $params[] = $filtro_fecha_inicio;
    $params[] = $filtro_fecha_fin;
    $types .= 'ss'; // Tipo de datos: 's' para strings (fechas)
}

if (!empty($filtro_usuario)) {
    $condiciones .= " AND u.id_usuario = ?";
    $params[] = $filtro_usuario;
    $types .= 'i'; // Tipo de datos: 'i' para integer
}

if (!empty($filtro_ruta)) {
    $condiciones .= " AND cl.id_ruta = ?";
    $params[] = $filtro_ruta;
    $types .= 'i'; // Tipo de datos: 'i' para integer
}

// Consulta SQL con filtros
$sql = "SELECT 
            c.id_credito, 
            c.id_cliente, 
            c.saldo_actual, 
            cl.nombres, 
            cl.apellidos, 
            u.nombre_completo AS cobrador,
            r.nombre_ruta
        FROM Creditos c
        JOIN Clientes cl ON c.id_cliente = cl.id_cliente
        JOIN Usuarios u ON cl.id_usuario = u.id_usuario
        JOIN Rutas r ON cl.id_ruta = r.id_ruta
        $condiciones
        ORDER BY cl.nombres ASC";

// Preparar y ejecutar la consulta con parámetros
$stmt = $conexion->prepare($sql);
if ($stmt === false) {
    die("Error en la consulta SQL: " . $conexion->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$resultado = $stmt->get_result();
$creditos = $resultado->fetch_all(MYSQLI_ASSOC);

// Obtener lista de usuarios/cobradores
$usuarios_query = "SELECT id_usuario, nombre_completo FROM Usuarios";
$usuarios_resultado = $conexion->query($usuarios_query);
$usuarios = $usuarios_resultado->fetch_all(MYSQLI_ASSOC);

// Obtener lista de rutas
$rutas_query = "SELECT id_ruta, nombre_ruta FROM Rutas";
$rutas_resultado = $conexion->query($rutas_query);
$rutas = $rutas_resultado->fetch_all(MYSQLI_ASSOC);

// Calcular el total general
$total_general = array_sum(array_column($creditos, 'saldo_actual'));
?>

<div class="container mt-4">
    <h2 class="mb-4"><i class="bi bi-filter-circle me-2"></i> Reporte General con Filtros</h2>

    <!-- Formulario de Filtros -->
    <form method="POST" class="row g-3 mb-4">
        <div class="col-md-3">
            <label for="fecha_inicio" class="form-label"><i class="bi bi-calendar"></i> Fecha Inicial</label>
            <input type="date" class="form-control" name="fecha_inicio" value="<?= htmlspecialchars($filtro_fecha_inicio) ?>">
        </div>
        <div class="col-md-3">
            <label for="fecha_fin" class="form-label"><i class="bi bi-calendar"></i> Fecha Final</label>
            <input type="date" class="form-control" name="fecha_fin" value="<?= htmlspecialchars($filtro_fecha_fin) ?>">
        </div>
        <div class="col-md-3">
            <label for="usuario" class="form-label"><i class="bi bi-person-circle"></i> Cobrador</label>
            <select class="form-select" name="usuario">
                <option value="">Todos</option>
                <?php foreach ($usuarios as $usuario): ?>
                    <option value="<?= htmlspecialchars($usuario['id_usuario']) ?>" <?= ($usuario['id_usuario'] == $filtro_usuario) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($usuario['nombre_completo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="ruta" class="form-label"><i class="bi bi-geo-alt"></i> Ruta</label>
            <select class="form-select" name="ruta">
                <option value="">Todas</option>
                <?php foreach ($rutas as $ruta): ?>
                    <option value="<?= htmlspecialchars($ruta['id_ruta']) ?>" <?= ($ruta['id_ruta'] == $filtro_ruta) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ruta['nombre_ruta']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-search"></i> Aplicar Filtros
            </button>
        </div>
    </form>

    <!-- Mostrar el Reporte General -->
    <?php if (!empty($creditos)): ?>
        <div class="card shadow">
            <div class="card-body">
                <h4 class="card-title">Detalle de Créditos</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>ID Crédito</th>
                                <th>Cliente</th>
                                <th>Cobrador</th>
                                <th>Ruta</th>
                                <th>Saldo Actual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($creditos as $credito): ?>
                                <tr>
                                    <td><?= htmlspecialchars($credito['id_credito']) ?></td>
                                    <td><?= htmlspecialchars($credito['nombres'] . ' ' . $credito['apellidos']) ?></td>
                                    <td><?= htmlspecialchars($credito['cobrador']) ?></td>
                                    <td><?= htmlspecialchars($credito['nombre_ruta']) ?></td>
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
    <?php else: ?>
        <div class="alert alert-warning">No hay registros que coincidan con los filtros seleccionados.</div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>