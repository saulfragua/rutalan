<?php
// Iniciar sesión primero para tener acceso al rol
session_start();

// Incluir archivos necesarios
require 'includes/conexion2.php'; // Cambiado a conexion.php ya que es el que se usa después
require 'includes/header.php';
require 'includes/permisos.php';

// Verificar permisos
$rol = $_SESSION['rol'] ?? null;

if (!tienePermiso($rol, 'reportes', 'ver_cuadres_caja')) {
    echo "<div class='alert alert-danger'>No tienes permiso para acceder a esta página.</div>";
    include 'includes/footer.php';
    exit();
}

// Configuración de fechas
date_default_timezone_set('America/Bogota');
$fechaInicio = date('Y-m-01');
$fechaFin = date('Y-m-t');
$idUsuario = '';

// Inicializar variables
$cierres = [];
$usuarios = [];
$error = '';

try {
    // Conexión a la base de datos
    // Verificar si la conexión se estableció correctamente
    if (!$pdo) {
        throw new Exception("No se pudo establecer conexión con la base de datos");
    }

    // Procesar filtros si se envió el formulario
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $fechaInicio = $_POST['fecha_inicio'] ?? $fechaInicio;
        $fechaFin = $_POST['fecha_fin'] ?? $fechaFin;
        $idUsuario = $_POST['id_usuario'] ?? '';
    }

    // Consulta SQL con parámetros seguros
    $sql = "SELECT rd.*, u.nombre_completo as nombre_usuario 
            FROM reportes_diarios rd 
            LEFT JOIN usuarios u ON rd.id_usuario = u.id_usuario 
            WHERE rd.fecha BETWEEN :fecha_inicio AND :fecha_fin";

    $params = [
        ':fecha_inicio' => $fechaInicio,
        ':fecha_fin' => $fechaFin
    ];

    // Añadir filtro por usuario si existe
    if (!empty($idUsuario)) {
        $sql .= " AND rd.id_usuario = :id_usuario";
        $params[':id_usuario'] = $idUsuario;
    }

    $sql .= " ORDER BY rd.fecha DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cierres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener lista de usuarios para el filtro
    $usuarios = $pdo->query("SELECT id_usuario, nombre_completo FROM usuarios ORDER BY nombre_completo")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Error en la consulta: " . $e->getMessage();
    error_log($error);
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
    error_log($error);
}

// Función para formatear fechas
function formatFecha($fecha) {
    return date('d/m/Y', strtotime($fecha));
}
?>

<div class="container mt-4">
    <h2 class="mb-4"><i class="bi bi-journal-bookmark me-2"></i> Cuadres de Caja</h2>
    
    <!-- Mostrar errores si existen -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-funnel me-2"></i> Filtros
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-3">
                    <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                           value="<?= htmlspecialchars($fechaInicio) ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                           value="<?= htmlspecialchars($fechaFin) ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="id_usuario" class="form-label">Usuario</label>
                    <select class="form-select" id="id_usuario" name="id_usuario">
                        <option value="">Todos los usuarios</option>
                        <?php foreach ($usuarios as $usuario): ?>
                            <option value="<?= htmlspecialchars($usuario['id_usuario']) ?>" 
                                <?= ($idUsuario == $usuario['id_usuario']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($usuario['nombre_completo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-filter me-1"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Resultados -->
    <div class="card">
        <div class="card-header bg-success text-white">
            <i class="bi bi-list-check me-2"></i> Historial de Cierres
        </div>
        <div class="card-body">
            <?php if (empty($cierres) && empty($error)): ?>
                <div class="alert alert-info">No se encontraron cierres de caja con los filtros seleccionados.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Fecha Cierre</th>
                                <th>Usuario</th>
                                <th class="text-end">Valor Cierre</th>
                                <th>Observaciones</th>
                                <th>Fecha Registro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cierres as $cierre): ?>
                                <tr>
                                    <td><?= formatFecha($cierre['fecha']) ?></td>
                                    <td><?= htmlspecialchars($cierre['nombre_usuario'] ?? 'N/A') ?></td>
                                    <td class="text-end">$ <?= number_format($cierre['cierre_caja'], 2, ',', '.') ?></td>
                                    <td><?= !empty($cierre['observaciones']) ? htmlspecialchars($cierre['observaciones']) : 'Ninguna' ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($cierre['fecha_registro'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Botón para volver -->
    <div class="mt-4">
        <a href="reportes.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i> Volver a Reportes
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>