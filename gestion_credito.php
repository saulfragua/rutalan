<?php
include 'includes/header.php';
include 'includes/conexion.php';
include 'includes/permisos.php';
include 'whatsapp_service.php'; // Incluir el servicio de WhatsApp

// Establecer la zona horaria
date_default_timezone_set('America/Bogota');
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es');

session_start();
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['admin', 'cobrador'])) {
    header("Location: login.php");
    exit();
}

$accion = $_GET['accion'] ?? 'crear';
$id_credito = $_GET['id'] ?? null;

// Obtener clientes activos
$clientes = $conexion->query("SELECT * FROM clientes WHERE activo = 1");

// Verificar si el crédito ha recibido pagos
$tiene_pagos = false;
if ($accion === 'editar' && $id_credito) {
    $stmt_pagos = $conexion->prepare("SELECT COUNT(*) AS total_pagos FROM pagos WHERE id_credito = ?");
    $stmt_pagos->bind_param("i", $id_credito);
    $stmt_pagos->execute();
    $result_pagos = $stmt_pagos->get_result()->fetch_assoc();
    $tiene_pagos = ($result_pagos['total_pagos'] > 0);
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['eliminar_credito'])) {
        // Verificar si el crédito tiene pagos
        if ($tiene_pagos) {
            echo "<div class='alert alert-danger'>No se puede eliminar el crédito porque ya tiene pagos registrados.</div>";
        } else {
            // Eliminar primero los registros relacionados en planpagos
            $stmt_eliminar_planpagos = $conexion->prepare("DELETE FROM planpagos WHERE id_credito = ?");
            $stmt_eliminar_planpagos->bind_param("i", $id_credito);
            if ($stmt_eliminar_planpagos->execute()) {
                // Luego eliminar el crédito
                $stmt_eliminar_credito = $conexion->prepare("DELETE FROM creditos WHERE id_credito = ?");
                $stmt_eliminar_credito->bind_param("i", $id_credito);
                if ($stmt_eliminar_credito->execute()) {
                    header("Location: creditos.php");
                    exit();
                } else {
                    echo "<div class='alert alert-danger'>Error al eliminar el crédito.</div>";
                }
            } else {
                echo "<div class='alert alert-danger'>Error al eliminar los registros relacionados en planpagos.</div>";
            }
        }
    } else {
        // Verificar si el crédito tiene pagos
        if ($tiene_pagos) {
            echo "<div class='alert alert-danger'>No se puede modificar el crédito porque ya tiene pagos registrados.</div>";
        } else {
            // En modo edición, mantener el id_cliente original si el campo está deshabilitado
            $id_cliente = ($accion === 'editar' && isset($_POST['id_cliente_original'])) ? 
                          $_POST['id_cliente_original'] : $_POST['id_cliente'];
            
            $monto_credito = floatval($_POST['monto_credito']);
            $cuotas = intval($_POST['cuotas']);
            $frecuencia_pago = $_POST['frecuencia_pago'];
            
            // Calcular seguro según los rangos especificados
            $seguro = 0;
            if (isset($_POST['seguro'])) {
                if ($monto_credito >= 0 && $monto_credito <= 100) {
                    $seguro = 5;
                } elseif ($monto_credito >= 101 && $monto_credito <= 200) {
                    $seguro = 10;
                } elseif ($monto_credito >= 201 && $monto_credito <= 300) {
                    $seguro = 15;
                } elseif ($monto_credito >= 301 && $monto_credito <= 400) {
                    $seguro = 20;
                } elseif ($monto_credito >= 401 && $monto_credito <= 500) {
                    $seguro = 25;
                } elseif ($monto_credito >= 501 && $monto_credito <= 600) {
                    $seguro = 30;
                } elseif ($monto_credito >= 601 && $monto_credito <= 700) {
                    $seguro = 35;
                } elseif ($monto_credito >= 701 && $monto_credito <= 800) {
                    $seguro = 40;
                } elseif ($monto_credito >= 801 && $monto_credito <= 900) {
                    $seguro = 45;
                } elseif ($monto_credito >= 901 && $monto_credito <= 1000) {
                    $seguro = 50;
                } else {
                    // Para montos mayores a 1000, aplicar $50 + $5 por cada $100 adicionales
                    $seguro = 50 + (floor(($monto_credito - 1000) / 100) * 5);
                }
                
                // Doble del seguro si el crédito es de 70 días
                if ($cuotas == 70) {
                    $seguro *= 2;
                }
            }

            // Validar que el monto y las cuotas sean mayores a 0
            if ($monto_credito <= 0 || $cuotas <= 0) {
                die("<div class='alert alert-danger'>El monto y las cuotas deben ser mayores a 0.</div>");
            }

            // Validar que las cuotas de 40 y 70 días no tengan frecuencia de pago mensual
            if (($cuotas == 40 || $cuotas == 70) && $frecuencia_pago == 'mensual') {
                die("<div class='alert alert-danger'>Las cuotas de 40 y 70 días no pueden tener una frecuencia de pago mensual.</div>");
            }

            // Verificar si el cliente tiene un crédito pendiente (solo para creación)
            if ($accion === 'crear') {
                $stmt = $conexion->prepare("SELECT id_credito FROM creditos WHERE id_cliente = ? AND saldo_actual > 0 AND activo = 1");
                $stmt->bind_param("i", $id_cliente);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    echo "<div class='alert alert-danger'>El cliente tiene un crédito pendiente.</div>";
                    $mostrar_formulario = false;
                }
            }

            if (!isset($mostrar_formulario) || $mostrar_formulario !== false) {
                // Calcular intereses y saldo actual
                $tasa_interes = ($cuotas == 70) ? 48 : 24;
                $intereses = $monto_credito * ($tasa_interes / 100);
                $saldo_actual = $monto_credito + $intereses;
                $fecha_toma_credito = date('Y-m-d');
                $hora_toma_credito = date('H:i:s');
                $fecha_finaliza_credito = date('Y-m-d', strtotime("+$cuotas days"));
                $monto_entregar = $monto_credito - $seguro;
                $id_usuario = $_SESSION['id_usuario'];

                // Obtener la ruta del usuario
                $stmt_ruta = $conexion->prepare("SELECT id_ruta FROM usuarios WHERE id_usuario = ?");
                $stmt_ruta->bind_param("i", $id_usuario);
                $stmt_ruta->execute();
                $id_ruta = $stmt_ruta->get_result()->fetch_assoc()['id_ruta'];

                // Insertar o actualizar el crédito
                if ($accion === 'crear') {
                    $sql = "INSERT INTO creditos (id_cliente, monto_credito, cuotas, tasa_interes, frecuencia_pago, 
                            seguro, saldo_actual, fecha_toma_credito, hora_toma_credito, fecha_finaliza_credito, 
                            id_usuario, id_ruta, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                } elseif ($accion === 'editar' && $id_credito) {
                    $sql = "UPDATE creditos SET id_cliente = ?, monto_credito = ?, cuotas = ?, tasa_interes = ?, 
                            frecuencia_pago = ?, seguro = ?, saldo_actual = ?, fecha_finaliza_credito = ? WHERE id_credito = ?";
                }

                $stmt = $conexion->prepare($sql);
                if ($accion === 'crear') {
                    $stmt->bind_param("ididsdssssii", $id_cliente, $monto_credito, $cuotas, $tasa_interes, $frecuencia_pago, 
                                      $seguro, $saldo_actual, $fecha_toma_credito, $hora_toma_credito, $fecha_finaliza_credito, 
                                      $id_usuario, $id_ruta);
                } else {
                    $stmt->bind_param("ididsdssi", $id_cliente, $monto_credito, $cuotas, $tasa_interes, $frecuencia_pago, 
                                      $seguro, $saldo_actual, $fecha_finaliza_credito, $id_credito);
                }

                if ($stmt->execute()) {
                    if ($accion === 'crear') {
                        $id_credito = $conexion->insert_id;
                        
                        // ENVIAR MENSAJE DE WHATSAPP - NUEVO CRÉDITO CREADO
                        try {
                            $whatsappService = new WhatsAppService();
                            $whatsappService->enviarConfirmacionCredito($id_credito);
                        } catch (Exception $e) {
                            // No mostrar error al usuario si falla el WhatsApp, solo log
                            error_log("Error al enviar WhatsApp: " . $e->getMessage());
                        }
                    }

                    // Eliminar el plan de pagos existente si estamos editando
                    if ($accion === 'editar') {
                        $stmt_eliminar_planpagos = $conexion->prepare("DELETE FROM planpagos WHERE id_credito = ?");
                        $stmt_eliminar_planpagos->bind_param("i", $id_credito);
                        if (!$stmt_eliminar_planpagos->execute()) {
                            echo "<div class='alert alert-danger'>Error al eliminar el plan de pagos anterior.</div>";
                            exit();
                        }
                    }

                    // Crear el nuevo plan de pagos
                    $total_a_pagar = $monto_credito + $intereses;
                    $numero_pagos = match ($frecuencia_pago) {
                        'diario' => $cuotas,
                        'semanal' => ceil($cuotas / 8),
                        'quincenal' => ceil($cuotas / 16),
                        'mensual' => ceil($cuotas / 31),
                    };

                    $monto_cuota = round($total_a_pagar / $numero_pagos, 2);
                    $fecha_pago = $accion === 'crear' ? $fecha_toma_credito : ($credito['fecha_toma_credito'] ?? $fecha_toma_credito);

                    // Insertar cuotas en el plan de pagos
                    $error_cuotas = false;
                    for ($i = 1; $i <= $numero_pagos; $i++) {
                        $fecha_pago = match ($frecuencia_pago) {
                            'diario' => date('Y-m-d', strtotime("$fecha_pago +1 days")),
                            'semanal' => date('Y-m-d', strtotime("$fecha_pago +1 weeks")),
                            'quincenal' => date('Y-m-d', strtotime("$fecha_pago +15 days")),
                            'mensual' => date('Y-m-d', strtotime("$fecha_pago +1 months")),
                        };

                        $stmt_cuota = $conexion->prepare("INSERT INTO planpagos (id_credito, numero_cuota, monto_cuota, monto_restante, fecha_pago, estado) 
                                                          VALUES (?, ?, ?, ?, ?, 'pendiente')");
                        $stmt_cuota->bind_param("iidss", $id_credito, $i, $monto_cuota, $monto_cuota, $fecha_pago);
                        if (!$stmt_cuota->execute()) {
                            $error_cuotas = true;
                            break;
                        }
                    }

                    if ($error_cuotas) {
                        echo "<div class='alert alert-danger'>Error al generar el plan de pagos.</div>";
                        $conexion->rollback();
                    } else {
                        // Mostrar mensaje de éxito
                        if ($accion === 'crear') {
                            echo "<div class='alert alert-success'>Crédito creado exitosamente. Se ha enviado un mensaje de confirmación por WhatsApp al cliente.</div>";
                        } else {
                            echo "<div class='alert alert-success'>Crédito actualizado exitosamente.</div>";
                        }
                        
                        // Redirigir después de 3 segundos
                        echo "<script>
                            setTimeout(function() {
                                window.location.href = 'creditos.php';
                            }, 3000);
                        </script>";
                    }
                } else {
                    echo "<div class='alert alert-danger'>Error al guardar el crédito.</div>";
                }
            }
        }
    }
}

// Obtener datos para edición
if ($accion === 'editar' && $id_credito) {
    $stmt = $conexion->prepare("SELECT * FROM creditos WHERE id_credito = ?");
    $stmt->bind_param("i", $id_credito);
    $stmt->execute();
    $credito = $stmt->get_result()->fetch_assoc();
    
    if (!$credito) {
        header("Location: creditos.php");
        exit();
    }
}
?>

<div class="container mt-5">
    <h2><?= ucfirst($accion) ?> Crédito</h2>
    <!-- Botón para volver al menú principal -->
    <a href="creditos.php" class="btn btn-secondary mb-3">
        <i class="bi bi-arrow-left-circle"></i> Volver a Gestión de Créditos
    </a>

    <!-- Mostrar mensaje si el crédito tiene pagos -->
    <?php if ($tiene_pagos): ?>
        <div class="alert alert-warning">
            Este crédito no puede ser modificado ni eliminado porque ya tiene pagos registrados.
        </div>
    <?php endif; ?>

    <!-- Buscador de clientes (solo en modo de creación) -->
    <?php if ($accion === 'crear'): ?>
        <div class="mb-3">
            <label class="form-label">Buscar Cliente <i class="bi bi-search"></i></label>
            <input type="text" class="form-control" id="buscarCliente" placeholder="Buscar por nombre o apellido">
        </div>
    <?php endif; ?>

    <form method="POST" onsubmit="return validarFormulario()">
        <!-- Campo oculto para mantener el id_cliente original en modo edición -->
        <?php if ($accion === 'editar'): ?>
            <input type="hidden" name="id_cliente_original" value="<?= $credito['id_cliente'] ?>">
        <?php endif; ?>
        
        <div class="mb-3">
            <label class="form-label">Cliente <i class="bi bi-person-fill"></i></label>
            <select class="form-select" name="id_cliente" id="id_cliente" required <?= ($tiene_pagos || $accion === 'editar') ? 'disabled' : '' ?>>
                <?php while($cliente = $clientes->fetch_assoc()): ?>
                <option value="<?= $cliente['id_cliente'] ?>" <?= isset($credito) && $credito['id_cliente'] == $cliente['id_cliente'] ? 'selected' : '' ?>>
                    <?= $cliente['nombres'] . ' ' . $cliente['apellidos'] ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>

        <?php if($accion === 'editar'): ?>
        <div class="mb-3">
            <label class="form-label">Fecha de creación <i class="bi bi-calendar-date"></i></label>
            <input type="text" class="form-control" 
                   value="<?= strftime('%A %d de %B de %Y', strtotime($credito['fecha_toma_credito'])) ?>" 
                   readonly>
        </div>
        <?php endif; ?>
        
        <div class="mb-3">
            <label class="form-label">Monto del Crédito <i class="bi bi-cash-stack"></i></label>
            <input type="number" class="form-control" name="monto_credito" id="monto_credito" step="0.01" min="0.01"
                   value="<?= $credito['monto_credito'] ?? '' ?>" required oninput="calcularResumen()" <?= $tiene_pagos ? 'disabled' : '' ?>>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Plazo (Días) <i class="bi bi-clock"></i></label>
            <select class="form-select" name="cuotas" id="cuotas" required onchange="calcularResumen()" <?= $tiene_pagos ? 'disabled' : '' ?>>
                <option value="31" <?= isset($credito) && $credito['cuotas'] == 31 ? 'selected' : '' ?>>31 días</option>
                <option value="40" <?= isset($credito) && $credito['cuotas'] == 40 ? 'selected' : '' ?>>40 días</option>
                <option value="70" <?= isset($credito) && $credito['cuotas'] == 70 ? 'selected' : '' ?>>70 días</option>
            </select>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Frecuencia de Pago <i class="bi bi-calendar-week"></i></label>
            <select class="form-select" name="frecuencia_pago" id="frecuencia_pago" required <?= $tiene_pagos ? 'disabled' : '' ?>>
                <option value="diario" <?= isset($credito) && $credito['frecuencia_pago'] == 'diario' ? 'selected' : '' ?>>Diario</option>
                <option value="semanal" <?= isset($credito) && $credito['frecuencia_pago'] == 'semanal' ? 'selected' : '' ?>>Semanal</option>
                <option value="quincenal" <?= isset($credito) && $credito['frecuencia_pago'] == 'quincenal' ? 'selected' : '' ?>>Quincenal</option>
                <option value="mensual" <?= isset($credito) && $credito['frecuencia_pago'] == 'mensual' ? 'selected' : '' ?>>Mensual</option>
            </select>
        </div>
        
        <!-- Checkbox de seguro -->
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" name="seguro" id="seguro" onchange="calcularResumen()" <?= $tiene_pagos ? 'disabled' : '' ?>
                   <?= ($accion === 'crear' || (isset($credito) && $credito['seguro'] > 0)) ? 'checked' : '' ?>>
            <label class="form-check-label" for="seguro">Incluir seguro</label>
        </div>
        
        <!-- Resumen del crédito -->
        <div class="card mb-3">
            <div class="card-header">Resumen del Crédito <i class="bi bi-clipboard-check"></i></div>
            <div class="card-body">
                <p><strong>Monto del crédito:</strong> <span id="resumen_monto">$0.00</span></p>
                <p><strong>Seguro:</strong> <span id="resumen_seguro">$0.00</span></p>
                <p><strong>Monto a entregar al cliente:</strong> <span id="resumen_entregar">$0.00</span></p>
                <p><strong>Intereses:</strong> <span id="resumen_intereses">$0.00</span></p>
                <p><strong>Total a pagar:</strong> <span id="resumen_total">$0.00</span></p>
                <p><strong>Fecha de finalización:</strong> <span id="resumen_fecha_finalizacion">-</span></p>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary" <?= $tiene_pagos ? 'disabled' : '' ?>>
            <i class="bi bi-save"></i> <?= $accion === 'crear' ? 'Crear Crédito y Enviar WhatsApp' : 'Actualizar Crédito' ?>
        </button>
        <a href="creditos.php" class="btn btn-secondary"><i class="bi bi-arrow-left-circle"></i> Cancelar</a>
        
        <?php if ($accion === 'editar' && tienePermiso($_SESSION['rol'], 'creditos', 'eliminar') && !$tiene_pagos): ?>
            <button type="submit" name="eliminar_credito" class="btn btn-danger" onclick="return confirm('¿Estás seguro de que deseas eliminar este crédito?')">
                <i class="bi bi-trash"></i> Eliminar Crédito
            </button>
        <?php endif; ?>
    </form>
</div>

<script>
// Función para calcular el resumen del crédito
function calcularResumen() {
    const montoCredito = parseFloat(document.getElementById('monto_credito').value) || 0;
    const cuotas = parseInt(document.getElementById('cuotas').value);
    const seguroCheckbox = document.getElementById('seguro');

    // Calcular seguro según los rangos especificados
    let seguro = 0;
    if (seguroCheckbox.checked) {
        if (montoCredito >= 0 && montoCredito <= 100) {
            seguro = 5;
        } else if (montoCredito >= 101 && montoCredito <= 200) {
            seguro = 10;
        } else if (montoCredito >= 201 && montoCredito <= 300) {
            seguro = 15;
        } else if (montoCredito >= 301 && montoCredito <= 400) {
            seguro = 20;
        } else if (montoCredito >= 401 && montoCredito <= 500) {
            seguro = 25;
        } else if (montoCredito >= 501 && montoCredito <= 600) {
            seguro = 30;
        } else if (montoCredito >= 601 && montoCredito <= 700) {
            seguro = 35;
        } else if (montoCredito >= 701 && montoCredito <= 800) {
            seguro = 40;
        } else if (montoCredito >= 801 && montoCredito <= 900) {
            seguro = 45;
        } else if (montoCredito >= 901 && montoCredito <= 1000) {
            seguro = 50;
        } else {
            // Para montos mayores a 1000, aplicar $50 + $5 por cada $100 adicionales
            seguro = 50 + (Math.floor((montoCredito - 1000) / 100) * 5);
        }
        
        // Doble del seguro si el crédito es de 70 días
        if (cuotas === 70) {
            seguro *= 2;
        }
    }

    const tasaInteres = (cuotas === 70) ? 48 : 24;
    const intereses = montoCredito * (tasaInteres / 100);
    const montoEntregar = montoCredito - seguro;
    const totalPagar = montoCredito + intereses;

    // Calcular fecha de finalización
    const fechaActual = new Date();
    const fechaFinalizacion = new Date(fechaActual);
    fechaFinalizacion.setDate(fechaActual.getDate() + cuotas);
    const fechaFormateada = fechaFinalizacion.toLocaleDateString('es-ES', { 
        weekday: 'long', 
        day: 'numeric', 
        month: 'long', 
        year: 'numeric' 
    });

    // Actualizar resumen
    document.getElementById('resumen_monto').textContent = `$${montoCredito.toFixed(2)}`;
    document.getElementById('resumen_seguro').textContent = `$${seguro.toFixed(2)}`;
    document.getElementById('resumen_entregar').textContent = `$${montoEntregar.toFixed(2)}`;
    document.getElementById('resumen_intereses').textContent = `$${intereses.toFixed(2)}`;
    document.getElementById('resumen_total').textContent = `$${totalPagar.toFixed(2)}`;
    document.getElementById('resumen_fecha_finalizacion').textContent = fechaFormateada;
}

// Función para buscar clientes (solo en modo de creación)
<?php if ($accion === 'crear'): ?>
document.getElementById('buscarCliente').addEventListener('input', function() {
    const busqueda = this.value.toLowerCase();
    const opciones = document.querySelectorAll('#id_cliente option');
    opciones.forEach(opcion => {
        const texto = opcion.textContent.toLowerCase();
        opcion.style.display = texto.includes(busqueda) ? 'block' : 'none';
    });
});
<?php endif; ?>

// Función para validar el formulario
function validarFormulario() {
    const montoCredito = parseFloat(document.getElementById('monto_credito').value) || 0;
    const cuotas = parseInt(document.getElementById('cuotas').value);
    const frecuenciaPago = document.getElementById('frecuencia_pago').value;

    if (montoCredito <= 0) {
        alert("El monto del crédito debe ser mayor a 0.");
        return false;
    }

    // Validar que las cuotas de 40 y 70 días no tengan frecuencia de pago mensual
    if ((cuotas == 40 || cuotas == 70) && frecuenciaPago == 'mensual') {
        alert("Las cuotas de 40 y 70 días no pueden tener una frecuencia de pago mensual.");
        return false;
    }

    return true;
}

window.onload = calcularResumen;
</script>

<?php include 'includes/footer.php'; ?>