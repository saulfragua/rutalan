<?php
include 'includes/header.php';
include 'includes/conexion.php';

// Establecer la zona horaria
date_default_timezone_set('America/Bogota');

// Configurar el idioma local para fechas en español
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es');

session_start();
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['admin', 'cobrador'])) {
    header("Location: login.php");
    exit();
}

$accion = $_GET['accion'] ?? 'crear';
$id_cliente = $_GET['id'] ?? null;

// Obtener datos del cliente si se está editando
if ($accion === 'editar' && $id_cliente) {
    $sql = "SELECT * FROM clientes WHERE id_cliente = ?";
    $stmt = $conexion->prepare($sql);
    if ($stmt === false) {
        die("Error en prepare(): " . $conexion->error);
    }
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();
    $result = $stmt->get_result();
    $cliente = $result->fetch_assoc();
    $stmt->close();
}

// Obtener todas las rutas para el select
$sql_rutas = "SELECT * FROM rutas WHERE activo = 1";
$rutas = $conexion->query($sql_rutas);
if ($rutas === false) {
    die("Error al obtener las rutas: " . $conexion->error);
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $documento = $_POST['documento'];
    $nombres = $_POST['nombres'];
    $apellidos = $_POST['apellidos'];
    $direccion = $_POST['direccion'];
    $telefono = $_POST['telefono'];
    $telefono2 = $_POST['telefono2'];
    $id_ruta = $_POST['id_ruta'];
    $activo = 1;
    $id_usuario = $_SESSION['id_usuario'];

    // Procesar la foto del cliente
    $foto_cliente = null;
    if (isset($_FILES['foto_cliente']) && $_FILES['foto_cliente']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/fotos_clientes/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_name = uniqid('cliente_') . '_' . basename($_FILES['foto_cliente']['name']);
        $file_path = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['foto_cliente']['tmp_name'], $file_path)) {
            $foto_cliente = $file_path;
        } else {
            echo "<div class='alert alert-danger'>Error al subir la foto del cliente.</div>";
        }
    }

    if ($accion === 'crear') {
        $sql = "INSERT INTO clientes (documento, nombres, apellidos, direccion, telefono, telefono2, id_ruta, activo, id_usuario, foto_cliente) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        if ($stmt === false) {
            die("Error en prepare(): " . $conexion->error);
        }
        $stmt->bind_param("ssssssiiis", $documento, $nombres, $apellidos, $direccion, $telefono, $telefono2, $id_ruta, $activo, $id_usuario, $foto_cliente);
    } elseif ($accion === 'editar' && $id_cliente) {
        $activo = isset($_POST['activo']) ? 1 : 0;
        $sql = "UPDATE clientes 
                SET documento = ?, nombres = ?, apellidos = ?, direccion = ?, telefono = ?, telefono2 = ?, id_ruta = ?, activo = ?, id_usuario = ?, foto_cliente = ? 
                WHERE id_cliente = ?";
        $stmt = $conexion->prepare($sql);
        if ($stmt === false) {
            die("Error en prepare(): " . $conexion->error);
        }
        $stmt->bind_param("ssssssiiisi", $documento, $nombres, $apellidos, $direccion, $telefono, $telefono2, $id_ruta, $activo, $id_usuario, $foto_cliente, $id_cliente);
    }

    if ($stmt->execute()) {
        header("Location: clientes.php");
        exit();
    } else {
        echo "<div class='alert alert-danger'>Error al guardar el cliente: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// Inactivar o activar cliente
if ($accion === 'inactivar' && $id_cliente) {
    $sql = "UPDATE clientes SET activo = 0 WHERE id_cliente = ?";
    $stmt = $conexion->prepare($sql);
    if ($stmt === false) {
        die("Error en prepare(): " . $conexion->error);
    }
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();
    header("Location: clientes.php");
    exit();
} elseif ($accion === 'activar' && $id_cliente) {
    $sql = "UPDATE clientes SET activo = 1 WHERE id_cliente = ?";
    $stmt = $conexion->prepare($sql);
    if ($stmt === false) {
        die("Error en prepare(): " . $conexion->error);
    }
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();
    header("Location: clientes.php");
    exit();
}
?>

<div class="container mt-5">
    <h2><i class="bi bi-person-lines-fill"></i> <?php echo ucfirst($accion); ?> Cliente</h2>
    
    <!-- Botón de regreso -->
    <a href="clientes.php" class="btn btn-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Volver a Clientes
    </a>

    <form method="POST" class="border p-4 rounded shadow" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="documento" class="form-label"><i class="bi bi-card-list"></i> Documento</label>
            <input type="text" class="form-control" id="documento" name="documento" 
                   value="<?php echo $cliente['documento'] ?? ''; ?>" required>
        </div>

        <div class="mb-3">
            <label for="foto_cliente" class="form-label"><i class="bi bi-camera"></i> Foto del Cliente</label>
            <input type="file" class="form-control" id="foto_cliente" name="foto_cliente" accept="image/*" capture="camera">
            <small class="text-muted">Toma una foto del cliente.</small>
        </div>
        
        <div class="mb-3">
            <label for="nombres" class="form-label"><i class="bi bi-person"></i> Nombres</label>
            <input type="text" class="form-control" id="nombres" name="nombres" 
                   value="<?php echo $cliente['nombres'] ?? ''; ?>" required>
        </div>
        
        <div class="mb-3">
            <label for="apellido" class="form-label"><i class="bi bi-person"></i> Apellidos</label>
            <input type="text" class="form-control" id="apellido" name="apellidos" 
                   value="<?php echo $cliente['apellidos'] ?? ''; ?>" required>
        </div>
        
        <div class="mb-3">
            <label for="direccion" class="form-label"><i class="bi bi-geo-alt"></i> Dirección</label>
            <input type="text" class="form-control" id="direccion" name="direccion" 
                   value="<?php echo $cliente['direccion'] ?? ''; ?>">
        </div>
        
        <div class="mb-3">
            <label for="telefono" class="form-label"><i class="bi bi-telephone"></i> Teléfono</label>
            <input type="text" class="form-control" id="telefono" name="telefono" 
                   value="<?php echo $cliente['telefono'] ?? ''; ?>">
        </div>
        
        <div class="mb-3">
            <label for="telefono2" class="form-label"><i class="bi bi-telephone"></i> Segundo Teléfono</label>
            <input type="text" class="form-control" id="telefono2" name="telefono2" 
                   value="<?php echo $cliente['telefono2'] ?? ''; ?>">
        </div>
        
        <div class="mb-3">
            <label for="id_ruta" class="form-label"><i class="bi bi-map"></i> Ruta de Cobro</label>
            <select class="form-select" id="id_ruta" name="id_ruta">
                <option value="">Seleccione una ruta</option>
                <?php while ($ruta = $rutas->fetch_assoc()): ?>
                    <option value="<?php echo $ruta['id_ruta']; ?>" 
                        <?php echo ($ruta['id_ruta'] == ($cliente['id_ruta'] ?? '')) ? 'selected' : ''; ?>>
                        <?php echo $ruta['nombre_ruta']; ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <?php if ($accion === 'editar'): ?>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="activo" name="activo" 
                       <?php echo ($cliente['activo'] ?? false) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="activo"><i class="bi bi-check-circle"></i> Activo</label>
            </div>
        <?php endif; ?>
        
        <button type="submit" class="btn btn-success">
            <i class="bi bi-save"></i> Guardar
        </button>
        <a href="clientes.php" class="btn btn-danger">
            <i class="bi bi-x-circle"></i> Cancelar
        </a>
    </form>
</div>

<?php include 'includes/footer.php'; ?>