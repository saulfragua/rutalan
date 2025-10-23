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
$search = $_GET['search'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$id_usuario = $_GET['id_usuario'] ?? '';

// Obtener lista de usuarios para el select (solo para admin)
$usuarios = [];
if ($_SESSION['rol'] == 'admin') {
    $queryUsuarios = "SELECT id_usuario, nombre_completo FROM usuarios WHERE estado = 'activo'";
    $resultUsuarios = $conexion->query($queryUsuarios);
    while ($usuario = $resultUsuarios->fetch_assoc()) {
        $usuarios[] = $usuario;
    }
}

// Consulta SQL base
$sql = "SELECT g.*, r.nombre_ruta, u.nombre_completo 
        FROM gastos g
        JOIN rutas r ON g.id_ruta = r.id_ruta
        JOIN usuarios u ON g.id_usuario = u.id_usuario
        WHERE 1=1";

// Aplicar filtros
if (!empty($search)) {
    $sql .= " AND (g.descripcion LIKE '%$search%' 
              OR r.nombre_ruta LIKE '%$search%' 
              OR u.nombre_completo LIKE '%$search%' 
              OR g.monto LIKE '%$search%')";
}

if (!empty($fecha_inicio) && !empty($fecha_fin)) {
    $sql .= " AND g.fecha_gasto BETWEEN '$fecha_inicio' AND '$fecha_fin'";
}

if (!empty($id_usuario)) {
    $sql .= " AND g.id_usuario = '$id_usuario'";
}

// Si el usuario no es admin, solo mostrar sus propios gastos
if ($_SESSION['rol'] != 'admin') {
    $sql .= " AND g.id_usuario = '".$_SESSION['id_usuario']."'";
}

$result = $conexion->query($sql);
$total_gastos = 0;
$gastos_data = [];

if ($result->num_rows > 0) {
    $gastos_data = $result->fetch_all(MYSQLI_ASSOC);
    $total_gastos = array_sum(array_column($gastos_data, 'monto'));
}
?>

<div class="container mt-5">
    <h2><i class="bi bi-coin me-2"></i>Gestión de Gastos de Rutas</h2>
    
    <!-- Formulario de Filtros -->
    <form method="GET" action="" class="mb-4">
        <div class="row g-3">
            <!-- Barra de búsqueda -->
            <div class="col-md-4">
                <label class="form-label">Buscar:</label>
                <input type="text" name="search" class="form-control" 
                       placeholder="Descripción, ruta, monto..." value="<?= htmlspecialchars($search) ?>">
            </div>
            
            <!-- Filtro por fechas -->
            <div class="col-md-2">
                <label class="form-label">Fecha Inicio:</label>
                <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fecha_inicio) ?>">
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Fecha Fin:</label>
                <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fecha_fin) ?>">
            </div>
            
            <!-- Filtro por usuario (solo para admin) -->
            <?php if ($_SESSION['rol'] === 'admin' && !empty($usuarios)): ?>
            <div class="col-md-2">
                <label class="form-label">Registrado por:</label>
                <select name="id_usuario" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $usuario): ?>
                        <option value="<?= $usuario['id_usuario'] ?>" <?= $id_usuario == $usuario['id_usuario'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($usuario['nombre_completo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-filter"></i> Filtrar
                </button>
                <a href="gastos.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </div>
    </form>

    <div class="d-flex mb-3">
        <a href="gestion_gasto.php?accion=crear" class="btn btn-primary me-2">
            <i class="bi bi-plus-circle"></i> Nuevo Gasto
        </a>
        <a href="reportes.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left-circle"></i> Volver a Reportes
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-striped">
                <thead class="bg-dark text-white">
                    <tr>
                        <th><i class="bi bi-calendar"></i> Fecha</th>
                        <?php if ($_SESSION['rol'] === 'admin'): ?>
                            <th><i class="bi bi-clock"></i> Hora</th>
                        <?php endif; ?>
                        <th><i class="bi bi-map"></i> Ruta</th>
                        <th><i class="bi bi-info-circle"></i> Descripción</th>
                        <th><i class="bi bi-currency-dollar"></i> Monto</th>
                        <th><i class="bi bi-person"></i> Registrado por</th>
                        <th><i class="bi bi-gear"></i> Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($gastos_data)): ?>
                        <?php foreach($gastos_data as $gasto): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($gasto['fecha_gasto'])) ?></td>
                                <?php if ($_SESSION['rol'] === 'admin'): ?>
                                    <td><?= date('H:i:s', strtotime($gasto['hora_gasto'])) ?></td>
                                <?php endif; ?>
                                <td><?= htmlspecialchars($gasto['nombre_ruta']) ?></td>
                                <td><?= htmlspecialchars($gasto['descripcion']) ?></td>
                                <td>$<?= number_format($gasto['monto'], 2) ?></td>
                                <td><?= htmlspecialchars($gasto['nombre_completo']) ?></td>
                                <td>
                                    <a href="gestion_gasto.php?accion=editar&id=<?= $gasto['id_gasto'] ?>" class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <a href="gestion_gasto.php?accion=eliminar&id=<?= $gasto['id_gasto'] ?>" class="btn btn-sm btn-danger">
                                        <i class="bi bi-trash"></i> Eliminar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $_SESSION['rol'] === 'admin' ? 7 : 6 ?>" class="text-center">No se encontraron resultados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="mt-3 fw-bold">
                Total Gastos: $<?= number_format($total_gastos, 2) ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>