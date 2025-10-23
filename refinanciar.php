<?php
include 'includes/header.php';
include 'includes/conexion.php';

function obtenerSaldoCreditoAnterior($id_credito_anterior) {
    global $conexion;
    $sql = "SELECT saldo_actual FROM creditos WHERE id_credito = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_credito_anterior);
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_assoc();
    return $resultado ? floatval($resultado['saldo_actual']) : 0;
}

include 'whatsapp_service.php'; // Incluir el servicio de WhatsApp

// Establecer la zona horaria
date_default_timezone_set('America/Bogota');
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es');

session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Verificar parámetros GET
if (!isset($_GET['id_cliente']) || !isset($_GET['id_credito']) || !isset($_GET['saldo']) || !isset($_GET['ruta']) || !isset($_GET['indice'])) {
    die("No se ha especificado un crédito para refinanciar.");
}

$id_cliente = intval($_GET['id_cliente']);
$id_credito = intval($_GET['id_credito']);
$saldo = floatval($_GET['saldo']);
$ruta_actual = intval($_GET['ruta']);
$indice_actual = intval($_GET['indice']);

// Obtener datos del crédito actual
$stmt = $conexion->prepare("SELECT * FROM creditos WHERE id_credito = ?");
$stmt->bind_param("i", $id_credito);
$stmt->execute();
$credito = $stmt->get_result()->fetch_assoc();

if (!$credito) {
    die("<div class='alert alert-danger'>El crédito no existe.</div>");
}

// Obtener datos del cliente
$stmt_cliente = $conexion->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
$stmt_cliente->bind_param("i", $credito['id_cliente']);
$stmt_cliente->execute();
$cliente = $stmt_cliente->get_result()->fetch_assoc();

$saldo_pendiente = $credito['saldo_actual'];
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'liquidar') {
    $fecha_pago = date('Y-m-d');
    $hora_pago = date('H:i:s');

    if ($saldo_pendiente < 0) {
        $mensaje = "<div class='alert alert-danger'>El saldo pendiente no puede ser negativo.</div>";
    } else {
        $conexion->begin_transaction();

        try {
            // Registrar pago
            $stmt_pago = $conexion->prepare("INSERT INTO pagos (id_credito, id_cliente, monto_pagado, fecha_pago, hora_pago, id_usuario) 
                                           VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_pago->bind_param("iidssi", $id_credito, $id_cliente, $saldo_pendiente, $fecha_pago, $hora_pago, $_SESSION['id_usuario']);
            $stmt_pago->execute();

            // Actualizar crédito actual
            $stmt_actualizar = $conexion->prepare("UPDATE creditos SET saldo_actual = 0, activo = 0 WHERE id_credito = ?");
            $stmt_actualizar->bind_param("i", $id_credito);
            $stmt_actualizar->execute();

            // Procesar nuevo crédito
            $nuevo_monto = floatval($_POST['nuevo_monto']);
            $nuevas_cuotas = intval($_POST['cuotas']);
            $frecuencia_pago = $_POST['frecuencia_pago'];
            $tipo_refinanciacion = $_POST['tipo_refinanciacion'];

            // Calcular seguro
            $seguro = 0;
            if (isset($_POST['seguro']) && $_POST['seguro'] === 'on') {
                $seguro = (floor(($nuevo_monto - 1) / 100) + 1) * 5;
                if ($nuevas_cuotas === 70) $seguro *= 2;
            }

            $tasa_interes = ($nuevas_cuotas === 70) ? 48 : 24;
            $intereses = $nuevo_monto * ($tasa_interes / 100);
            
            // Calcular saldo actual según el tipo de refinanciación
            if ($tipo_refinanciacion === 'descontar') {
                $saldo_actual_con_intereses = $nuevo_monto + $intereses;
            } else {
                $saldo_actual_con_intereses = $nuevo_monto + $intereses + $saldo_pendiente;
            }

            $fecha_toma_credito = date('Y-m-d');
            $hora_toma_credito = date('H:i:s');
            $fecha_finaliza_credito = date('Y-m-d', strtotime("+$nuevas_cuotas days"));

            // Insertar el nuevo crédito (sin el campo tipo_refinanciacion)
            $stmt_nuevo_credito = $conexion->prepare("INSERT INTO creditos (
                id_cliente, fecha_toma_credito, monto_credito, cuotas, tasa_interes, 
                frecuencia_pago, seguro, saldo_actual, activo, id_usuario, 
                id_ruta, fecha_finaliza_credito, hora_toma_credito, tipo_credito
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // Crear variables para cada parámetro
            $activo = 1;
            $tipo_credito = 'refinanciado';
            
            $stmt_nuevo_credito->bind_param(
                "issdisdsiissss",
                $id_cliente,
                $fecha_toma_credito,
                $nuevo_monto,
                $nuevas_cuotas,
                $tasa_interes,
                $frecuencia_pago,
                $seguro,
                $saldo_actual_con_intereses,
                $activo,
                $_SESSION['id_usuario'],
                $ruta_actual,
                $fecha_finaliza_credito,
                $hora_toma_credito,
                $tipo_credito
            );
            $stmt_nuevo_credito->execute();

            $id_nuevo_credito = $conexion->insert_id;

            // Calcular número de cuotas según frecuencia
            $numero_cuotas = 0;
            $intervalo = 1;
            
            switch ($frecuencia_pago) {
                case 'diario':
                    $numero_cuotas = $nuevas_cuotas;
                    $intervalo = 1;
                    break;
                case 'semanal':
                    $numero_cuotas = ceil($nuevas_cuotas / 8);
                    $intervalo = 7;
                    break;
                case 'quincenal':
                    $numero_cuotas = ceil($nuevas_cuotas / 16);
                    $intervalo = 15;
                    break;
                case 'mensual':
                    $numero_cuotas = ceil($nuevas_cuotas / 31);
                    $intervalo = 30;
                    break;
            }

            // Generar cuotas
            $monto_cuota = $saldo_actual_con_intereses / $numero_cuotas;
            for ($i = 1; $i <= $numero_cuotas; $i++) {
                $fecha_pago_cuota = date('Y-m-d', strtotime("$fecha_toma_credito +" . ($i * $intervalo) . " days"));
                $stmt_cuota = $conexion->prepare("INSERT INTO planpagos (id_credito, numero_cuota, monto_cuota, monto_restante, fecha_pago, estado) 
                                                  VALUES (?, ?, ?, ?, ?, 'pendiente')");
                $stmt_cuota->bind_param("iidss", $id_nuevo_credito, $i, $monto_cuota, $monto_cuota, $fecha_pago_cuota);
                $stmt_cuota->execute();
            }

            // ENVIAR MENSAJE DE WHATSAPP - REFINANCIACIÓN EXITOSA
            try {
                $whatsappService = new WhatsAppService();
                $whatsappService->enviarConfirmacionRefinanciacion($id_credito, $id_nuevo_credito, $tipo_refinanciacion, $saldo_pendiente, $saldo_credito_anterior);



            } catch (Exception $e) {
                // No mostrar error al usuario si falla el WhatsApp, solo log
                error_log("Error al enviar WhatsApp de refinanciación: " . $e->getMessage());
            }

            $conexion->commit();
            // Mostrar mensaje de éxito
            echo "<div class='alert alert-success'>Refinanciación completada exitosamente. Se ha enviado un mensaje de confirmación por WhatsApp al cliente.</div>";
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'gestion_pago.php?ruta=$ruta_actual&indice=$indice_actual';
                }, 3000);
            </script>";
            exit();

        } catch (Exception $e) {
            $conexion->rollback();
            $mensaje = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
        }
    }
    
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refinanciar o Liquidar Crédito</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 800px; margin-top: 20px; }
        .form-label { font-weight: bold; }
        .btn { margin-top: 10px; }
        .form-check-input:checked { background-color: #0d6efd; border-color: #0d6efd; }
    </style>
</head>
<body>
<div class="container">
    <h2><i class="bi bi-arrow-repeat"></i> Refinanciar o Liquidar Crédito</h2>
    
    <?php if (!empty($mensaje)) echo $mensaje; ?>
    
    <form method="POST" onsubmit="return validarFormulario()">
        <input type="hidden" name="id_credito" value="<?= $id_credito ?>">
        
        <div class="mb-3">
            <label class="form-label"><i class="bi bi-person-fill"></i> Cliente</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($cliente['nombres'] . ' ' . $cliente['apellidos']) ?>" readonly>
        </div>
        
        <div class="mb-3">
            <label class="form-label"><i class="bi bi-cash-stack"></i> Saldo Pendiente</label>
            <input type="text" class="form-control" id="saldo_pendiente" value="<?= number_format($saldo_pendiente, 2) ?>" readonly>
        </div>
        
        <div class="mb-3">
            <label class="form-label"><i class="bi bi-cash-stack"></i> Nuevo Monto del Crédito</label>
            <input type="number" class="form-control" name="nuevo_monto" id="nuevo_monto" step="0.01" min="0.01" required oninput="calcularResumen()">
        </div>
        
        <div class="mb-3">
            <label class="form-label">Tipo de Refinanciación <i class="bi bi-arrow-left-right"></i></label>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="tipo_refinanciacion" id="descontar" value="descontar" checked onchange="calcularResumen()">
                <label class="form-check-label" for="descontar">Descontar saldo pendiente</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="tipo_refinanciacion" id="sin_descontar" value="sin_descontar" onchange="calcularResumen()">
                <label class="form-check-label" for="sin_descontar">Sin descontar saldo</label>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Plazo (Días) <i class="bi bi-clock"></i></label>
            <select class="form-select" name="cuotas" id="cuotas" required onchange="calcularResumen()">
                <option value="31" <?= $credito['cuotas'] == 31 ? 'selected' : '' ?>>31 días</option>
                <option value="40" <?= $credito['cuotas'] == 40 ? 'selected' : '' ?>>40 días</option>
                <option value="70" <?= $credito['cuotas'] == 70 ? 'selected' : '' ?>>70 días</option>
            </select>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Frecuencia de Pago <i class="bi bi-calendar-week"></i></label>
            <select class="form-select" name="frecuencia_pago" id="frecuencia_pago" required>
                <option value="diario" <?= $credito['frecuencia_pago'] == 'diario' ? 'selected' : '' ?>>Diario</option>
                <option value="semanal" <?= $credito['frecuencia_pago'] == 'semanal' ? 'selected' : '' ?>>Semanal</option>
                <option value="quincenal" <?= $credito['frecuencia_pago'] == 'quincenal' ? 'selected' : '' ?>>Quincenal</option>
                <option value="mensual" <?= $credito['frecuencia_pago'] == 'mensual' ? 'selected' : '' ?>>Mensual</option>
            </select>
        </div>
        
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" name="seguro" id="seguro" checked onchange="calcularResumen()">
            <label class="form-check-label" for="seguro">Incluir seguro <i class="bi bi-shield-lock"></i> ($5 por cada $100)</label>
        </div>
        
        <div class="card mb-3">
            <div class="card-header">Resumen del Crédito <i class="bi bi-clipboard-check"></i></div>
            <div class="card-body">
                <p><strong>Saldo pendiente a refinanciar:</strong> <span id="resumen_saldo_pendiente">$<?= number_format($saldo_pendiente, 2) ?></span></p>
                <p><strong>Nuevo monto del crédito:</strong> <span id="resumen_nuevo_monto">$0.00</span></p>
                <p><strong>Tipo de refinanciación:</strong> <span id="resumen_tipo_refinanciacion">Descontar saldo</span></p>
                <p><strong>Seguro:</strong> <span id="resumen_seguro">$0.00</span></p>
                <p><strong>Monto a entregar al cliente:</strong> <span id="resumen_entregar">$0.00</span></p>
                <p><strong>Intereses:</strong> <span id="resumen_intereses">$0.00</span></p>
                <p><strong>Total a pagar:</strong> <span id="resumen_total">$0.00</span></p>
                <p><strong>Fecha de finalización:</strong> <span id="resumen_fecha_finalizacion">-</span></p>
            </div>
        </div>
        
        <button type="submit" name="accion" value="liquidar" class="btn btn-danger">
            <i class="bi bi-cash-coin"></i> Liquidar
        </button>
        <a href="gestion_pago.php?ruta=<?= $ruta_actual ?>&indice=<?= $indice_actual ?>" class="btn btn-secondary">
            <i class="bi bi-x-circle"></i> Cancelar
        </a>
    </form>
</div>

<script>
function calcularResumen() {
    const saldoPendiente = parseFloat(document.getElementById('saldo_pendiente').value.replace(/,/g, '')) || 0;
    const nuevoMonto = parseFloat(document.getElementById('nuevo_monto').value) || 0;
    const cuotas = parseInt(document.getElementById('cuotas').value);
    const seguroCheckbox = document.getElementById('seguro');
    const tipoRefinanciacion = document.querySelector('input[name="tipo_refinanciacion"]:checked').value;

// Calcular seguro
let seguro = 0;
if (seguroCheckbox.checked) {
    // Si el monto es 0, no aplica seguro
    if (nuevoMonto > 0) {
        seguro = (Math.floor((nuevoMonto - 1) / 100) + 1) * 5;
    }
    // Duplicar si son 70 cuotas
    if (cuotas === 70) {
        seguro *= 2;
    }
}
    const tasaInteres = (cuotas === 70) ? 48 : 24;
    const intereses = nuevoMonto * (tasaInteres / 100);
    
    // Calcular montos según tipo de refinanciación
    let montoEntregar = 0;
    let totalPagar = 0;
    
    if (tipoRefinanciacion === 'descontar') {
        montoEntregar = nuevoMonto - saldoPendiente - seguro;
        totalPagar = nuevoMonto + intereses;
    } else {
        montoEntregar = nuevoMonto - seguro;
        totalPagar = nuevoMonto + intereses + saldoPendiente;
    }

    // Calcular fecha finalización
    const fechaFinalizacion = new Date();
    fechaFinalizacion.setDate(fechaFinalizacion.getDate() + cuotas);
    const fechaFormateada = fechaFinalizacion.toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' });

    // Actualizar resumen
    document.getElementById('resumen_nuevo_monto').textContent = `$${nuevoMonto.toFixed(2)}`;
    document.getElementById('resumen_tipo_refinanciacion').textContent = tipoRefinanciacion === 'descontar' ? 'Descontar saldo' : 'Sumar saldo';
    document.getElementById('resumen_seguro').textContent = `$${seguro.toFixed(2)}`;
    document.getElementById('resumen_entregar').textContent = `$${montoEntregar.toFixed(2)}`;
    document.getElementById('resumen_intereses').textContent = `$${intereses.toFixed(2)}`;
    document.getElementById('resumen_total').textContent = `$${totalPagar.toFixed(2)}`;
    document.getElementById('resumen_fecha_finalizacion').textContent = fechaFormateada;
}

function validarFormulario() {
    const nuevoMonto = parseFloat(document.getElementById('nuevo_monto').value) || 0;
    const cuotas = parseInt(document.getElementById('cuotas').value);
    const frecuenciaPago = document.getElementById('frecuencia_pago').value;
    const tipoRefinanciacion = document.querySelector('input[name="tipo_refinanciacion"]:checked').value;
    const saldoPendiente = parseFloat(document.getElementById('saldo_pendiente').value.replace(/,/g, '')) || 0;

    if (nuevoMonto <= 0) {
        alert("El monto del crédito debe ser mayor a 0.");
        return false;
    }

    if ((cuotas == 40 || cuotas == 70) && frecuenciaPago == 'mensual') {
        alert("Las cuotas de 40 y 70 días no pueden tener frecuencia mensual.");
        return false;
    }

    if (tipoRefinanciacion === 'descontar') {
        const seguroCheckbox = document.getElementById('seguro');
        let seguro = 0;
        if (seguroCheckbox.checked) {
            seguro = (Math.floor((nuevoMonto - 1) / 100) + 1) * 5;
            if (cuotas === 70) seguro *= 2;
        }
        
        if (nuevoMonto <= (saldoPendiente + seguro)) {
            alert("El monto debe ser mayor al saldo pendiente más el seguro.");
            return false;
        }
    }

    return true;
}

window.onload = calcularResumen;
</script>

<?php include 'includes/footer.php'; ?>