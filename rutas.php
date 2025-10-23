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

// Obtener todas las rutas y contar los clientes asociados
$sql = "SELECT r.*, 
               (SELECT COUNT(*) FROM clientes c WHERE c.id_ruta = r.id_ruta) AS total_clientes
        FROM rutas r";
$result = $conexion->query($sql);
?>

<div class="container mt-5">
    <h2><i class="bi bi-map me-2"></i>Gestión de Rutas de Cobro</h2>
    
    <!-- Botón para crear nueva ruta -->
    <a href="gestion_ruta.php?accion=crear" class="btn btn-primary mb-3">
        <i class="bi bi-plus-circle"></i> Crear Nueva Ruta
    </a>
    <!-- Botón para volver al menú principal -->
    <a href="reportes.php" class="btn btn-secondary mb-3">
        <i class="bi bi-arrow-left-circle"></i> Volver a Reportes
    </a>
    <!-- Tabla de rutas -->
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Nombre de Ruta</th>
                <th>Clientes Asociados</th> <!-- Nueva columna -->
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($ruta = $result->fetch_assoc()): ?>
                <tr>
                    <td>
                        <i class="bi bi-card-list"></i> <?php echo $ruta['id_ruta']; ?>
                    </td>
                    <td>
                        <i class="bi bi-map"></i> <?php echo $ruta['nombre_ruta']; ?>
                    </td>
                    <td>
                        <i class="bi bi-people"></i> <?php echo $ruta['total_clientes']; ?> clientes
                    </td>
                    <td>
                        <i class="bi <?php echo $ruta['activo'] ? 'bi-check-circle' : 'bi-x-circle'; ?>"></i> 
                        <?php echo $ruta['activo'] ? 'Activo' : 'Inactivo'; ?>
                    </td>
                    <td>
                        <a href="gestion_ruta.php?accion=editar&id=<?php echo $ruta['id_ruta']; ?>" class="btn btn-warning btn-sm">
                            <i class="bi bi-pencil"></i> Editar
                        </a>
                        <?php if ($ruta['activo']): ?>
                            <a href="gestion_ruta.php?accion=inactivar&id=<?php echo $ruta['id_ruta']; ?>" class="btn btn-danger btn-sm">
                                <i class="bi bi-x-circle"></i> Inactivar
                            </a>
                        <?php else: ?>
                            <a href="gestion_ruta.php?accion=activar&id=<?php echo $ruta['id_ruta']; ?>" class="btn btn-success btn-sm">
                                <i class="bi bi-check-circle"></i> Activar
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>