<?php
include 'includes/header.php';
include 'includes/conexion.php';

session_start();
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['admin', 'cobrador'])) {
    header("Location: login.php");
    exit();
}

// Obtener todos los clientes
$sql = "SELECT c.*, r.nombre_ruta FROM clientes c 
        LEFT JOIN rutas r ON c.id_ruta = r.id_ruta";
$result = $conexion->query($sql);
?>

<div class="container mt-5">
    <h2><i class="bi bi-people-fill"></i> Gestión de Clientes</h2>

    <!-- Barra de búsqueda -->
    <div class="input-group mb-3">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" class="form-control" id="busqueda" placeholder="Buscar cliente...">
    </div>

    <!-- Botón para crear nuevo cliente -->
    <a href="gestion_cliente.php?accion=crear" class="btn btn-primary mb-3">
        <i class="bi bi-person-plus-fill"></i> Crear Nuevo Cliente
    </a>

    <!-- Botón para volver al menú principal -->
    <a href="dashboard.php" class="btn btn-secondary mb-3">
        <i class="bi bi-arrow-left-circle"></i> Volver al Menú Principal
    </a>

    <!-- Tabla de clientes -->
    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th><i class="bi bi-image"></i> Foto</th>
                <th><i class="bi bi-hash"></i> ID</th>
                <th><i class="bi bi-card-list"></i> Documento</th>
                <th><i class="bi bi-person"></i> Nombres</th>
                <th><i class="bi bi-person"></i> Apellido</th>
                <th><i class="bi bi-geo-alt"></i> Dirección</th>
                <th><i class="bi bi-telephone"></i> Teléfono</th>
                <th><i class="bi bi-telephone"></i> Teléfono 2</th>
                <th><i class="bi bi-map"></i> Ruta</th>
                <th><i class="bi bi-check-circle"></i> Estado</th>
                <th><i class="bi bi-gear"></i> Acciones</th>
            </tr>
        </thead>
        <tbody id="tablaClientes">
            <?php while ($cliente = $result->fetch_assoc()): ?>
                <tr>
                    <td>
                        <?php if (!empty($cliente['foto_cliente'])): ?>
                            <img src="<?php echo $cliente['foto_cliente']; ?>" alt="Foto del cliente" width="80" height="80" style="object-fit: cover;">
                        <?php else: ?>
                            <span>Sin foto</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $cliente['id_cliente']; ?></td>
                    <td><?php echo $cliente['documento']; ?></td>
                    <td><?php echo $cliente['nombres']; ?></td>
                    <td><?php echo $cliente['apellidos']; ?></td>
                    <td><?php echo $cliente['direccion']; ?></td>
                    <td><?php echo $cliente['telefono']; ?></td>
                    <td><?php echo $cliente['telefono2']; ?></td>
                    <td><?php echo $cliente['nombre_ruta'] ?? 'Sin ruta'; ?></td>
                    <td>
                        <span class="badge <?php echo $cliente['activo'] ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo $cliente['activo'] ? 'Activo' : 'Inactivo'; ?>
                        </span>
                    </td>
                    <td>
                        <a href="gestion_cliente.php?accion=editar&id=<?php echo $cliente['id_cliente']; ?>" 
                           class="btn btn-warning btn-sm">
                            <i class="bi bi-pencil-square"></i> Editar
                        </a>
                        <?php if ($cliente['activo']): ?>
                            <a href="gestion_cliente.php?accion=inactivar&id=<?php echo $cliente['id_cliente']; ?>" 
                               class="btn btn-danger btn-sm">
                                <i class="bi bi-x-circle"></i> Inactivar
                            </a>
                        <?php else: ?>
                            <a href="gestion_cliente.php?accion=activar&id=<?php echo $cliente['id_cliente']; ?>" 
                               class="btn btn-success btn-sm">
                                <i class="bi bi-check-circle"></i> Activar
                            </a>
                        <?php endif; ?>
                        <!-- Botón de Historial -->
                        <a href="historialcliente.php?id=<?php echo $cliente['id_cliente']; ?>" 
                           class="btn btn-info btn-sm">
                            <i class="bi bi-clock-history"></i> Historial
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
    // Filtro de búsqueda en la tabla
    document.getElementById('busqueda').addEventListener('keyup', function() {
        let filtro = this.value.toLowerCase();
        let filas = document.querySelectorAll('#tablaClientes tr');
        filas.forEach(fila => {
            let texto = fila.innerText.toLowerCase();
            fila.style.display = texto.includes(filtro) ? '' : 'none';
        });
    });
</script>

<?php include 'includes/footer.php'; ?>