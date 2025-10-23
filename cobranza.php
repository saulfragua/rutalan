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

// Obtener información del usuario actual
$id_usuario = $_SESSION['id_usuario'];
$rol_usuario = $_SESSION['rol'];

// Obtener ruta asignada si es cobrador
$ruta_asignada = null;
if ($rol_usuario == 'cobrador') {
    $stmt = $conexion->prepare("SELECT id_ruta FROM usuarios WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $ruta_asignada = $user_data['id_ruta'];
}

// Obtener todas las rutas activas (solo las asignadas si es cobrador)
if ($rol_usuario == 'admin') {
    $rutas = $conexion->query("SELECT * FROM rutas WHERE activo = 1");
} else {
    $stmt = $conexion->prepare("SELECT * FROM rutas WHERE id_ruta = ? AND activo = 1");
    $stmt->bind_param("i", $ruta_asignada);
    $stmt->execute();
    $rutas = $stmt->get_result();
}

// Obtener ruta seleccionada
$ruta_actual = $_GET['ruta'] ?? null;

// Si es cobrador y no ha seleccionado ruta, redirigir a su ruta asignada
if ($rol_usuario == 'cobrador' && !$ruta_actual && $ruta_asignada) {
    header("Location: cobranza.php?ruta=" . $ruta_asignada);
    exit();
}

// Validar que el cobrador solo pueda ver su ruta asignada
if ($rol_usuario == 'cobrador' && $ruta_actual && $ruta_actual != $ruta_asignada) {
    header("Location: cobranza.php?ruta=" . $ruta_asignada);
    exit();
}

// Obtener clientes de la ruta con información de créditos (solo saldo > 0)
$clientes = [];
if ($ruta_actual) {
    $sql = "SELECT c.*, cr.saldo_actual, MAX(p.fecha_pago) as ultimo_pago, 
                   (SELECT COUNT(*) FROM pagos WHERE id_credito = cr.id_credito) as num_pagos,
                   DATE_ADD(cr.fecha_toma_credito, INTERVAL cr.cuotas DAY) as fecha_finaliza_credito,
                   cr.fecha_toma_credito,
                   (SELECT COUNT(*) FROM planpagos WHERE id_credito = cr.id_credito AND estado = 'pendiente') as cuotas_pendientes,
                   (SELECT COUNT(*) FROM planpagos WHERE id_credito = cr.id_credito AND estado = 'pagada') as cuotas_pagadas,
                   (SELECT COUNT(*) FROM planpagos WHERE id_credito = cr.id_credito AND estado = 'vencida') as cuotas_vencidas
            FROM clientes c
            LEFT JOIN creditos cr ON c.id_cliente = cr.id_cliente
            LEFT JOIN pagos p ON cr.id_credito = p.id_credito
            WHERE c.id_ruta = ? AND cr.activo = 1 AND cr.saldo_actual > 0
            GROUP BY c.id_cliente
            HAVING saldo_actual > 0
            ORDER BY c.orden_cobranza ASC";  // Ordenar por el campo guardado
    
    $stmt = $conexion->prepare($sql);
    
    if ($stmt === false) {
        die("Error en la consulta SQL: " . $conexion->error);
    }
    
    $stmt->bind_param("i", $ruta_actual);
    $stmt->execute();
    $clientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Guardar orden de cobranza
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['orden'])) {
    $orden = json_decode($_POST['orden'], true);
    $ruta_actual = $_POST['ruta']; // Obtenemos la ruta actual
    
    // Primero resetear todos los órdenes a 0 para esta ruta
    $reset = $conexion->prepare("UPDATE clientes SET orden_cobranza = 0 WHERE id_ruta = ?");
    $reset->bind_param("i", $ruta_actual);
    $reset->execute();
    
    // Luego asignar nuevos órdenes
    foreach ($orden as $index => $id_cliente) {
        $orden_real = $index + 1; // Convertir a base 1
        $stmt = $conexion->prepare("UPDATE clientes SET orden_cobranza = ? WHERE id_cliente = ? AND id_ruta = ?");
        $stmt->bind_param("iii", $orden_real, $id_cliente, $ruta_actual);
        $stmt->execute();
    }
    echo "<div class='alert alert-success'>Orden guardado correctamente</div>";
}
?>

<div class="container mt-4">
    <h2 class="mb-4"><i class="bi bi-receipt me-2"></i> Gestión de Cobranza</h2>
    
    <!-- Selector de Rutas y Botón Cobrar Ruta -->
    <div class="row mb-4">
        <?php if ($rol_usuario == 'admin'): ?>
        <div class="col-md-4">
            <select class="form-select" onchange="location = this.value;">
                <option value="">Seleccionar Ruta</option>
                <?php while ($ruta = $rutas->fetch_assoc()): ?>
                <option value="cobranza.php?ruta=<?= $ruta['id_ruta'] ?>" 
                    <?= $ruta['id_ruta'] == $ruta_actual ? 'selected' : '' ?>>
                    <?= $ruta['nombre_ruta'] ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        <?php else: ?>
        <div class="col-md-4">
            <input type="text" class="form-control" value="<?= $rutas->fetch_assoc()['nombre_ruta'] ?>" readonly>
        </div>
        <?php endif; ?>
        <div class="col-md-6">
            <a href="gestion_pago.php?ruta=<?= $ruta_actual ?>" class="btn btn-success mb-2">
                <i class="bi bi-credit-card"></i> Cobrar Ruta</a>
            
            <a href="dashboard.php" class="btn btn-secondary mb-2">
                <i class="bi bi-arrow-left-circle"></i> Volver al Menú Principal
            </a>  
        </div>
    </div>

    <!-- Lista de Clientes -->
    <?php if ($ruta_actual): ?>
    <div class="card shadow">
        <div class="card-body">
            <form id="formOrden" method="POST">
                <input type="hidden" name="ruta" value="<?= $ruta_actual ?>">
                <input type="hidden" name="orden" id="ordenClientes">
                
                <!-- Botón de Guardar Orden al inicio -->
                <button type="submit" class="btn btn-primary mb-3"><i class="bi bi-save"></i> Guardar Orden</button>
                
                <div class="table-responsive">
                    <table class="table table-hover" id="tablaClientes">
                        <thead class="table-light">
                            <tr>
                                <th><i class="bi bi-sort-down-alt"></i> Orden</th>
                                <th><i class="bi bi-person"></i> Cliente</th>
                                <th><i class="bi bi-house-door"></i> Dirección</th>
                                <th><i class="bi bi-telephone"></i> Teléfono</th>
                                <th><i class="bi bi-currency-dollar"></i> Saldo Pendiente</th>
                                <th><i class="bi bi-calendar-event"></i> Días sin Pagar</th>
                                <th><i class="bi bi-calendar"></i> Fecha del Crédito</th>
                                <th><i class="bi bi-cash-coin"></i> Cuotas Pagadas</th>
                                <th><i class="bi bi-calendar-x"></i> Cuotas Pendientes</th>
                                <th><i class="bi bi-calendar-check"></i> Cuotas Vencidas</th>
                                <th><i class="bi bi-calendar-check"></i> Fecha Finaliza Crédito</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($clientes) > 0): ?>
                                <?php foreach ($clientes as $index => $cliente): 
                                    $ultimo_pago = $cliente['ultimo_pago'] ? strtotime($cliente['ultimo_pago']) : time();
                                    $dias = floor((time() - $ultimo_pago) / (60 * 60 * 24));
                                    
                                    // Determinar color
                                    if ($dias >= 2 && $dias <= 29) $color = 'bg-warning';
                                    elseif ($dias >= 30 && $dias <= 40) $color = 'bg-warning text-dark';
                                    elseif ($dias >= 41 && $dias <= 70) $color = 'bg-danger';
                                    else $color = 'bg-dark text-white';
                                ?>
                                <tr class="<?= $color ?>" data-id="<?= $cliente['id_cliente'] ?>">
                                    <td><?= $index + 1 ?></td> 
                                    <td><i class="fas fa-arrows-alt-v"></i> <?= $cliente['nombres'] ?> <?= $cliente['apellidos'] ?></td>
                                    <td><?= $cliente['direccion'] ?></td>
                                    <td><?= $cliente['telefono'] ?></td>
                                    <td>$<?= number_format($cliente['saldo_actual'], 2) ?></td>
                                    <td><?= $dias ?> días</td>
                                    <td><?= $cliente['fecha_toma_credito'] ?></td>
                                    <td><?= $cliente['cuotas_pagadas'] ?></td>
                                    <td><?= $cliente['cuotas_pendientes'] ?></td>
                                    <td><?= $cliente['cuotas_vencidas'] ?></td>
                                    <td><?= $cliente['fecha_finaliza_credito'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="text-center">No hay clientes con saldo pendiente en esta ruta.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Botón de Guardar Orden al final -->
                <button type="submit" class="btn btn-primary mt-2"><i class="bi bi-save"></i> Guardar Orden</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
new Sortable(document.getElementById('tablaClientes').getElementsByTagName('tbody')[0], {
    animation: 150,
    handle: '.fa-arrows-alt-v',
    onEnd: function() {
        const orden = Array.from(document.querySelectorAll('#tablaClientes tbody tr'))
            .map(tr => tr.getAttribute('data-id'));
        document.querySelector('#ordenClientes').value = JSON.stringify(orden);
    }
});
</script>

<?php include 'includes/footer.php'; ?>