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

$accion = $_GET['accion'] ?? 'crear';
$id_ruta = $_GET['id'] ?? null;

// Obtener datos de la ruta si se está editando
if ($accion === 'editar' && $id_ruta) {
    $sql = "SELECT * FROM rutas WHERE id_ruta = ?";
    $stmt = $conexion->prepare($sql);
    if ($stmt === false) {
        die("Error en prepare(): " . $conexion->error);
    }
    $stmt->bind_param("i", $id_ruta);
    $stmt->execute();
    $result = $stmt->get_result();
    $ruta = $result->fetch_assoc();
    $stmt->close();
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_ruta = $_POST['nombre_ruta'];
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($accion === 'crear') {
        // Crear nueva ruta
        $sql = "INSERT INTO rutas (nombre_ruta, activo) VALUES (?, ?)";
        $stmt = $conexion->prepare($sql);
        if ($stmt === false) {
            die("Error en prepare(): " . $conexion->error);
        }
        $stmt->bind_param("si", $nombre_ruta, $activo);
    } elseif ($accion === 'editar' && $id_ruta) {
        // Editar ruta existente
        $sql = "UPDATE rutas SET nombre_ruta = ?, activo = ? WHERE id_ruta = ?";
        $stmt = $conexion->prepare($sql);
        if ($stmt === false) {
            die("Error en prepare(): " . $conexion->error);
        }
        $stmt->bind_param("sii", $nombre_ruta, $activo, $id_ruta);
    }

    if ($stmt->execute()) {
        header("Location: rutas.php");
        exit();
    } else {
        echo "<div class='alert alert-danger'>Error al guardar la ruta: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// Inactivar o activar ruta
if ($accion === 'inactivar' && $id_ruta) {
    $sql = "UPDATE rutas SET activo = 0 WHERE id_ruta = ?";
    $stmt = $conexion->prepare($sql);
    if ($stmt === false) {
        die("Error en prepare(): " . $conexion->error);
    }
    $stmt->bind_param("i", $id_ruta);
    $stmt->execute();
    header("Location: rutas.php");
    exit();
} elseif ($accion === 'activar' && $id_ruta) {
    $sql = "UPDATE rutas SET activo = 1 WHERE id_ruta = ?";
    $stmt = $conexion->prepare($sql);
    if ($stmt === false) {
        die("Error en prepare(): " . $conexion->error);
    }
    $stmt->bind_param("i", $id_ruta);
    $stmt->execute();
    header("Location: rutas.php");
    exit();
}
?>

<div class="container mt-5">
    <h2>
        <?php echo ucfirst($accion); ?> Ruta
    </h2>
    
    <form method="POST">
        <div class="mb-3">
            <label for="nombre_ruta" class="form-label">
                <i class="bi bi-geo-alt"></i> Nombre de la Ruta
            </label>
            <input type="text" class="form-control" id="nombre_ruta" name="nombre_ruta" 
                   value="<?php echo $ruta['nombre_ruta'] ?? ''; ?>" required>
        </div>
        
        <?php if ($accion === 'editar'): ?>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="activo" name="activo" 
                       <?php echo ($ruta['activo'] ?? false) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="activo">
                    <i class="bi bi-check-circle"></i> Activo
                </label>
            </div>
        <?php endif; ?>
        
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> Guardar
        </button>
        <a href="rutas.php" class="btn btn-secondary">
            <i class="bi bi-x-circle"></i> Cancelar
        </a>
    </form>
</div>

<?php include 'includes/footer.php'; ?>