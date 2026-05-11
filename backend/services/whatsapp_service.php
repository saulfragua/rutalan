<?php
// whatsapp_service.php
class WhatsAppService {
    private $apiUrl;

    public function __construct() {
        // Detectar si estamos en producción o desarrollo
        // Acepta tanto rutalan.cloud como www.rutalan.cloud
        $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $isProduction = !empty($host) && (
            strpos($host, 'rutalan.cloud') !== false || 
            strpos($host, 'www.rutalan.cloud') !== false
        );
        
        if ($isProduction) {
            // En producción usar el proxy de Nginx /whatsapp-api
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $domain = $host; // Usar el dominio completo desde HTTP_HOST
            $this->apiUrl = $protocol . '://' . $domain . '/whatsapp-api/api/send-message';
        } else {
            $this->apiUrl = 'http://localhost:3000/api/send-message';
        }
        
        error_log("📱 WhatsApp Service inicializado. API URL: " . $this->apiUrl);
        error_log("📱 Entorno detectado: " . ($isProduction ? "PRODUCCIÓN" : "DESARROLLO"));
        error_log("📱 Host detectado: " . $host);
    }

    public function enviarConfirmacionPago($id_pago) {
        global $conexion;

        $sql = "SELECT p.*, c.nombres, c.apellidos, c.telefono,
                       cr.saldo_actual, cr.monto_credito, cr.id_credito,
                       r.nombre_ruta
                FROM pagos p
                JOIN clientes c ON p.id_cliente = c.id_cliente
                JOIN creditos cr ON p.id_credito = cr.id_credito
                JOIN rutas r ON p.id_ruta = r.id_ruta
                WHERE p.id_pago = ?";

        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(1, $id_pago, PDO::PARAM_INT);
        $stmt->execute();
        $pago = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pago) {
            error_log("❌ WhatsApp - No se encontró el pago con ID: $id_pago");
            return false;
        }

        // Validar que el teléfono no esté vacío
        if (empty($pago['telefono'])) {
            error_log("❌ WhatsApp - El cliente {$pago['nombres']} {$pago['apellidos']} (ID: {$pago['id_cliente']}) no tiene teléfono registrado");
            return false;
        }

        $mensaje = $this->formatearMensajePago($pago);
        return $this->enviarMensajeWhatsApp($pago['telefono'], $mensaje);
    }

    private function formatearMensajePago($pago) {
        $fecha = date('d/m/Y', strtotime($pago['fecha_pago']));
        $hora = $pago['hora_pago'];
        $monto = number_format($pago['monto_pagado'], 2);
        $saldo = number_format($pago['saldo_actual'], 2);

        return "✅ *Confirmación de Pago*\n\n" .
               "📋 *Número de Crédito:* #{$pago['id_credito']}\n" .
               "👤 *Cliente:* {$pago['nombres']} {$pago['apellidos']}\n" .
               "💵 *Monto pagado:* $$monto\n" .
               "💰 *Saldo actual:* $$saldo\n" .
               "📅 *Fecha:* $fecha\n" .
               "⏰ *Hora:* $hora\n\n" .
               "¡Gracias por su pago!";
    }

    public function enviarConfirmacionCredito($id_credito) {
        global $conexion;

        error_log("📱 WhatsApp - ===== INICIANDO ENVÍO DE CONFIRMACIÓN DE CRÉDITO =====");
        error_log("📱 WhatsApp - ID Crédito recibido: $id_credito");

        $sql = "SELECT cr.*, cl.nombres, cl.apellidos, cl.telefono,
                       cr.monto_credito, cr.cuotas, cr.tasa_interes, cr.seguro,
                       cr.fecha_toma_credito, cr.fecha_finaliza_credito, cr.frecuencia_pago,
                       cr.saldo_actual
                FROM creditos cr
                JOIN clientes cl ON cr.id_cliente = cl.id_cliente
                WHERE cr.id_credito = ?";
        
        try {
            $stmt = $conexion->prepare($sql);
            $stmt->bindValue(1, $id_credito, PDO::PARAM_INT);
            $stmt->execute();
            $credito = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$credito) {
                error_log("❌ WhatsApp - No se encontró el crédito con ID: $id_credito");
                error_log("📱 WhatsApp - ===== ENVÍO CANCELADO (crédito no encontrado) =====");
                return false;
            }

            error_log("✅ WhatsApp - Crédito encontrado:");
            error_log("   - Cliente ID: {$credito['id_cliente']}");
            error_log("   - Nombre: {$credito['nombres']} {$credito['apellidos']}");
            error_log("   - Monto: $" . number_format($credito['monto_credito'], 2));
            error_log("   - Cuotas: {$credito['cuotas']} días");

            // Validar que el teléfono no esté vacío
            if (empty($credito['telefono'])) {
                error_log("❌ WhatsApp - El cliente {$credito['nombres']} {$credito['apellidos']} (ID: {$credito['id_cliente']}) no tiene teléfono registrado");
                error_log("📱 WhatsApp - ===== ENVÍO CANCELADO (sin teléfono) =====");
                return false;
            }

            error_log("✅ WhatsApp - Teléfono encontrado: {$credito['telefono']}");
        } catch (Exception $e) {
            error_log("❌ WhatsApp - Error al consultar crédito: " . $e->getMessage());
            error_log("❌ WhatsApp - Stack trace: " . $e->getTraceAsString());
            error_log("📱 WhatsApp - ===== ENVÍO CANCELADO (error en consulta) =====");
            return false;
        }

        $intereses = $credito['monto_credito'] * ($credito['tasa_interes'] / 100);
        $total_a_pagar = $credito['monto_credito'] + $intereses;
        $monto_entregar = $credito['monto_credito'] - $credito['seguro'];

        $fecha_inicio = date('d/m/Y', strtotime($credito['fecha_toma_credito']));
        $fecha_fin = date('d/m/Y', strtotime($credito['fecha_finaliza_credito']));

        $mensaje = "🏦 *CONFIRMACIÓN DE CRÉDITO APROBADO*\n\n";
        $mensaje .= "📋 *Número de Crédito:* #{$id_credito}\n";
        $mensaje .= "👤 *Cliente:* {$credito['nombres']} {$credito['apellidos']}\n";
        $mensaje .= "💵 *Monto del crédito:* $" . number_format($credito['monto_credito'], 2) . "\n";
        if ($credito['seguro'] > 0) {
            $mensaje .= "🛡️ *Seguro:* $" . number_format($credito['seguro'], 2) . "\n";
        }
        $mensaje .= "💰 *Monto a entregar:* $" . number_format($monto_entregar, 2) . "\n";
        $mensaje .= "💸 *Total a pagar:* $" . number_format($total_a_pagar, 2) . "\n";
        $mensaje .= "📅 *Plazo:* {$credito['cuotas']} días\n";
        $mensaje .= "🔄 *Frecuencia de pago:* " . ucfirst($credito['frecuencia_pago']) . "\n";
        $mensaje .= "📅 *Fecha de inicio:* $fecha_inicio\n";
        $mensaje .= "📅 *Fecha de finalización:* $fecha_fin\n\n";
        $mensaje .= "✅ *¡Su crédito ha sido aprobado y registrado correctamente!*\n\n";

        if ($credito['seguro'] > 0) {
            $mensaje .= "🛡️ *Políticas de Seguro:*\n";
            $mensaje .= "El pago de seguro de incapacidad corresponde al préstamo adquirido.\n";

            if ($credito['cuotas'] == 31) {
                $mensaje .= "• Si su crédito es de 31 días, se pagará un máximo de 6 cuotas.\n";
            } elseif ($credito['cuotas'] == 40) {
                $mensaje .= "• Si su crédito es de 40 días, se pagará un máximo de 8 cuotas.\n";
            } elseif ($credito['cuotas'] == 70) {
                $mensaje .= "• Si su crédito es de 70 días, se pagará un máximo de 24 cuotas.\n";
            } else {
                $mensaje .= "• Para plazos diferentes, consulte con la empresa las políticas de seguro.\n";
            }

            $mensaje .= "• NOTA: Para validez debe estar al día en sus cuotas, incluyendo dominicales y feriados.\n\n";
            $mensaje .= "• *Importante:* La empresa no se hace responsable de ningún trato, acuerdo o negociación que el cliente realice directamente con el cobrador.\n\n";
        }

        $mensaje .= "💡 *Recuerde:*\n";
        $mensaje .= "• Mantenga sus pagos al día\n";
        $mensaje .= "• Cumpla con las fechas establecidas\n";
        $mensaje .= "• Ante cualquier duda, contáctenos\n\n";
        $mensaje .= "¡Gracias por confiar en nosotros!";

        error_log("📝 WhatsApp - Mensaje formateado correctamente");
        error_log("   - Longitud del mensaje: " . strlen($mensaje) . " caracteres");
        error_log("📤 WhatsApp - Llamando a enviarMensajeWhatsApp()");
        error_log("   - Teléfono destino: {$credito['telefono']}");

        $resultado = $this->enviarMensajeWhatsApp($credito['telefono'], $mensaje);
        
        if ($resultado) {
            error_log("✅ WhatsApp - Mensaje de confirmación de crédito enviado exitosamente");
            error_log("📱 WhatsApp - ===== ENVÍO COMPLETADO EXITOSAMENTE =====");
        } else {
            error_log("❌ WhatsApp - Falló el envío del mensaje de confirmación de crédito");
            error_log("📱 WhatsApp - ===== ENVÍO FALLIDO =====");
        }

        return $resultado;
    }

    public function enviarRecordatorioPago($id_credito) {
        global $conexion;

        $sql = "SELECT c.*, cr.saldo_actual
                FROM creditos cr
                JOIN clientes c ON cr.id_cliente = c.id_cliente
                WHERE cr.id_credito = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(1, $id_credito, PDO::PARAM_INT);
        $stmt->execute();
        $datos = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$datos) {
            error_log("❌ WhatsApp - No se encontró el crédito con ID: $id_credito");
            return false;
        }

        // Validar que el teléfono no esté vacío
        if (empty($datos['telefono'])) {
            error_log("❌ WhatsApp - El cliente {$datos['nombres']} {$datos['apellidos']} no tiene teléfono registrado");
            return false;
        }

        $mensaje = "Hola " . $datos['nombres'] . ",\n";
        $mensaje .= "Le recordamos que tiene un saldo pendiente de $" . number_format($datos['saldo_actual'], 2) . ".\n";
        $mensaje .= "Por favor, realice su pago a la brevedad.\n";
        $mensaje .= "¡Gracias por su preferencia!";

        return $this->enviarMensajeWhatsApp($datos['telefono'], $mensaje);
    }

    public function enviarConfirmacionRefinanciacion($id_credito_anterior, $id_nuevo_credito, $tipo_refinanciacion, $saldo_refinanciado) {
        global $conexion;

        $sql_anterior = "SELECT cr.id_credito, cr.monto_credito, cr.saldo_actual, cr.seguro, cr.tasa_interes, cr.cuotas, cr.frecuencia_pago, cr.fecha_toma_credito, cr.fecha_finaliza_credito,
                                cl.nombres, cl.apellidos, cl.telefono
                         FROM creditos cr
                         JOIN clientes cl ON cr.id_cliente = cl.id_cliente
                         WHERE cr.id_credito = ?";
        $stmt_anterior = $conexion->prepare($sql_anterior);
        $stmt_anterior->bindValue(1, $id_credito_anterior, PDO::PARAM_INT);
        $stmt_anterior->execute();
        $credito_anterior = $stmt_anterior->fetch(PDO::FETCH_ASSOC);

        $sql_nuevo = "SELECT cr.*, cl.nombres, cl.apellidos, cl.telefono
                      FROM creditos cr
                      JOIN clientes cl ON cr.id_cliente = cl.id_cliente
                      WHERE cr.id_credito = ?";
        $stmt_nuevo = $conexion->prepare($sql_nuevo);
        $stmt_nuevo->bindValue(1, $id_nuevo_credito, PDO::PARAM_INT);
        $stmt_nuevo->execute();
        $credito_nuevo = $stmt_nuevo->fetch(PDO::FETCH_ASSOC);

        if (!$credito_anterior || !$credito_nuevo) {
            error_log("❌ WhatsApp - No se encontraron los créditos. Anterior: " . ($credito_anterior ? "OK" : "NO") . ", Nuevo: " . ($credito_nuevo ? "OK" : "NO"));
            return false;
        }

        // Validar que el teléfono no esté vacío
        if (empty($credito_anterior['telefono'])) {
            error_log("❌ WhatsApp - El cliente {$credito_anterior['nombres']} {$credito_anterior['apellidos']} no tiene teléfono registrado");
            return false;
        }

        $saldo_anterior = $saldo_refinanciado;
        $intereses_nuevo = $credito_nuevo['monto_credito'] * ($credito_nuevo['tasa_interes'] / 100);
        $total_a_pagar_nuevo = $credito_nuevo['monto_credito'] + $intereses_nuevo;
        $monto_entregar_nuevo = $credito_nuevo['monto_credito'] - $credito_nuevo['seguro'] - $saldo_refinanciado;

        $fecha_inicio_nuevo = date('d/m/Y', strtotime($credito_nuevo['fecha_toma_credito']));
        $fecha_fin_nuevo = date('d/m/Y', strtotime($credito_nuevo['fecha_finaliza_credito']));

        $mensaje = "🔄 *REFINANCIACIÓN DE CRÉDITO COMPLETADA*\n\n";
        $mensaje .= "📋 *Crédito Anterior:* #{$id_credito_anterior}\n";
        $mensaje .= "📋 *Nuevo Crédito:* #{$id_nuevo_credito}\n";
        $mensaje .= "👤 *Cliente:* {$credito_anterior['nombres']} {$credito_anterior['apellidos']}\n";
        $mensaje .= "💰 *Saldo del crédito anterior:* $" . number_format($saldo_anterior, 2) . "\n";
        $mensaje .= "💰 *Monto a entregar:* $" . number_format($monto_entregar_nuevo, 2) . "\n";
        $mensaje .= "💵 *Nuevo monto:* $" . number_format($credito_nuevo['monto_credito'], 2) . "\n";

        if (!empty($credito_nuevo['seguro']) && $credito_nuevo['seguro'] > 0) {
            $mensaje .= "🛡️ *Seguro:* $" . number_format($credito_nuevo['seguro'], 2) . "\n";
            $mensaje .= "💸 *Total a pagar:* $" . number_format($total_a_pagar_nuevo, 2) . "\n";
            $mensaje .= "📅 *Plazo:* {$credito_nuevo['cuotas']} días\n";
            $mensaje .= "🔄 *Frecuencia de pago:* " . ucfirst($credito_nuevo['frecuencia_pago']) . "\n";
            $mensaje .= "📅 *Fecha de inicio:* $fecha_inicio_nuevo\n";
            $mensaje .= "📅 *Fecha de finalización:* $fecha_fin_nuevo\n";
            $mensaje .= "⚡ *Tipo de refinanciación:* " . ($tipo_refinanciacion === 'descontar' ? 'Descontar saldo' : 'Sumar saldo') . "\n\n";
            $mensaje .= "✅ *¡Su crédito ha sido refinanciado exitosamente!*\n\n";

            $dias_credito = $credito_nuevo['dias'] ?? $credito_nuevo['cuotas'] ?? 0;
            if ($dias_credito == 31) {
                $mensaje .= "🛡️ *Políticas de Seguro:*\n";
                $mensaje .= "El pago de seguro de incapacidad corresponde al préstamo refinanciado.\n";
                $mensaje .= "• Si su crédito es de 31 días, se pagará un máximo de 6 cuotas.\n";
            } elseif ($dias_credito == 40) {
                $mensaje .= "• Si su crédito es de 40 días, se pagará un máximo de 8 cuotas.\n";
            } elseif ($dias_credito == 70) {
                $mensaje .= "• Si su crédito es de 70 días, se pagará un máximo de 24 cuotas.\n";
            } else {
                $mensaje .= "• Para plazos diferentes, consulte con la empresa las políticas de seguro.\n";
            }

            $mensaje .= "• NOTA: Para validez debe estar al día en sus cuotas, incluyendo dominicales y feriados.\n\n";
            $mensaje .= "• *Importante:* La empresa no se hace responsable de ningún trato, acuerdo o negociación que el cliente realice directamente con el cobrador.\n\n";
        }

        $mensaje .= "💡 *Recuerde:*\n";
        $mensaje .= "• Mantenga sus pagos al día\n";
        $mensaje .= "• Cumpla con las fechas establecidas\n";
        $mensaje .= "• Ante cualquier duda, contáctenos\n\n";
        $mensaje .= "¡Gracias por confiar en nosotros!";

        return $this->enviarMensajeWhatsApp($credito_anterior['telefono'], $mensaje);
    }

    private function enviarMensajeWhatsApp($telefono, $mensaje) {
        // Log del número original recibido
        error_log("📱 WhatsApp - Número original recibido: " . $telefono);
        
        $telefonoFormateado = $this->formatearNumero($telefono);
        
        // Log del número formateado
        error_log("📱 WhatsApp - Número formateado: " . $telefonoFormateado);
        
        if (empty($telefonoFormateado)) {
            error_log("❌ WhatsApp - Error: El número de teléfono está vacío después del formateo");
            return false;
        }

        $data = [
            'to' => $telefonoFormateado,
            'message' => $mensaje
        ];

        // Log de los datos que se enviarán
        error_log("📤 WhatsApp - Enviando a API: " . json_encode($data));

        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($data),
                'timeout' => 3
            ]
        ];

        $context  = stream_context_create($options);

        try {
            // Detectar si estamos en producción o desarrollo
            $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
            $isProduction = !empty($host) && (
                strpos($host, 'rutalan.cloud') !== false || 
                strpos($host, 'www.rutalan.cloud') !== false
            );
            if ($isProduction) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $statusUrl = $protocol . '://' . $host . '/whatsapp-api/api/status';
            } else {
                $statusUrl = 'http://localhost:3000/api/status';
            }
            $servicioDeshabilitado = false;
            
            try {
                $statusContext = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'timeout' => 2 // Reducido a 2 segundos para no bloquear
                    ]
                ]);
                
                $statusResult = @file_get_contents($statusUrl, false, $statusContext);
                if ($statusResult !== false) {
                    $statusData = json_decode($statusResult, true);
                    
                    // Verificar si el servicio está deshabilitado
                    if (isset($statusData['disabled']) && $statusData['disabled']) {
                        error_log("⚠️ WhatsApp - El servicio está deshabilitado en modo desarrollo");
                        $servicioDeshabilitado = true;
                        return false; // Si está deshabilitado, no intentar enviar
                    }
                    
                    // Verificar si está conectado (múltiples formas)
                    $estaConectado = false;
                    if (isset($statusData['ready']) && $statusData['ready']) {
                        $estaConectado = true;
                        error_log("✅ WhatsApp - Servicio verificado: ready=true");
                    } else if (isset($statusData['clientState']) && $statusData['clientState'] === 'CONNECTED') {
                        $estaConectado = true;
                        error_log("✅ WhatsApp - Servicio verificado: clientState=CONNECTED");
                    }
                    
                    if (!$estaConectado) {
                        error_log("⚠️ WhatsApp - El servicio reporta que no está conectado. Estado: " . json_encode($statusData));
                        error_log("⚠️ WhatsApp - Continuando con el envío de todas formas (el estado puede no estar actualizado)...");
                        // NO retornar false aquí - continuar intentando enviar
                        // El API de WhatsApp puede estar conectado aunque el estado no lo refleje inmediatamente
                    }
                } else {
                    error_log("⚠️ WhatsApp - No se pudo verificar el estado del servicio (puede estar iniciando o no disponible)");
                    error_log("⚠️ WhatsApp - Continuando con el envío de todas formas...");
                    // Continuar intentando enviar de todas formas
                }
            } catch (Exception $statusError) {
                error_log("⚠️ WhatsApp - Error al verificar estado: " . $statusError->getMessage());
                error_log("⚠️ WhatsApp - Continuando con el envío de todas formas...");
                // Continuar intentando enviar de todas formas
            }
            
            // Si el servicio está explícitamente deshabilitado, no intentar enviar
            if ($servicioDeshabilitado) {
                return false;
            }

            // Usar curl para mejor manejo de errores HTTP
            $ch = curl_init($this->apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($data))
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            
            $result = curl_exec($ch);
            $httpResponseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($result === false || !empty($curlError)) {
                error_log("❌ WhatsApp - Error al enviar mensaje para teléfono: $telefonoFormateado");
                error_log("❌ WhatsApp - Error cURL: " . ($curlError ?: 'Desconocido'));
                error_log("❌ WhatsApp - Código HTTP: " . ($httpResponseCode ?: 'N/A'));
                error_log("❌ WhatsApp - URL intentada: " . $this->apiUrl);
                
                // Verificar si el servicio Node.js está corriendo
                $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
                $isProduction = !empty($host) && (
                    strpos($host, 'rutalan.cloud') !== false || 
                    strpos($host, 'www.rutalan.cloud') !== false
                );
                if ($isProduction) {
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $testUrl = $protocol . '://' . $host . '/whatsapp-api/api/status';
                } else {
                    $testUrl = 'http://localhost:3000/api/status';
                }
                $testCh = curl_init($testUrl);
                curl_setopt($testCh, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($testCh, CURLOPT_TIMEOUT, 2);
                $testResult = curl_exec($testCh);
                $testHttpCode = curl_getinfo($testCh, CURLINFO_HTTP_CODE);
                curl_close($testCh);
                
                if ($testResult !== false && $testHttpCode === 200) {
                    $statusData = json_decode($testResult, true);
                    error_log("⚠️ WhatsApp - Estado del servicio: " . json_encode($statusData));
                    if (isset($statusData['ready']) && !$statusData['ready']) {
                        error_log("⚠️ WhatsApp - El servicio está corriendo pero WhatsApp no está conectado");
                    }
                } else {
                    $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
                    $isProduction = !empty($host) && (
                        strpos($host, 'rutalan.cloud') !== false || 
                        strpos($host, 'www.rutalan.cloud') !== false
                    );
                    if ($isProduction) {
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $serviceUrl = $protocol . '://' . $host . '/whatsapp-api';
                    } else {
                        $serviceUrl = 'http://localhost:3000';
                    }
                    error_log("❌ WhatsApp - El servicio Node.js no está disponible en " . $serviceUrl);
                }
                
                return false;
            }

            $response = json_decode($result, true);
            error_log("📥 WhatsApp - Respuesta de API (HTTP $httpResponseCode): " . json_encode($response));
            
            // Si el código HTTP es de error, registrar el error
            if ($httpResponseCode && $httpResponseCode >= 400) {
                $errorMsg = $response['error'] ?? 'Error HTTP ' . $httpResponseCode;
                error_log("❌ WhatsApp - Error HTTP $httpResponseCode: " . $errorMsg);
                
                // Si hay detalles adicionales, registrarlos también
                if (isset($response['details'])) {
                    error_log("❌ WhatsApp - Detalles del error: " . $response['details']);
                }
                
                return false;
            }
            
            if (isset($response['error'])) {
                error_log("❌ WhatsApp - Error en respuesta: " . $response['error']);
                return false;
            }
            
            $success = $response['success'] ?? false;
            if ($success) {
                error_log("✅ WhatsApp - Mensaje enviado exitosamente a: $telefonoFormateado");
            } else {
                error_log("⚠️ WhatsApp - Respuesta sin éxito. Respuesta completa: " . json_encode($response));
            }
            
            return $success;
        } catch (Exception $e) {
            error_log("❌ WhatsApp - Excepción al enviar: " . $e->getMessage());
            error_log("❌ WhatsApp - Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    private function formatearNumero($telefono) {
        // Si el teléfono está vacío o es null, retornar vacío
        if (empty($telefono)) {
            return '';
        }

        // Eliminar todos los caracteres no numéricos
        $telefono = preg_replace('/[^0-9]/', '', $telefono);
        
        // Si después de limpiar está vacío, retornar vacío
        if (empty($telefono)) {
            return '';
        }

        // Si el número ya empieza con 57 (código de país de Colombia), mantenerlo
        if (substr($telefono, 0, 2) === '57') {
            // Verificar que tenga al menos 12 dígitos (57 + 10 dígitos)
            if (strlen($telefono) >= 12) {
                return $telefono;
            } else {
                error_log("⚠️ WhatsApp - Número con código 57 pero longitud incorrecta: " . strlen($telefono) . " dígitos");
                return '';
            }
        }

        // Si tiene 10 dígitos (número colombiano sin código de país), agregar 57
        if (strlen($telefono) === 10) {
            return '57' . $telefono;
        }

        // Si tiene menos de 10 dígitos o más de 12, es inválido
        if (strlen($telefono) < 10) {
            error_log("⚠️ WhatsApp - Número muy corto: " . strlen($telefono) . " dígitos. Número: " . $telefono);
            return '';
        }

        if (strlen($telefono) > 12) {
            error_log("⚠️ WhatsApp - Número muy largo: " . strlen($telefono) . " dígitos. Número: " . $telefono);
            return '';
        }

        // Si tiene 11 dígitos, podría ser un número con código de país diferente o formato especial
        // Por ahora, lo retornamos tal cual
        error_log("⚠️ WhatsApp - Número con formato no estándar: " . strlen($telefono) . " dígitos. Número: " . $telefono);
        return $telefono;
    }
}
?>
