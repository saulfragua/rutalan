<?php
include 'includes/header.php';
include 'includes/conexion.php';

// Incluir el servicio de WhatsApp
include 'whatsapp_service.php';

// Establecer la zona horaria
date_default_timezone_set('America/Bogota');
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es');

session_start();
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['admin', 'cobrador'])) {
    header("Location: login.php");
    exit();
}

$ruta_actual = $_GET['ruta'] ?? null;
if (!$ruta_actual) {
    header("Location: cobranza.php");
    exit();
}

// Obtener el nombre de la ruta actual
$sql_ruta = "SELECT nombre_ruta FROM rutas WHERE id_ruta = ?";
$stmt_ruta = $conexion->prepare($sql_ruta);
$stmt_ruta->bind_param("i", $ruta_actual);
$stmt_ruta->execute();
$ruta_nombre = $stmt_ruta->get_result()->fetch_assoc()['nombre_ruta'] ?? 'Ruta Desconocida';

// Obtener clientes de la ruta con saldo pendiente
$sql = "SELECT c.*, cr.id_credito, cr.saldo_actual, cr.fecha_toma_credito, cr.cuotas, cr.frecuencia_pago, cr.tipo_credito,
               (SELECT COUNT(*) FROM pagos WHERE id_credito = cr.id_credito) AS num_pagos,
               (SELECT MAX(fecha_pago) FROM pagos WHERE id_credito = cr.id_credito) AS ultimo_pago,
               DATE_ADD(cr.fecha_toma_credito, INTERVAL cr.cuotas DAY) AS fecha_finaliza_credito,
               (SELECT COUNT(*) FROM planpagos WHERE id_credito = cr.id_credito AND estado = 'pendiente') AS cuotas_pendientes,
               (SELECT COUNT(*) FROM planpagos WHERE id_credito = cr.id_credito AND estado = 'pagada') AS cuotas_pagadas,
               (SELECT COUNT(*) FROM planpagos WHERE id_credito = cr.id_credito AND estado = 'vencida') AS cuotas_vencidas
        FROM clientes c
        JOIN creditos cr ON c.id_cliente = cr.id_cliente
        WHERE c.id_ruta = ? AND cr.activo = 1 AND cr.saldo_actual > 0
        ORDER BY c.orden_cobranza ASC";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $ruta_actual);
$stmt->execute();
$clientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener índice del cliente actual
$indice_actual = $_GET['indice'] ?? 0;
$cliente_actual = $clientes[$indice_actual] ?? null;

// Verificar y actualizar cuotas vencidas
if ($cliente_actual) {
    $id_credito = $cliente_actual['id_credito'];
    $fecha_actual = date('Y-m-d');

    // Actualizar cuotas pendientes a vencidas si la fecha de pago es anterior a la fecha actual
    $sql_actualizar_vencidas = "UPDATE planpagos SET estado = 'vencida' 
                                WHERE id_credito = ? AND estado = 'pendiente' AND fecha_pago < ?";
    $stmt_actualizar_vencidas = $conexion->prepare($sql_actualizar_vencidas);
    $stmt_actualizar_vencidas->bind_param("is", $id_credito, $fecha_actual);
    $stmt_actualizar_vencidas->execute();

    // Actualizar cuotas con monto restante 0 a pagadas
    $sql_actualizar_pagadas = "UPDATE planpagos SET estado = 'pagada' 
                               WHERE id_credito = ? AND monto_restante = 0";
    $stmt_actualizar_pagadas = $conexion->prepare($sql_actualizar_pagadas);
    $stmt_actualizar_pagadas->bind_param("i", $id_credito);
    $stmt_actualizar_pagadas->execute();
}

// Verificar si el último pago fue hoy
if ($cliente_actual) {
    $fecha_ultimo_pago = $cliente_actual['ultimo_pago'];
    $hoy = date('Y-m-d');
    $mostrar_check = ($fecha_ultimo_pago == $hoy);
    
    // Inicializar variables
    $dias_mora = 0;
    $color_fondo = 'background-color: white;';
    $leyenda = 'AL DÍA';

    // Verificar si hay cuotas vencidas
    $sql_cuotas_vencidas = "SELECT COUNT(*) AS total FROM planpagos 
                           WHERE id_credito = ? AND estado = 'vencida'";
    $stmt_cuotas_vencidas = $conexion->prepare($sql_cuotas_vencidas);
    $stmt_cuotas_vencidas->bind_param("i", $cliente_actual['id_credito']);
    $stmt_cuotas_vencidas->execute();
    $total_vencidas = $stmt_cuotas_vencidas->get_result()->fetch_assoc()['total'];

    if ($total_vencidas > 0) {
        // Calcular días corridos desde FECHA DE CRÉDITO (no desde vencimiento)
        $fecha_credito = new DateTime($cliente_actual['fecha_toma_credito']);
        $hoy_obj = new DateTime();
        
        // Diferencia exacta en días naturales (corridos)
        $dias_mora = $fecha_credito->diff($hoy_obj)->days;
        
        // Aplicar semaforización
        if ($dias_mora >= 1 && $dias_mora <= 30) {
            $color_fondo = 'background-color: #28a745; color: white;';
            $leyenda = 'PENDIENTE ('.$dias_mora.' días)';
        } elseif ($dias_mora >= 31 && $dias_mora <= 40) {
            $color_fondo = 'background-color: #ffc107;';
            $leyenda = 'VENCIDO ('.$dias_mora.' días)';
        } elseif ($dias_mora >= 41 && $dias_mora <= 70) {
            $color_fondo = 'background-color: #fd7e14; color: white;';
            $leyenda = 'CLAVO ('.$dias_mora.' días)';
        } elseif ($dias_mora >= 71) {
            $color_fondo = 'background-color: #dc3545; color: white;';
            $leyenda = 'RECLAVO ('.$dias_mora.' días)';
        }
    }
    // Si no hay cuotas vencidas, mantiene "AL DÍA" (blanco)
}


// Mostrar mensaje de recordatorio si existe
if (isset($_SESSION['mensaje_recordatorio'])) {
    echo $_SESSION['mensaje_recordatorio'];
    unset($_SESSION['mensaje_recordatorio']);
}




// Procesar pago
$pago_exitoso = false;
$mensaje_alerta = '';
$id_pago = null; // Variable para almacenar el ID del pago
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cliente_actual) {
    $monto = floatval($_POST['monto']);
    $descuento = floatval($_POST['descuento']);
    $id_credito = $cliente_actual['id_credito'];
    $id_cliente = $cliente_actual['id_cliente']; // Obtener el id_cliente
    $saldo_actual = floatval($cliente_actual['saldo_actual']);
    $id_usuario = $_SESSION['id_usuario']; // Obtener el id_usuario de la sesión

    // Calcular el monto neto a pagar
    $monto_neto = $monto + $descuento;
    $nuevo_saldo = $saldo_actual - $monto_neto;

    // Validar que los valores sean correctos
    if ($monto_neto <= 0 || $monto_neto > $saldo_actual) {
        $mensaje_alerta = "<div class='alert alert-danger'>El monto a pagar no puede ser mayor que el saldo actual ni ser negativo.</div>";
    } else {
        try {
            // Iniciar transacción
            $conexion->begin_transaction();

            // Capturar la fecha y hora actual
            $fecha_actual = date('Y-m-d');
            $hora_actual = date('H:i:s');

            // Registrar pago con la fecha y hora actual, el id_usuario, el id_ruta y el id_cliente
            $stmtInsert = $conexion->prepare("INSERT INTO pagos (id_cliente, id_credito, fecha_pago, hora_pago, monto_pagado, descuento, id_usuario, id_ruta) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtInsert->bind_param("iissddii", $id_cliente, $id_credito, $fecha_actual, $hora_actual, $monto_neto, $descuento, $id_usuario, $ruta_actual);
            $stmtInsert->execute();
            $id_pago = $conexion->insert_id; // Obtener el ID del pago registrado

            // Actualizar saldo del crédito
            $stmtUpdate = $conexion->prepare("UPDATE creditos SET saldo_actual = ? WHERE id_credito = ?");
            $stmtUpdate->bind_param("di", $nuevo_saldo, $id_credito);
            $stmtUpdate->execute();

            // Obtener las cuotas pendientes o vencidas
            $sql_cuotas_pendientes = "SELECT id_plan_pago, monto_cuota, monto_restante FROM planpagos 
                                     WHERE id_credito = ? AND (estado = 'pendiente' OR estado = 'vencida') 
                                     ORDER BY fecha_pago ASC";
            $stmt_cuotas_pendientes = $conexion->prepare($sql_cuotas_pendientes);
            $stmt_cuotas_pendientes->bind_param("i", $id_credito);
            $stmt_cuotas_pendientes->execute();
            $cuotas_pendientes = $stmt_cuotas_pendientes->get_result()->fetch_all(MYSQLI_ASSOC);

            $monto_restante = $monto_neto;
            foreach ($cuotas_pendientes as $cuota) {
                if ($monto_restante <= 0) break;

                $monto_cuota = $cuota['monto_cuota'];
                $monto_restante_cuota = $cuota['monto_restante'];
                $id_plan_pago = $cuota['id_plan_pago'];

                if ($monto_restante >= $monto_restante_cuota) {
                    // Pagar la cuota completa
                    $sql_actualizar_cuota = "UPDATE planpagos SET estado = 'pagada', monto_restante = 0 WHERE id_plan_pago = ?";
                    $stmt_actualizar_cuota = $conexion->prepare($sql_actualizar_cuota);
                    $stmt_actualizar_cuota->bind_param("i", $id_plan_pago);
                    $stmt_actualizar_cuota->execute();

                    $monto_restante -= $monto_restante_cuota;
                } else {
                    // Pagar parcialmente la cuota
                    $sql_actualizar_cuota = "UPDATE planpagos SET monto_restante = monto_restante - ? WHERE id_plan_pago = ?";
                    $stmt_actualizar_cuota = $conexion->prepare($sql_actualizar_cuota);
                    $stmt_actualizar_cuota->bind_param("di", $monto_restante, $id_plan_pago);
                    $stmt_actualizar_cuota->execute();

                    $monto_restante = 0;
                }
            }

            // Confirmar transacción
            $conexion->commit();

            // INTEGRACIÓN NUEVA: Enviar mensaje por WhatsApp después del pago exitoso
            $whatsappService = new WhatsAppService();
            $whatsappEnviado = $whatsappService->enviarConfirmacionPago($id_pago);
            
            // Opcional: guardar estado del envío en la base de datos
            if ($whatsappEnviado) {
                $stmtUpdateWhatsapp = $conexion->prepare(
                    "UPDATE pagos SET whatsapp_enviado = 1 WHERE id_pago = ?"
                );
                $stmtUpdateWhatsapp->bind_param("i", $id_pago);
                $stmtUpdateWhatsapp->execute();
                
                // Agregar mensaje de confirmación de WhatsApp
                $mensaje_alerta .= "<div class='alert alert-info mt-2'>✅ Mensaje de WhatsApp enviado al cliente.</div>";
            } else {
                $mensaje_alerta .= "<div class='alert alert-warning mt-2'>⚠️ El pago se registró pero no se pudo enviar el WhatsApp.</div>";
            }

            // Marcar el pago como exitoso
            $pago_exitoso = true;
            $mensaje_alerta = "<div class='alert alert-success'>Pago realizado con éxito.</div>" . $mensaje_alerta;

            // Redirigir para evitar reenvío del formulario
            header("Location: gestion_pago.php?ruta=$ruta_actual&indice=$indice_actual");
            exit();
        } catch (Exception $e) {
            // Revertir en caso de error
            $conexion->rollback();
            $mensaje_alerta = "<div class='alert alert-danger'>Error al registrar pago: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cobranza</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .card {
            margin-top: 20px;
        }
        .card-header {
            font-size: 1.25rem;
        }
        .form-control {
            margin-bottom: 10px;
        }
        .btn {
            margin: 5px;
        }
        .img-fluid {
            max-width: 100%;
            height: auto;
        }
        .status-indicator {
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
        }
        @media (max-width: 768px) {
            .card-header {
                font-size: 1rem;
            }
            .btn {
                width: 100%;
                margin: 5px 0;
            }
            .col-md-8, .col-md-4 {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <div class="card mx-auto" style="max-width: 1000px;">
        <div class="card-header bg-primary text-white">
            <h4><i class="bi bi-cash-coin"></i> Cobrar Ruta: <?= htmlspecialchars($ruta_nombre) ?></h4> <!-- Mostrar el nombre de la ruta -->
        </div>
        <div class="card-body">
            <?php if ($cliente_actual) : ?>
                <?php echo $mensaje_alerta; ?>
                <div class="row">
                    <!-- Información del cliente -->
                    <div class="col-md-8">
                        <!-- Mostrar el indicador de estado -->
                        <?php if (isset($color_fondo) && isset($leyenda)) : ?>
                            <div class="status-indicator" style="<?= $color_fondo ?>">
                                <?= $leyenda ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <input type="text" class="form-control" readonly 
                                   value=" <?= $cliente_actual['orden_cobranza'] ?>.<?= $cliente_actual['nombres'] ?> <?= $cliente_actual['apellidos'] ?>">
                            <!-- Mostrar check si el último pago fue hoy -->
                            <?php if ($mostrar_check) : ?>
                                <span style="color: green; font-size: 1.5em;">✅</span>
                            <?php endif; ?>
                            <!-- Información adicional agregada -->
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="bi bi-geo-alt"></i> <?= $cliente_actual['direccion'] ?><br>
                                    <i class="bi bi-telephone"></i> Tel: <?= $cliente_actual['telefono'] ?><br>
                                    <i class="bi bi-calendar"></i> Fecha de Crédito: <?= $cliente_actual['fecha_toma_credito'] ?> <?= strftime('%A', strtotime($cliente_actual['fecha_toma_credito'])) ?><br>
                                    <i class="bi bi-calendar-event"></i> Frecuencia de Pago: <?= $cliente_actual['frecuencia_pago'] ?><br>
                                    <i class="bi bi-credit-card"></i> Tipo de Crédito: <?= $cliente_actual['tipo_credito'] ?><br>
                                    <i class="bi bi-check-circle"></i> Cuotas Pagadas: <?= $cliente_actual['cuotas_pagadas'] ?><br>
                                    <i class="bi bi-clock"></i> Cuotas Pendientes: <?= $cliente_actual['cuotas_pendientes'] ?><br>
                                    <i class="bi bi-exclamation-circle"></i> Cuotas Vencidas: <?= $cliente_actual['cuotas_vencidas'] ?><br>
                                    <i class="bi bi-calendar-check"></i> Fecha Finaliza Crédito: <?= $cliente_actual['fecha_finaliza_credito'] ?><br>
                                </small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><i class="bi bi-wallet2"></i> Saldo Actual:</label>
                            <input type="text" class="form-control" readonly 
                                   value="$<?= number_format($cliente_actual['saldo_actual'], 2) ?>">
                        </div>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-currency-dollar"></i> Monto a Pagar:</label>
                                <input type="number" class="form-control" name="monto" 
                                       step="0" min="0" max="<?= $cliente_actual['saldo_actual'] ?>" value="0" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-percent"></i> Descuento:</label>
                                <input type="number" class="form-control" name="descuento" 
                                       step="0" min="0" value="0" required>
                            </div>

                            <div class="row g-2">
                                <!-- Primera fila: Anterior y Siguiente -->
                                    <div class="col-6 d-none d-md-block">
                                        <a href="gestion_pago.php?ruta=<?= $ruta_actual ?>&indice=<?= max(0, $indice_actual - 1) ?>" 
                                           class="btn btn-secondary w-100 <?= $indice_actual == 0 ? 'disabled' : '' ?>">
                                           <i class="bi bi-arrow-left"></i> Anterior
                                        </a>
                                    </div>
                                    <div class="col-6 d-none d-md-block">
                                        <a href="gestion_pago.php?ruta=<?= $ruta_actual ?>&indice=<?= min(count($clientes) - 1, $indice_actual + 1) ?>" 
                                           class="btn btn-secondary w-100 <?= $indice_actual == count($clientes) - 1 ? 'disabled' : '' ?>">
                                           Siguiente <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </div>
                                <!-- Segunda fila: Pagar y Refinanciar -->
                                <div class="col-6">
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="bi bi-check-circle"></i> Pagar
                                    </button>
                                </div>
                                <div class="col-6">
                                    <a href="refinanciar.php?accion=refinanciar&id_cliente=<?= $cliente_actual['id_cliente'] ?>&id_credito=<?= $cliente_actual['id_credito'] ?>&saldo=<?= $cliente_actual['saldo_actual'] ?>&ruta=<?= $ruta_actual ?>&indice=<?= $indice_actual ?>" 
                                       class="btn btn-warning w-100">
                                       <i class="bi bi-arrow-repeat"></i> Refinanciar
                                    </a>
                                </div>
                                <div class="col-6">
    <!-- <div class="col-6">
    <form method="post" action="enviar_recordatorio.php" class="w-100" id="formRecordatorio">
        <input type="hidden" name="id_credito" value="<?= $cliente_actual['id_credito']; ?>">
        <input type="hidden" name="id_cliente" value="<?= $cliente_actual['id_cliente']; ?>">
        <button type="submit" class="btn btn-info w-100" id="btnRecordatorio">
            <i class="bi bi-envelope"></i> Enviar recordatorio
        </button>
    </form>
</div> -->
                            </div>

                            <!-- Filtro para clientes no cobrados hoy -->
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-filter"></i> Clientes no cobrados hoy:</label>
                                <select class="form-control" id="filtroClientes" onchange="filtrarClientes(this.value)">
                                    <option value="">Todos los clientes</option>
                                    <?php foreach ($clientes as $index => $cliente): ?>
                                        <?php
                                        $fecha_ultimo_pago = $cliente['ultimo_pago'];
                                        $hoy = date('Y-m-d');
                                        $mostrar_check = ($fecha_ultimo_pago == $hoy);
                                        ?>
                                        <?php if (!$mostrar_check): ?>
                                            <option value="<?= $index ?>"><?= $cliente['nombres'] ?> <?= $cliente['apellidos'] ?> (<?= $cliente['orden_cobranza'] ?>)</option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Botón para volver a cobranza -->
                            <a href="cobranza.php" class="btn btn-secondary mb-3"><i class="bi bi-arrow-left-circle"></i> Volver a Cobranza</a>
                        </form>
                    </div>

                    <!-- Foto del cliente -->
                    <div class="col-md-4 text-center mb-3">
                        <?php if (!empty($cliente_actual['foto_cliente'])) : ?>
                            <?php if (isset($_GET['cargar_foto']) && $_GET['cargar_foto'] == 'true') : ?>
                                <img src="<?= $cliente_actual['foto_cliente'] ?>" alt="Foto del Cliente" class="img-fluid rounded" style="max-width: 100%; height: auto;">
                            <?php else : ?>
                                <button class="btn btn-primary" onclick="cargarFoto()">Cargar Foto</button>
                            <?php endif; ?>
                        <?php else : ?>
                            <p class="text-muted"><i class="bi bi-image"></i> No hay foto disponible</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No hay clientes con saldo pendiente en esta ruta.
                </div>
                <a href="cobranza.php" class="btn btn-primary"><i class="bi bi-arrow-left-circle"></i> Volver a Cobranza</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Función para filtrar clientes no cobrados hoy
function filtrarClientes(indice) {
    if (indice !== "") {
        window.location.href = `gestion_pago.php?ruta=<?= $ruta_actual ?>&indice=${indice}`;
    }
}

// Función para cargar la foto del cliente
function cargarFoto() {
    window.location.href = `gestion_pago.php?ruta=<?= $ruta_actual ?>&indice=<?= $indice_actual ?>&cargar_foto=true`;
}

// Detectar gestos táctiles
let touchStartX = 0;
let touchEndX = 0;

document.addEventListener('touchstart', function(event) {
    touchStartX = event.changedTouches[0].screenX;
}, false);

document.addEventListener('touchend', function(event) {
    touchEndX = event.changedTouches[0].screenX;
    handleSwipe();
}, false);

function handleSwipe() {
    const swipeThreshold = 50; // Sensibilidad del deslizamiento

    if (touchEndX < touchStartX - swipeThreshold) {
        // Deslizamiento hacia la izquierda (Siguiente cliente)
        const nextIndex = <?= min(count($clientes) - 1, $indice_actual + 1) ?>;
        if (nextIndex !== <?= $indice_actual ?>) {
            window.location.href = `gestion_pago.php?ruta=<?= $ruta_actual ?>&indice=${nextIndex}`;
        }
    } else if (touchEndX > touchStartX + swipeThreshold) {
        // Deslizamiento hacia la derecha (Cliente anterior)
        const prevIndex = <?= max(0, $indice_actual - 1) ?>;
        if (prevIndex !== <?= $indice_actual ?>) {
            window.location.href = `gestion_pago.php?ruta=<?= $ruta_actual ?>&indice=${prevIndex}`;
        }
    }
}



document.getElementById('formRecordatorio').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('btnRecordatorio');
    const originalText = btn.innerHTML;
    
    // Deshabilitar botón durante el envío
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enviando...';
    
    // Enviar solicitud AJAX
    fetch('enviar_recordatorio.php', {
        method: 'POST',
        body: new FormData(this)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mostrar mensaje de éxito
            mostrarNotificacion('Recordatorio enviado exitosamente', 'success');
        } else {
            // Mostrar mensaje de error
            mostrarNotificacion('Error al enviar recordatorio: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        mostrarNotificacion('Error de conexión', 'danger');
    })
    .finally(() => {
        // Restaurar botón
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});

function mostrarNotificacion(mensaje, tipo) {
    // Crear elemento de notificación
    const notificacion = document.createElement('div');
    notificacion.className = `alert alert-${tipo} alert-dismissible fade show`;
    notificacion.innerHTML = `
        ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Insertar al inicio del card-body
    const cardBody = document.querySelector('.card-body');
    cardBody.insertBefore(notificacion, cardBody.firstChild);
    
    // Auto-eliminar después de 5 segundos
    setTimeout(() => {
        notificacion.remove();
    }, 5000);
}
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>

