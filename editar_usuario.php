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

// Obtener el ID del usuario a editar
$id_usuario = $_GET['id'] ?? null;
if (!$id_usuario) {
    header("Location: registro.php");
    exit();
}

// Obtener datos del usuario
$sql = "SELECT * FROM usuarios WHERE id_usuario = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

// Procesar formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $rol = $_POST['rol'];

    // Actualizar usuario en la base de datos
    $sql = "UPDATE usuarios SET nombre_completo = ?, email = ?, rol = ? WHERE id_usuario = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("sssi", $nombre, $email, $rol, $id_usuario);
    $stmt->execute();

    header("Location: actualiza_usuario.php");
    exit();
}
?>

<div class="container mt-5">
    <h2><i class="bi bi-pencil"></i> Editar Usuario</h2>
    <form method="POST">
        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre</label>
            <input type="text" class="form-control" id="nombre" name="nombre" value="<?= $usuario['nombre_completo'] ?>" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email" value="<?= $usuario['email'] ?>" required>
        </div>
        <div class="mb-3">
            <label for="rol" class="form-label">Rol</label>
            <select class="form-select" id="rol" name="rol" required>
                <option value="admin" <?= $usuario['rol'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="cobrador" <?= $usuario['rol'] === 'cobrador' ? 'selected' : '' ?>>Cobrador</option>
                <option value="consultor" <?= $usuario['rol'] === 'consultor' ? 'selected' : '' ?>>Consultor</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>