<?php
include 'includes/header.php';
include 'includes/conexion.php';
include 'includes/permisos.php'; 

date_default_timezone_set('America/Bogota');
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es');

// Iniciar sesión y verificar autenticación
session_start();

// Obtener el rol del usuario actual
$rol = $_SESSION['rol'];

// Verificar si el usuario tiene permiso para ver esta página
if (!tienePermiso($rol, 'reportes', 'ver')) {
    echo "<div class='alert alert-danger'>No tienes permiso para acceder a esta página.</div>";
    include 'includes/footer.php';
    exit();
}
?>

<div class="container mt-4">
    <h2 class="mb-4 text-center"><i class="bi bi-file-earmark-text me-2"></i> Reportes</h2>

    <!-- Contenedor de botones con diseño mejorado -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <!-- Botón para Reporte General (solo si tiene permiso) -->
        <?php if (tienePermiso($rol, 'reportes', 'ver_general')): ?>
            <div class="col">
                <a href="reportegeneral.php" class="card h-100 text-decoration-none text-white bg-primary hover-effect">
                    <div class="card-body text-center">
                        <i class="bi bi-graph-up fs-1 mb-3"></i>
                        <h5 class="card-title">Reporte General</h5>
                        <p class="card-text">Resumen completo de actividades.</p>
                    </div>
                </a>
            </div>
        <?php endif; ?>

        <!-- Botón para Cuadre de Caja (solo si tiene permiso) -->
        <?php if (tienePermiso($rol, 'reportes', 'ver_cuadre_caja')): ?>
            <div class="col">
                <a href="caja.php" class="card h-100 text-decoration-none text-white bg-success hover-effect">
                    <div class="card-body text-center">
                        <i class="bi bi-cash-stack fs-1 mb-3"></i>
                        <h5 class="card-title">Cuadre de Caja</h5>
                        <p class="card-text">Balance de ingresos y egresos.</p>
                    </div>
                </a>
            </div>
        <?php endif; ?>

        <!-- Botón para Cuadres de Caja (nuevo) -->
        <?php if (tienePermiso($rol, 'reportes', 'ver_cuadres_caja')): ?>
            <div class="col">
                <a href="cuadres_caja.php" class="card h-100 text-decoration-none text-white bg-info hover-effect">
                    <div class="card-body text-center">
                        <i class="bi bi-journal-bookmark fs-1 mb-3"></i>
                        <h5 class="card-title">Cuadres de Caja</h5>
                        <p class="card-text">Historial de cierres de caja.</p>
                    </div>
                </a>
            </div>
        <?php endif; ?>

        <!-- Botón para Reporte de Gastos (solo si tiene permiso) -->
        <?php if (tienePermiso($rol, 'reportes', 'ver_gastos')): ?>
            <div class="col">
                <a href="gastos.php" class="card h-100 text-decoration-none text-dark bg-warning hover-effect">
                    <div class="card-body text-center">
                        <i class="bi bi-coin fs-1 mb-3"></i>
                        <h5 class="card-title">Reporte de Gastos</h5>
                        <p class="card-text">Registro y análisis de gastos.</p>
                    </div>
                </a>
            </div>
        <?php endif; ?>

        <!-- Botón para Adelanto Caja (solo si tiene permiso) -->
        <?php if (tienePermiso($rol, 'reportes', 'ver_adelanto_caja')): ?>
            <div class="col">
                <a href="adelantos.php" class="card h-100 text-decoration-none text-white bg-info hover-effect">
                    <div class="card-body text-center">
                        <i class="bi bi-wallet2 fs-1 mb-3"></i>
                        <h5 class="card-title">Ingresos y Egresos de Caja</h5>
                        <p class="card-text">Gestión de adelantos de efectivo.</p>
                    </div>
                </a>
            </div>
        <?php endif; ?>

        <!-- Botón para Listado de Pagos (solo si tiene permiso) -->
        <?php if (tienePermiso($rol, 'reportes', 'ver_listado_pagos')): ?>
            <div class="col">
                <a href="lista_cobros.php" class="card h-100 text-decoration-none text-white bg-secondary hover-effect">
                    <div class="card-body text-center">
                        <i class="bi bi-list-check fs-1 mb-3"></i>
                        <h5 class="card-title">Listado de Pagos</h5>
                        <p class="card-text">Detalle de pagos realizados.</p>
                    </div>
                </a>
            </div>
        <?php endif; ?>

        <!-- Botón para Listado de Créditos (solo si tiene permiso) -->
        <?php if (tienePermiso($rol, 'reportes', 'ver_listado_creditos')): ?>
            <div class="col">
                <a href="lista_creditos.php" class="card h-100 text-decoration-none text-white bg-danger hover-effect">
                    <div class="card-body text-center">
                        <i class="bi bi-file-earmark-text fs-1 mb-3"></i>
                        <h5 class="card-title">Listado de Créditos</h5>
                        <p class="card-text">Detalle de créditos otorgados.</p>
                    </div>
                </a>
            </div>
        <?php endif; ?>

        <!-- Botón para Gestión de Usuarios (solo si tiene permiso) -->
        <?php if (tienePermiso($rol, 'reportes', 'ver_actualiza_usuario')): ?>
            <div class="col">
                <a href="actualiza_usuario.php" class="card h-100 text-decoration-none text-white bg-dark hover-effect">
                    <div class="card-body text-center">
                        <i class="bi bi-people-fill fs-1 mb-3"></i>
                        <h5 class="card-title">Gestión de Usuarios</h5>
                        <p class="card-text">Administración de usuarios del sistema.</p>
                    </div>
                </a>
            </div>
        <?php endif; ?>

        <!-- Botón para Gestión de Rutas (solo si tiene permiso) -->
        <?php if (tienePermiso($rol, 'reportes', 'ver_rutas')): ?>
            <div class="col">
                <a href="rutas.php" class="card h-100 text-decoration-none text-dark bg-light hover-effect">
                    <div class="card-body text-center">
                        <i class="bi bi-arrow-left-right fs-1 mb-3"></i>
                        <h5 class="card-title">Gestión de Rutas</h5>
                        <p class="card-text">Administración de rutas.</p>
                    </div>
                </a>
            </div>
        <?php endif; ?>
             <!-- Botón para QR (solo si tiene permiso) -->
        <?php if (tienePermiso($rol, 'reportes', 'ver_qr')): ?>
    <div class="col">
        <a href="ver_qr.php" class="card h-100 text-decoration-none text-dark bg-light hover-effect">
            <div class="card-body text-center">
                <i class="bi bi-qr-code-scan fs-1 mb-3"></i>
                <h5 class="card-title">Escanear QR</h5>
                <p class="card-text">Escanea el QR para conectar y enviar mensajes.</p>
            </div>
        </a>
    </div>
<?php endif; ?>

    </div>

    <!-- Botón para volver al menú principal -->
    <div class="mt-4 text-center">
        <a href="dashboard.php" class="btn btn-outline-secondary btn-lg">
            <i class="bi bi-house-door me-2"></i> Volver al Menú Principal
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>