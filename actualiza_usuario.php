<?php
include 'includes/header.php';
include 'includes/conexion.php';
include 'includes/permisos.php';

session_start();

// Verificar permisos
if (!tienePermiso($_SESSION['rol'], 'reportes', 'ver_actualiza_usuario')) {
    header("Location: dashboard.php");
    exit();
}

// Procesar formulario de creación de usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crear_usuario'])) {
        $nombre = $_POST['nombre'];
        $nombre_usuario = $_POST['nombre_usuario'];
        $email = $_POST['email'];
        $rol = $_POST['rol'];
        $clave = password_hash($_POST['clave'], PASSWORD_DEFAULT); // Hashear la clave

        // Insertar usuario en la base de datos
        $sql = "INSERT INTO usuarios (nombre_completo, nombre_usuario, email, rol, clave) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("sssss", $nombre, $nombre_usuario, $email, $rol, $clave);
        if (!$stmt->execute()) {
            echo "<div class='alert alert-danger'>Error al crear el usuario: " . $conexion->error . "</div>";
        }
    }

    // Procesar activar/desactivar usuario
    if (isset($_POST['cambiar_estado'])) {
        $id_usuario = $_POST['id_usuario'];
        $estado_actual = $_POST['estado_actual'];

        // Cambiar el estado del usuario
        $nuevo_estado = $estado_actual ? 0 : 1; // Alternar entre activo e inactivo
        $sql = "UPDATE usuarios SET estado = ? WHERE id_usuario = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ii", $nuevo_estado, $id_usuario);
        if (!$stmt->execute()) {
            echo "<div class='alert alert-danger'>Error al cambiar el estado del usuario: " . $conexion->error . "</div>";
        }
    }

    // Procesar asignación de ruta
    if (isset($_POST['asignar_ruta'])) {
        $id_usuario = $_POST['id_usuario'];
        $id_ruta = $_POST['id_ruta'];

        // Asignar ruta al usuario
        $sql = "UPDATE usuarios SET id_ruta = ? WHERE id_usuario = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ii", $id_ruta, $id_usuario);
        if (!$stmt->execute()) {
            echo "<div class='alert alert-danger'>Error al asignar la ruta: " . $conexion->error . "</div>";
        }
    }
}

// Obtener lista de usuarios
$sql_usuarios = "SELECT * FROM usuarios";
$usuarios = $conexion->query($sql_usuarios);

// Obtener la lista de rutas activas
$sql_rutas = "SELECT * FROM rutas WHERE activo = 1";
$resultado_rutas = $conexion->query($sql_rutas);
$rutas = [];
while ($ruta = $resultado_rutas->fetch_assoc()) {
    $rutas[$ruta['id_ruta']] = $ruta['nombre_ruta'];
}
?>

<div class="container mt-5">
    <h2><i class="bi bi-people"></i> Gestión de Usuarios</h2>

    <!-- Formulario para crear usuario -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Crear Nuevo Usuario</h5>
            <form method="POST" id="formUsuario">
                <div class="mb-3">
                    <label for="nombre" class="form-label">Nombre Completo</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                </div>
                <div class="mb-3">
                    <label for="nombre_usuario" class="form-label">Usuario</label>
                    <input type="text" class="form-control" id="nombre_usuario" name="nombre_usuario" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="clave" class="form-label">Clave</label>
                    <input type="password" class="form-control" id="clave" name="clave" required minlength="8">
                    <small class="form-text text-muted">La clave debe tener al menos 8 caracteres.</small>
                </div>
                <div class="mb-3">
                    <label for="rol" class="form-label">Rol</label>
                    <select class="form-select" id="rol" name="rol" required>
                        <option value="admin">Admin</option>
                        <option value="cobrador">Cobrador</option>
                        <option value="consultor">Consultor</option>
                    </select>
                </div>
                <button type="submit" name="crear_usuario" class="btn btn-primary">Crear Usuario</button>
                <a href="reportes.php" class="btn btn-secondary">Volver a Reportes</a>
            </form>
        </div>
    </div>

    <!-- Lista de usuarios -->
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Lista de Usuarios</h5>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Ruta Asignada</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($usuario = $usuarios->fetch_assoc()): ?>
                        <tr>
                            <td><?= $usuario['id_usuario'] ?></td>
                            <td><?= $usuario['nombre_completo'] ?></td>
                            <td><?= $usuario['nombre_usuario'] ?></td>
                            <td><?= $usuario['email'] ?></td>
                            <td><?= $usuario['rol'] ?></td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="id_usuario" value="<?= $usuario['id_usuario'] ?>">
                                    <select name="id_ruta" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="">Seleccionar ruta</option>
                                        <?php foreach ($rutas as $id_ruta => $nombre_ruta): ?>
                                            <option value="<?= $id_ruta ?>" <?= $id_ruta == $usuario['id_ruta'] ? 'selected' : '' ?>>
                                                <?= $nombre_ruta ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="asignar_ruta">
                                </form>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="id_usuario" value="<?= $usuario['id_usuario'] ?>">
                                    <input type="hidden" name="estado_actual" value="<?= $usuario['estado'] ?>">
                                    <button type="submit" name="cambiar_estado" class="btn btn-sm <?= $usuario['estado'] ? 'btn-success' : 'btn-danger' ?>">
                                        <?= $usuario['estado'] ? 'Activo' : 'Inactivo' ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <a href="editar_usuario.php?id=<?= $usuario['id_usuario'] ?>" class="btn btn-sm btn-warning">Editar</a>
                                <a href="eliminar_usuario.php?id=<?= $usuario['id_usuario'] ?>" class="btn btn-sm btn-danger">Eliminar</a>
                                <a href="actualizar_clave_usuario.php?id=<?= $usuario['id_usuario'] ?>" class="btn btn-sm btn-info">Actualizar Clave</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Validar el formulario solo cuando se hace clic en "Crear Usuario"
document.getElementById('formUsuario').addEventListener('submit', function(event) {
    const botonCrear = document.querySelector('button[name="crear_usuario"]');
    if (event.submitter === botonCrear) {
        const nombre = document.getElementById('nombre').value.trim();
        const nombreUsuario = document.getElementById('nombre_usuario').value.trim();
        const email = document.getElementById('email').value.trim();
        const clave = document.getElementById('clave').value.trim();
        const rol = document.getElementById('rol').value;

        if (!nombre || !nombreUsuario || !email || !clave || !rol) {
            alert('Todos los campos son obligatorios.');
            event.preventDefault(); // Evitar envío del formulario
        } else if (clave.length < 8) {
            alert('La clave debe tener al menos 8 caracteres.');
            event.preventDefault(); // Evitar envío del formulario
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>