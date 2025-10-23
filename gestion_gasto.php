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
$id_gasto = $_GET['id'] ?? null;

// Obtener rutas activas
$sql_rutas = "SELECT * FROM rutas WHERE activo = 1";
$rutas = $conexion->query($sql_rutas);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_ruta = $_POST['id_ruta'];
    $descripcion = $_POST['descripcion'];
    $monto = $_POST['monto'];
    $fecha_gasto = $_POST['fecha_gasto'];
    $id_usuario = $_SESSION['id_usuario'];

    // Obtener la fecha y hora actual del servidor
    $fecha_hora_gasto = date('Y-m-d H:i:s');

    if ($accion === 'crear') {
        // Insertar nuevo gasto con fecha y hora
        $stmt = $conexion->prepare("INSERT INTO gastos (id_ruta, id_usuario, descripcion, monto, fecha_gasto, hora_gasto) VALUES (?, ?, ?, ?, ?, ?)");
        // Asegúrate de que la cadena de tipos tenga 6 caracteres (iisds para los primeros 5, s para la hora)
        $stmt->bind_param("iisdss", $id_ruta, $id_usuario, $descripcion, $monto, $fecha_gasto, $fecha_hora_gasto);
    } elseif ($accion === 'editar' && $id_gasto) {
        // Actualizar solo los campos editables (no se actualiza la hora)
        $stmt = $conexion->prepare("UPDATE gastos SET id_ruta = ?, descripcion = ?, monto = ? WHERE id_gasto = ?");
        $stmt->bind_param("isdi", $id_ruta, $descripcion, $monto, $id_gasto);
    }

    if ($stmt->execute()) {
        header("Location: dashboard.php");
        exit();
    } else {
        echo "<div class='alert alert-danger'><i class='bi bi-exclamation-circle'></i> Error al guardar el gasto</div>";
    }
}

// Eliminar gasto
if ($accion === 'eliminar' && $id_gasto) {
    $stmt = $conexion->prepare("DELETE FROM gastos WHERE id_gasto = ?");
    $stmt->bind_param("i", $id_gasto);
    $stmt->execute();
    header("Location: gastos.php");
    exit();
}

// Obtener datos para edición
if ($accion === 'editar' && $id_gasto) {
    $stmt = $conexion->prepare("SELECT * FROM gastos WHERE id_gasto = ?");
    $stmt->bind_param("i", $id_gasto);
    $stmt->execute();
    $gasto = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucfirst($accion) ?> Gasto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .container {
            max-width: 800px;
            margin-top: 20px;
        }
        .form-label {
            font-weight: bold;
        }
        .btn {
            margin-top: 10px;
        }
    </style>
</head>
<br>
<body>
<div class="container">
    <h2><i class="bi bi-cash-stack"></i> <?= ucfirst($accion) ?> Gasto</h2>
    
    <form method="POST">
        <div class="mb-3">
            <label class="form-label"><i class="bi bi-signpost"></i> Ruta</label>
            <select class="form-select" name="id_ruta" required>
                <?php while($ruta = $rutas->fetch_assoc()): ?>
                <option value="<?= $ruta['id_ruta'] ?>" <?= isset($gasto) && $gasto['id_ruta'] == $ruta['id_ruta'] ? 'selected' : '' ?>>
                    <?= $ruta['nombre_ruta'] ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="mb-3">
            <label class="form-label"><i class="bi bi-card-text"></i> Descripción</label>
            <input type="text" class="form-control" name="descripcion" 
                   value="<?= $gasto['descripcion'] ?? '' ?>" required>
        </div>
        
        <div class="mb-3">
            <label class="form-label"><i class="bi bi-currency-dollar"></i> Monto</label>
            <input type="number" class="form-control" name="monto" step="0.01" min="0.01"
                   value="<?= $gasto['monto'] ?? '' ?>" required>
        </div>
        
        <div class="mb-3">
            <label class="form-label"><i class="bi bi-calendar"></i> Fecha</label>
            <input type="date" class="form-control" name="fecha_gasto" 
                   value="<?= isset($gasto) ? $gasto['fecha_gasto'] : date('Y-m-d') ?>" 
                   <?= $accion === 'editar' ? 'readonly' : '' ?> required>
        </div>
        
        <!-- Mostrar la hora solo para el administrador -->
        <?php if ($_SESSION['rol'] === 'admin' && isset($gasto)): ?>
            <div class="mb-3">
                <label class="form-label"><i class="bi bi-clock"></i> Hora del Gasto</label>
                <input type="text" class="form-control" readonly 
                       value="<?= date('H:i:s', strtotime($gasto['hora_gasto'])) ?>">
            </div>
        <?php endif; ?>
        
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> Guardar
        </button>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="bi bi-x-circle"></i> Cancelar
        </a>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>