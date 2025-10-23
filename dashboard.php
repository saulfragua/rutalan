<?php 
include 'includes/header.php';
include 'includes/permisos.php'; // Incluir el archivo de permisos
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}
?>

<div class="container mt-5">
    <div class="alert alert-info">
        <i class="bi bi-person-circle"></i> Bienvenido, <?php echo $_SESSION['nombre']; ?> (Rol: <?php echo $_SESSION['rol']; ?>)
    </div>
    
    <div class="card shadow-lg">
        <div class="card-body">
            <h4 class="text-primary"><i class="bi bi-house-door"></i> Menú Principal</h4>
            <div class="list-group">

                <!-- Gestión de Clientes -->
                <a href="clientes.php" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="bi bi-person-badge me-2"></i> Gestión de Clientes
                </a>

                <!-- Gestión de Créditos -->
                <a href="creditos.php" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="bi bi-cash-coin me-2"></i> Gestión de Créditos
                </a>

                <!-- Gestión de Pagos -->
                <a href="cobranza.php" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="bi bi-receipt me-2"></i> Gestión de Pagos
                </a>

                <!-- Registro de Gastos -->
                <a href="gestion_gasto.php" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="bi bi-coin me-2"></i> Registro de Gastos
                </a>

                <!-- Gestión de Reportes y Análisis -->
                <?php if (tienePermiso($_SESSION['rol'], 'reportes', 'ver')): ?>
                    <a href="reportes.php" class="list-group-item list-group-item-action d-flex align-items-center">
                        <i class="bi bi-bar-chart me-2"></i> Gestión de Administración y Reportes 
                    </a>
                <?php endif; ?>

                <!-- Cerrar Sesión -->
                <a href="logout.php" class="list-group-item list-group-item-action text-danger d-flex align-items-center">
                    <i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>