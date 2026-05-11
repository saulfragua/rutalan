<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: origin, X-Requested-With, Content-Type, Accept");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

require_once "../config/conexion.php";
require_once "../models/creditosModelos.php";
require_once "../models/clientesModelos.php";
require_once "../models/planPagosModelos.php";
require_once "../models/cajasModelos.php";
require_once "../services/whatsapp_service.php";

$control = $_GET['control'] ?? '';
$creditos = new Creditos($conexion);
$clientes = new Clientes($conexion);
$planPagos = new PlanPagos($conexion);
$cajas = new Cajas($conexion);
// Inicializar WhatsAppService (si falla, no bloquea el flujo)
$whatsappService = null;
try {
    $whatsappService = new WhatsAppService();
} catch (Exception $e) {
    error_log("Advertencia: No se pudo inicializar WhatsAppService: " . $e->getMessage());
}

switch ($control) {
    case 'consultar':
        header("Content-Type: application/json");
        $vec = $creditos->consultar();
        echo json_encode($vec);
        break;

    case 'buscar':
        header("Content-Type: application/json");
        try {
            $termino = $_GET['termino'] ?? '';
            if (!empty($termino)) {
                $vec = $creditos->buscar(trim($termino));
            } else {
                $vec = $creditos->consultar();
            }
            echo json_encode($vec);
        } catch (Exception $e) {
            error_log("Error en buscar créditos: " . $e->getMessage());
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "Error al buscar créditos: " . $e->getMessage()
            ]);
        }
        break;

    case 'consultarPorId':
        header("Content-Type: application/json");
        $id = $_GET['id'] ?? null;
        if ($id) {
            $vec = $creditos->consultarPorId($id);
            if ($vec && isset($vec['id_credito'])) {
                echo json_encode($vec);
            } else {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "Crédito no encontrado"
                ]);
            }
        } else {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID no proporcionado"
            ]);
        }
        break;

    case 'tienePagos':
        header("Content-Type: application/json");
        $id = $_GET['id'] ?? null;
        if ($id) {
            $tienePagos = $creditos->tienePagos($id);
            echo json_encode([
                "tiene_pagos" => $tienePagos
            ]);
        } else {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID no proporcionado"
            ]);
        }
        break;

    case 'clienteTieneCreditoPendiente':
        header("Content-Type: application/json");
        $idCliente = $_GET['id_cliente'] ?? null;
        if ($idCliente) {
            $resultado = $creditos->clienteTieneCreditoPendiente($idCliente);
            echo json_encode([
                "tiene_credito_pendiente" => $resultado['tiene_credito'],
                "creditos" => $resultado['creditos']
            ]);
        } else {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID de cliente no proporcionado"
            ]);
        }
        break;

    case 'insertar':
        header("Content-Type: application/json");
        
        try {
            $json = file_get_contents('php://input');
            $params = json_decode($json, true);

            // Validar campos obligatorios
            if (empty($params['id_cliente']) || empty($params['monto_credito']) || 
                empty($params['cuotas']) || empty($params['frecuencia_pago'])) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "Faltan campos obligatorios"
                ]);
                exit;
            }

            $montoCredito = floatval($params['monto_credito']);
            $cuotas = intval($params['cuotas']);
            $frecuenciaPago = $params['frecuencia_pago'];

            // Validar monto y cuotas
            if ($montoCredito <= 0 || $cuotas <= 0) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "El monto y las cuotas deben ser mayores a 0"
                ]);
                exit;
            }

            // Validar que las cuotas de 40 y 70 días no tengan frecuencia mensual
            if (($cuotas == 40 || $cuotas == 70) && $frecuenciaPago == 'mensual') {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "Las cuotas de 40 y 70 días no pueden tener una frecuencia de pago mensual"
                ]);
                exit;
            }

            // Los clientes pueden tener múltiples créditos activos
            // Se eliminó la validación que impedía crear créditos si el cliente ya tenía uno pendiente

            // Calcular seguro
            $seguro = 0;
            if (isset($params['incluir_seguro']) && $params['incluir_seguro']) {
                $seguro = $creditos->calcularSeguro($montoCredito, $cuotas);
            }

            // Calcular intereses y saldo
            $tasaInteres = $creditos->calcularTasaInteres($cuotas);
            $intereses = $montoCredito * ($tasaInteres / 100);
            $saldoActual = $montoCredito + $intereses;

            // Fechas
            $fechaTomaCredito = date('Y-m-d');
            $horaTomaCredito = date('H:i:s');
            $fechaFinalizaCredito = date('Y-m-d', strtotime("+$cuotas days"));

            // Validar y obtener id_usuario
            $idUsuario = null;
            $idCaja = null;
            
            if (!empty($params['id_usuario'])) {
                $idUsuarioParam = intval($params['id_usuario']);
                // Verificar si el usuario existe en la base de datos
                $stmtUsuario = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = :id_usuario");
                $stmtUsuario->bindParam(":id_usuario", $idUsuarioParam, PDO::PARAM_INT);
                $stmtUsuario->execute();
                $usuarioExiste = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
                
                if ($usuarioExiste) {
                    $idUsuario = $idUsuarioParam;
                    
                    // Obtener la caja abierta del usuario para asociar el crédito
                    $cajaAbierta = $cajas->obtenerCajaAbierta($idUsuario);
                    if ($cajaAbierta && isset($cajaAbierta['id_caja'])) {
                        $idCaja = (int)$cajaAbierta['id_caja'];
                    }
                } else {
                    // Si el usuario no existe, establecer a NULL (la columna permite NULL)
                    $idUsuario = null;
                }
            }

            // Obtener id_ruta del cliente (asignada al cliente)
            $idRuta = null;
            if (!empty($params['id_ruta'])) {
                $idRutaParam = intval($params['id_ruta']);
                // Verificar si la ruta existe en la base de datos
                $stmtRuta = $conexion->prepare("SELECT id_ruta FROM rutas WHERE id_ruta = :id_ruta");
                $stmtRuta->bindParam(":id_ruta", $idRutaParam, PDO::PARAM_INT);
                $stmtRuta->execute();
                $rutaExiste = $stmtRuta->fetch(PDO::FETCH_ASSOC);
                
                if ($rutaExiste) {
                    $idRuta = $idRutaParam;
                } else {
                    // Si la ruta no existe, establecer a NULL (la columna permite NULL)
                    $idRuta = null;
                }
            } else {
                // Si no se proporciona id_ruta, intentar obtenerlo del cliente
                $stmtClienteRuta = $conexion->prepare("SELECT id_ruta FROM clientes WHERE id_cliente = :id_cliente");
                $stmtClienteRuta->bindParam(":id_cliente", $params['id_cliente'], PDO::PARAM_INT);
                $stmtClienteRuta->execute();
                $clienteRuta = $stmtClienteRuta->fetch(PDO::FETCH_ASSOC);
                
                if ($clienteRuta && !empty($clienteRuta['id_ruta'])) {
                    $idRuta = (int)$clienteRuta['id_ruta'];
                }
            }

            $paramsCredito = [
                'id_cliente' => intval($params['id_cliente']),
                'monto_credito' => $montoCredito,
                'cuotas' => $cuotas,
                'tasa_interes' => $tasaInteres,
                'frecuencia_pago' => $frecuenciaPago,
                'seguro' => $seguro,
                'saldo_actual' => $saldoActual,
                'fecha_toma_credito' => $fechaTomaCredito,
                'hora_toma_credito' => $horaTomaCredito,
                'fecha_finaliza_credito' => $fechaFinalizaCredito,
                'id_usuario' => $idUsuario, // Puede ser null
                'id_ruta' => $idRuta, // Puede ser null
                'id_caja' => $idCaja // Puede ser null si no hay caja abierta
            ];

            try {
                $resultado = $creditos->insertar($paramsCredito);
                $idCredito = $creditos->obtenerUltimoId();
                
                if (!$idCredito || $idCredito <= 0) {
                    throw new Exception("Error al obtener el ID del crédito insertado");
                }

                // Intentar crear plan de pagos (si falla, el crédito ya está guardado)
                try {
                    $planPagos->crearPlanPagos($idCredito, $montoCredito, $intereses, $cuotas, $frecuenciaPago, $fechaTomaCredito);
                    // Si se creó correctamente, agregar mensaje de éxito
                    if (!isset($resultado['advertencia'])) {
                        $resultado['mensaje'] = ($resultado['mensaje'] ?? 'Crédito creado correctamente') . ' Plan de pagos generado exitosamente.';
                    }
                } catch (Exception $ePlan) {
                    // Si falla el plan de pagos, registrar el error pero no fallar la inserción del crédito
                    error_log("Error al crear plan de pagos para crédito $idCredito: " . $ePlan->getMessage());
                    error_log("Stack trace: " . $ePlan->getTraceAsString());
                    // Agregar advertencia al mensaje pero mantener el resultado como éxito
                    $resultado['advertencia'] = "Crédito creado correctamente, pero hubo un problema al crear el plan de pagos: " . $ePlan->getMessage();
                    $resultado['error_plan_pagos'] = $ePlan->getMessage();
                }

                // Log para depuración
                error_log("🟢 CRÉDITO - ===== INICIO VERIFICACIÓN WHATSAPP =====");
                error_log("🟢 CRÉDITO - Resultado completo: " . json_encode($resultado));
                error_log("🟢 CRÉDITO - ID Crédito obtenido: " . $idCredito);
                error_log("🟢 CRÉDITO - Resultado['resultado']: " . ($resultado['resultado'] ?? 'NO DEFINIDO'));
                error_log("🟢 CRÉDITO - WhatsApp Service disponible: " . ($whatsappService !== null ? "SÍ" : "NO"));

                // Enviar mensaje de WhatsApp si el crédito fue creado exitosamente
                // IMPORTANTE: El modelo retorna 'success' cuando se inserta correctamente
                // Verificar tanto 'ok' como 'success' para compatibilidad
                $resultadoExitoso = isset($resultado['resultado']) && 
                                   ($resultado['resultado'] === 'ok' || $resultado['resultado'] === 'success');
                
                if ($resultadoExitoso && $idCredito && $idCredito > 0) {
                    error_log("✅ CRÉDITO - Condiciones cumplidas para enviar WhatsApp");
                    error_log("🟢 CRÉDITO - Intentando enviar WhatsApp para crédito ID: " . $idCredito);
                    error_log("🟢 CRÉDITO - Tipo de crédito: común");
                    
                    if ($whatsappService !== null) {
                        try {
                            error_log("📤 CRÉDITO - Llamando a enviarConfirmacionCredito($idCredito)");
                            // Intentar enviar el mensaje de confirmación
                            $enviado = $whatsappService->enviarConfirmacionCredito($idCredito);
                            
                            if ($enviado) {
                                error_log("✅ CRÉDITO - WhatsApp enviado exitosamente para crédito ID: " . $idCredito);
                            } else {
                                error_log("⚠️ CRÉDITO - WhatsApp NO se pudo enviar para crédito ID: " . $idCredito);
                                error_log("⚠️ CRÉDITO - Posibles causas:");
                                error_log("   1. WhatsApp no está conectado");
                                error_log("   2. El cliente no tiene teléfono registrado");
                                error_log("   3. El servicio de WhatsApp no está disponible");
                                error_log("   4. Error en el formato del número de teléfono");
                            }
                        } catch (Exception $e) {
                            error_log("❌ CRÉDITO - Excepción al enviar WhatsApp: " . $e->getMessage());
                            error_log("❌ CRÉDITO - Stack trace: " . $e->getTraceAsString());
                            // No lanzar la excepción para que el crédito se guarde correctamente
                        }
                    } else {
                        error_log("⚠️ CRÉDITO - WhatsApp Service no está disponible (no se pudo inicializar)");
                        error_log("⚠️ CRÉDITO - Verificar que WhatsAppService se haya inicializado correctamente");
                    }
                } else {
                    error_log("⚠️ CRÉDITO - No se puede enviar WhatsApp");
                    error_log("⚠️ CRÉDITO - Resultado exitoso: " . ($resultadoExitoso ? "SÍ" : "NO"));
                    error_log("⚠️ CRÉDITO - ID Crédito válido: " . ($idCredito && $idCredito > 0 ? "SÍ ($idCredito)" : "NO"));
                    error_log("⚠️ CRÉDITO - Resultado del modelo: " . ($resultado['resultado'] ?? 'desconocido'));
                }
                
                error_log("🟢 CRÉDITO - ===== FIN VERIFICACIÓN WHATSAPP =====");

                echo json_encode($resultado);
            } catch (Exception $e) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "Error al insertar crédito: " . $e->getMessage()
                ]);
            }

        } catch (Exception $e) {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "Error al procesar: " . $e->getMessage()
            ]);
        }
        break;

    case 'editar':
        header("Content-Type: application/json");
        
        try {
            $idCredito = $_GET['id'] ?? null;
            if (!$idCredito) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "ID no proporcionado"
                ]);
                exit;
            }

            // Verificar si tiene pagos
            if ($creditos->tienePagos($idCredito)) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "No se puede modificar el crédito porque ya tiene pagos registrados"
                ]);
                exit;
            }

            $json = file_get_contents('php://input');
            $params = json_decode($json, true);

            $montoCredito = floatval($params['monto_credito']);
            $cuotas = intval($params['cuotas']);
            $frecuenciaPago = $params['frecuencia_pago'];

            // Validaciones
            if ($montoCredito <= 0 || $cuotas <= 0) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "El monto y las cuotas deben ser mayores a 0"
                ]);
                exit;
            }

            if (($cuotas == 40 || $cuotas == 70) && $frecuenciaPago == 'mensual') {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "Las cuotas de 40 y 70 días no pueden tener una frecuencia de pago mensual"
                ]);
                exit;
            }

            // Obtener crédito original
            $creditoOriginal = $creditos->consultarPorId($idCredito);
            if (!$creditoOriginal) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "Crédito no encontrado"
                ]);
                exit;
            }

            // Obtener fecha_toma_credito: usar la nueva si se proporciona, sino mantener la original
            $fechaTomaCredito = isset($params['fecha_toma_credito']) && !empty($params['fecha_toma_credito'])
                ? $params['fecha_toma_credito']
                : $creditoOriginal['fecha_toma_credito'];
            
            // Obtener hora_toma_credito: usar la nueva si se proporciona, sino mantener la original
            $horaTomaCredito = isset($params['hora_toma_credito']) && !empty($params['hora_toma_credito'])
                ? $params['hora_toma_credito']
                : ($creditoOriginal['hora_toma_credito'] ?? date('H:i:s'));

            // Calcular seguro
            $seguro = 0;
            if (isset($params['incluir_seguro']) && $params['incluir_seguro']) {
                $seguro = $creditos->calcularSeguro($montoCredito, $cuotas);
            }

            // Calcular intereses y saldo
            $tasaInteres = $creditos->calcularTasaInteres($cuotas);
            $intereses = $montoCredito * ($tasaInteres / 100);
            $saldoActual = $montoCredito + $intereses;
            
            // Calcular fecha_finaliza_credito basada en la nueva fecha_toma_credito
            $fechaFinalizaCredito = date('Y-m-d', strtotime($fechaTomaCredito . " +$cuotas days"));

            $paramsCredito = [
                'id_cliente' => intval($params['id_cliente']),
                'monto_credito' => $montoCredito,
                'cuotas' => $cuotas,
                'tasa_interes' => $tasaInteres,
                'frecuencia_pago' => $frecuenciaPago,
                'seguro' => $seguro,
                'saldo_actual' => $saldoActual,
                'fecha_finaliza_credito' => $fechaFinalizaCredito,
                'fecha_toma_credito' => $fechaTomaCredito,
                'hora_toma_credito' => $horaTomaCredito
            ];

            // Eliminar plan de pagos anterior
            $planPagos->eliminarPorIdCredito($idCredito);

            // Actualizar crédito
            $resultado = $creditos->editar($idCredito, $paramsCredito);

            // Crear nuevo plan de pagos con la nueva fecha
            $planPagos->crearPlanPagos($idCredito, $montoCredito, $intereses, $cuotas, $frecuenciaPago, $fechaTomaCredito);

            echo json_encode($resultado);

        } catch (Exception $e) {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "Error al procesar: " . $e->getMessage()
            ]);
        }
        break;

    case 'eliminar':
        header("Content-Type: application/json");
        $id = $_GET['id'] ?? null;
        if ($id) {
            $vec = $creditos->eliminar($id);
            echo json_encode($vec);
        } else {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID no proporcionado"
            ]);
        }
        break;

    case 'cancelar':
        header("Content-Type: application/json");
        
        try {
            $json = file_get_contents('php://input');
            $params = json_decode($json, true);
            
            if (!isset($params['id_credito']) || !isset($params['id_usuario'])) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "Faltan parámetros requeridos (id_credito, id_usuario)"
                ]);
                exit;
            }
            
            $idCredito = (int)$params['id_credito'];
            $idUsuario = (int)$params['id_usuario'];
            
            // Validar que el usuario existe
            $stmtUsuario = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = :id_usuario AND estado = 1");
            $stmtUsuario->bindParam(":id_usuario", $idUsuario, PDO::PARAM_INT);
            $stmtUsuario->execute();
            $usuarioExiste = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
            
            if (!$usuarioExiste) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "El usuario no existe o está inactivo"
                ]);
                exit;
            }
            
            // Validar que el crédito existe y está activo
            $stmtCredito = $conexion->prepare("SELECT id_credito, estado_credito FROM creditos WHERE id_credito = :id_credito AND activo = 1");
            $stmtCredito->bindParam(":id_credito", $idCredito, PDO::PARAM_INT);
            $stmtCredito->execute();
            $creditoExiste = $stmtCredito->fetch(PDO::FETCH_ASSOC);
            
            if (!$creditoExiste) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "El crédito no existe o ya está inactivo"
                ]);
                exit;
            }
            
            if ($creditoExiste['estado_credito'] === 'cancelado') {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "El crédito ya está cancelado"
                ]);
                exit;
            }
            
            // Cancelar el crédito
            $resultado = $creditos->cancelar($idCredito, $idUsuario);
            echo json_encode($resultado);
            
        } catch (Exception $e) {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "Error al cancelar el crédito: " . $e->getMessage()
            ]);
        }
        break;

    case 'refinanciar_automatico':
        header("Content-Type: application/json");
        // DESACTIVADO: Sistema de refinanciación automático desactivado
        echo json_encode([
            "resultado" => "desactivado",
            "mensaje" => "El sistema de refinanciación automático está desactivado"
        ]);
        break;

    default:
        header("Content-Type: application/json");
        echo json_encode([
            "resultado" => "error",
            "mensaje" => "Control no válido"
        ]);
        break;
}

?>
