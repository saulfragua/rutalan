<?php
include 'includes/header.php';
include 'includes/conexion1.php';
// Establecer la zona horaria
date_default_timezone_set('America/Bogota');
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es');
// Inicializar variables
$mensaje = '';
$adelantos = [];
// Obtener listas de usuarios y rutas para los filtros
try {
    $usuarios = $conn->query("SELECT id_usuario, nombre_completo FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
    $rutas = $conn->query("SELECT id_ruta, nombre_ruta FROM rutas")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al obtener datos de usuarios o rutas: " . $e->getMessage());
}
// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $idUsuario = $_POST['id_usuario'] ?? '';
    $idRuta = $_POST['id_ruta'] ?? '';
    $monto = $_POST['monto'] ?? '';
    $fechaAdelanto = $_POST['fecha_adelanto'] ?? '';
    $medioEntrega = $_POST['medio_entrega'] ?? '';
    $tipoMovimiento = $_POST['tipo'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    
    // Validar datos
    if (empty($idUsuario) || empty($idRuta) || empty($monto) || empty($fechaAdelanto) || empty($medioEntrega) || empty($tipoMovimiento)) {
        $mensaje = "<div class='alert alert-danger'>Todos los campos obligatorios deben ser completados.</div>";
    } else {
        try {
            // Obtener la hora actual
            $horaAdelanto = date('H:i:s'); // Formato de hora: HH:MM:SS
            // Insertar el adelanto en la base de datos con la fecha y hora actual
            $query = "INSERT INTO adelantos (id_usuario, id_ruta, monto, fecha_adelanto, hora_adelanto, medio_entrega, tipo, descripcion) 
                      VALUES (:id_usuario, :id_ruta, :monto, :fecha_adelanto, :hora_adelanto, :medio_entrega, :tipo, :descripcion)";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                'id_usuario' => $idUsuario,
                'id_ruta' => $idRuta,
                'monto' => $monto,
                'fecha_adelanto' => $fechaAdelanto,
                'hora_adelanto' => $horaAdelanto,
                'medio_entrega' => $medioEntrega,
                'tipo' => $tipoMovimiento,
                'descripcion' => $descripcion
            ]);
            // Mostrar mensaje de éxito
            $mensaje = "<div class='alert alert-success'>Adelanto registrado correctamente.</div>";
        } catch (PDOException $e) {
            // Mostrar mensaje de error
            $mensaje = "<div class='alert alert-danger'>Error al registrar el adelanto: " . $e->getMessage() . "</div>";
        }
    }
}
// Obtener adelantos filtrados por rango de fechas o búsqueda
if (isset($_GET['fecha_inicio']) || isset($_GET['fecha_fin']) || isset($_GET['busqueda'])) {
    $fechaInicio = $_GET['fecha_inicio'] ?? '';
    $fechaFin = $_GET['fecha_fin'] ?? '';
    $busqueda = $_GET['busqueda'] ?? '';
    try {
        $query = "SELECT a.id_adelanto, u.nombre_completo, a.fecha_adelanto, a.hora_adelanto, a.monto, a.medio_entrega, a.tipo, a.descripcion 
                  FROM adelantos a 
                  INNER JOIN usuarios u ON a.id_usuario = u.id_usuario 
                  WHERE 1=1";
        
        if (!empty($fechaInicio) && !empty($fechaFin)) {
            $query .= " AND DATE(a.fecha_adelanto) BETWEEN :fecha_inicio AND :fecha_fin";
        } elseif (!empty($fechaInicio)) {
            $query .= " AND DATE(a.fecha_adelanto) >= :fecha_inicio";
        } elseif (!empty($fechaFin)) {
            $query .= " AND DATE(a.fecha_adelanto) <= :fecha_fin";
        }
        
        if (!empty($busqueda)) {
            $query .= " AND u.nombre_completo LIKE :busqueda";
        }
        
        $stmt = $conn->prepare($query);

        if (!empty($fechaInicio) && !empty($fechaFin)) {
            $stmt->bindValue(':fecha_inicio', $fechaInicio);
            $stmt->bindValue(':fecha_fin', $fechaFin);
        } elseif (!empty($fechaInicio)) {
            $stmt->bindValue(':fecha_inicio', $fechaInicio);
        } elseif (!empty($fechaFin)) {
            $stmt->bindValue(':fecha_fin', $fechaFin);
        }
        
        if (!empty($busqueda)) {
            $stmt->bindValue(':busqueda', "%$busqueda%");
        }
        
        $stmt->execute();
        $adelantos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error al obtener adelantos: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Adelanto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center">Registrar Adelanto</h2>
        <!-- Mostrar mensajes de éxito o error -->
        <?= $mensaje ?>
        <form method="POST" action="">
            <div class="row mb-3">
                <div class="col">
                    <label for="id_usuario" class="form-label">
                        <i class="bi bi-person-fill"></i> Usuario
                    </label>
                    <select class="form-select" id="id_usuario" name="id_usuario" required>
                        <option value="">Seleccione un usuario</option>
                        <?php foreach ($usuarios as $usuario): ?>
                            <option value="<?= $usuario['id_usuario'] ?>">
                                <?= $usuario['nombre_completo'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label for="id_ruta" class="form-label">
                        <i class="bi bi-geo-alt-fill"></i> Ruta
                    </label>
                    <select class="form-select" id="id_ruta" name="id_ruta" required>
                        <option value="">Seleccione una ruta</option>
                        <?php foreach ($rutas as $ruta): ?>
                            <option value="<?= $ruta['id_ruta'] ?>">
                                <?= $ruta['nombre_ruta'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col">
                    <label for="monto" class="form-label">
                        <i class="bi bi-cash-coin"></i> Monto del Adelanto
                    </label>
                    <input type="number" class="form-control" id="monto" name="monto" step="0.01" required>
                </div>
                <div class="col">
                    <label for="fecha_adelanto" class="form-label">
                        <i class="bi bi-calendar-event"></i> Fecha del Adelanto
                    </label>
                    <input type="date" class="form-control" id="fecha_adelanto" name="fecha_adelanto" required>
                </div>
                <div class="col">
                    <label for="medio_entrega" class="form-label">
                        <i class="bi bi-wallet2"></i> Medio de Entrega
                    </label>
                    <select class="form-select" id="medio_entrega" name="medio_entrega" required>
                        <option value="">Seleccione un medio</option>
                        <option value="Efectivo">Efectivo</option>
                        <option value="Transferencia">Transferencia</option>
                    </select>
                </div>
                <div class="col">
                    <label for="tipo" class="form-label">
                        <i class="bi bi-arrow-left-right"></i> Tipo de Movimiento
                    </label>
                    <select class="form-select" id="tipo" name="tipo" required>
                        <option value="">Seleccione un tipo</option>
                        <option value="Ingreso">Ingreso</option>
                        <option value="Egreso">Egreso</option>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col">
                    <label for="descripcion" class="form-label">
                        <i class="bi bi-card-text"></i> Descripción (Opcional)
                    </label>
                    <textarea class="form-control" id="descripcion" name="descripcion" rows="2"></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Registrar Adelanto
            </button>
        </form>
        <!-- Filtros para la lista de adelantos -->
        <h3 class="mt-5">Lista de Adelantos</h3>
        <form method="GET" action="" class="mb-3">
            <div class="row">
                <div class="col-md-3">
                    <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio">
                </div>
                <div class="col-md-3">
                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin">
                </div>
                <div class="col-md-4">
                    <label for="busqueda" class="form-label">Buscar por Nombre</label>
                    <input type="text" class="form-control" id="busqueda" name="busqueda" placeholder="Nombre del usuario">
                </div>
                <div class="col-md-2 align-self-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </form>
        <!-- Tabla de adelantos -->
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Nombre del Usuario</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Monto</th>
                    <th>Medio de Entrega</th>
                    <th>Tipo de Movimiento</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($adelantos)): ?>
                    <?php foreach ($adelantos as $adelanto): ?>
                        <tr>
                            <td><?= $adelanto['nombre_completo'] ?></td>
                            <td><?= date('d/m/Y', strtotime($adelanto['fecha_adelanto'])) ?></td>
                            <td><?= date('H:i:s', strtotime($adelanto['hora_adelanto'])) ?></td>
                            <td><?= number_format($adelanto['monto'], 2) ?></td>
                            <td><?= $adelanto['medio_entrega'] ?></td>
                            <td><?= $adelanto['tipo'] ?></td>
                            <td><?= $adelanto['descripcion'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">No hay adelantos registrados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Botón para volver a reportes -->
        <a href="reportes.php" class="btn btn-secondary mt-3">
            <i class="bi bi-arrow-left-circle"></i> Volver a Reportes
        </a>
    </div>
</body>
<?php include 'includes/footer.php'; ?>