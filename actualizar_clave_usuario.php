<?php
include 'includes/header.php';
include 'includes/conexion.php';
include 'includes/permisos.php';

// Iniciar sesión solo una vez
session_start();

$rol = $_SESSION['rol'];

// Verificar si el usuario tiene permiso para ver esta página
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['admin', 'cobrador', 'consultor'])) {
    header("Location: login.php");
    exit();
}

$mensaje = ""; // Variable para mostrar mensajes al usuario

// Obtener el ID del usuario a actualizar
$id_usuario = $_GET['id'] ?? null;
if (!$id_usuario) {
    header("Location: registro.php");
    exit();
}

// Procesar el formulario de actualización de clave
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nueva_clave = $_POST['nueva_clave'];
    $confirmar_clave = $_POST['confirmar_clave'];

    // Validar que las nuevas claves coincidan
    if ($nueva_clave !== $confirmar_clave) {
        $mensaje = "<div class='alert alert-danger'>Las nuevas claves no coinciden.</div>";
    } else {
        // Validar que la nueva clave tenga al menos 8 caracteres
        if (strlen($nueva_clave) < 8) {
            $mensaje = "<div class='alert alert-danger'>La nueva clave debe tener al menos 8 caracteres.</div>";
        } else {
            // Actualizar la clave en la base de datos
            $nueva_clave_hash = password_hash($nueva_clave, PASSWORD_DEFAULT);
            $sql = "UPDATE usuarios SET clave = ? WHERE id_usuario = ?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("si", $nueva_clave_hash, $id_usuario);

            if ($stmt->execute()) {
                $mensaje = "<div class='alert alert-success'>Clave actualizada correctamente.</div>";
            } else {
                $mensaje = "<div class='alert alert-danger'>Error al actualizar la clave. Inténtalo de nuevo.</div>";
            }
        }
    }
}
?>

<div class="container mt-5">
    <h2><i class="bi bi-key"></i> Actualizar Clave de Usuario</h2>
    <?php echo $mensaje; ?>
    <form method="POST">
        <div class="mb-3">
            <label for="nueva_clave" class="form-label">Nueva Clave</label>
            <input type="password" class="form-control" id="nueva_clave" name="nueva_clave" required minlength="8">
            <small class="form-text text-muted">La clave debe tener al menos 8 caracteres.</small>
        </div>
        <div class="mb-3">
            <label for="confirmar_clave" class="form-label">Confirmar Nueva Clave</label>
            <input type="password" class="form-control" id="confirmar_clave" name="confirmar_clave" required minlength="8">
        </div>
        <button type="submit" class="btn btn-primary">Actualizar Clave</button>
        <a href="actualiza_usuario.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<?php include 'includes/footer.php'; ?>