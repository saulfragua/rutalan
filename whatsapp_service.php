<?php
// whatsapp_service.php
class WhatsAppService {
    private $apiUrl;
    
    public function __construct() {
        // URL de tu API de WhatsApp (debe estar en otro servidor/puerto)
        $this->apiUrl = 'http://localhost:3000/api/send-message';
    }
    
    /**
     * Envía mensaje de confirmación de pago por WhatsApp
     */
    /**
 * Envía mensaje de confirmación de pago por WhatsApp
 */
public function enviarConfirmacionPago($id_pago) {
    global $conexion;
    
    // Obtener información completa del pago
    $sql = "SELECT p.*, c.nombres, c.apellidos, c.telefono, 
                   cr.saldo_actual, cr.monto_credito, cr.id_credito,
                   r.nombre_ruta
            FROM pagos p
            JOIN clientes c ON p.id_cliente = c.id_cliente
            JOIN creditos cr ON p.id_credito = cr.id_credito
            JOIN rutas r ON p.id_ruta = r.id_ruta
            WHERE p.id_pago = ?";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_pago);
    $stmt->execute();
    $pago = $stmt->get_result()->fetch_assoc();
    
    if (!$pago) {
        return false;
    }
    
    // Formatear el mensaje
    $mensaje = $this->formatearMensajePago($pago);
    
    // Enviar por WhatsApp
    return $this->enviarMensajeWhatsApp($pago['telefono'], $mensaje);
}

/**
 * Formatea el mensaje de confirmación de pago
 */
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
    
    /**
     * Envía mensaje de confirmación de nuevo crédito por WhatsApp
     */
    public function enviarConfirmacionCredito($id_credito) {
    global $conexion;

    // Obtener información del crédito y del cliente
    $sql = "SELECT cr.*, cl.nombres, cl.apellidos, cl.telefono,
                   cr.monto_credito, cr.cuotas, cr.tasa_interes, cr.seguro,
                   cr.fecha_toma_credito, cr.fecha_finaliza_credito, cr.frecuencia_pago,
                   cr.saldo_actual
            FROM creditos cr
            JOIN clientes cl ON cr.id_cliente = cl.id_cliente
            WHERE cr.id_credito = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_credito);
    $stmt->execute();
    $credito = $stmt->get_result()->fetch_assoc();

    if (!$credito) {
        return false;
    }

    // Calcular valores importantes
    $intereses = $credito['monto_credito'] * ($credito['tasa_interes'] / 100);
    $total_a_pagar = $credito['monto_credito'] + $intereses;
    $monto_entregar = $credito['monto_credito'] - $credito['seguro'];

    // Formatear fechas
    $fecha_inicio = date('d/m/Y', strtotime($credito['fecha_toma_credito']));
    $fecha_fin = date('d/m/Y', strtotime($credito['fecha_finaliza_credito']));

    // Formatear mensaje con el ID del crédito como número de crédito
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
// ------------------ POLÍTICAS DE SEGURO ------------------
// ------------------ POLÍTICAS DE SEGURO ------------------
if ($credito['seguro'] > 0) {
    $mensaje .= "🛡️ *Políticas de Seguro:*\n";
    $mensaje .= "El pago de seguro de incapacidad corresponde al préstamo adquirido.\n";

    // Enviar mensaje según el plazo
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

// Mensaje final de recordatorio
$mensaje .= "💡 *Recuerde:*\n";
$mensaje .= "• Mantenga sus pagos al día\n";
$mensaje .= "• Cumpla con las fechas establecidas\n";
$mensaje .= "• Ante cualquier duda, contáctenos\n\n";
$mensaje .= "¡Gracias por confiar en nosotros!";

    // Enviar mensaje
    return $this->enviarMensajeWhatsApp($credito['telefono'], $mensaje);
}

    /**
     * Envía mensaje de recordatorio de cobro por WhatsApp
     */
    public function enviarRecordatorioPago($id_credito) {
        global $conexion;
        
        $sql = "SELECT c.*, cr.saldo_actual 
                FROM creditos cr 
                JOIN clientes c ON cr.id_cliente = c.id_cliente 
                WHERE cr.id_credito = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $id_credito);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        $datos = $result->fetch_assoc();
        
        // Formatear el mensaje de recordatorio
        $mensaje = "Hola " . $datos['nombres'] . ",\n";
        $mensaje .= "Le recordamos que tiene un saldo pendiente de $" . number_format($datos['saldo_actual'], 2) . ".\n";
        $mensaje .= "Por favor, realice su pago a la brevedad.\n";
        $mensaje .= "¡Gracias por su preferencia!";
        
        // Enviar mensaje por WhatsApp
        return $this->enviarMensajeWhatsApp($datos['telefono'], $mensaje);
    }

    /**
     * Envía mensaje a través de la API de WhatsApp
     */
    private function enviarMensajeWhatsApp($telefono, $mensaje) {
        // Limpiar y formatear el número de teléfono
        $telefono = $this->formatearNumero($telefono);
        
        // Preparar datos para la API
        $data = [
            'sessionId' => 'cobranza-session',
            'to' => $telefono,
            'message' => $mensaje
        ];
        
        // Configurar la solicitud HTTP
        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($data),
                'timeout' => 10 // timeout de 10 segundos
            ]
        ];
        
        $context  = stream_context_create($options);
        
        try {
            $result = file_get_contents($this->apiUrl, false, $context);
            if ($result === FALSE) {
                error_log("Error al enviar mensaje de WhatsApp para teléfono: $telefono");
                return false;
            }
            
            $response = json_decode($result, true);
            return $response['success'] ?? false;
            
        } catch (Exception $e) {
            error_log("Excepción al enviar WhatsApp: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Formatea el número de teléfono para WhatsApp
     */
    private function formatearNumero($telefono) {
        // Eliminar caracteres no numéricos
        $telefono = preg_replace('/[^0-9]/', '', $telefono);
        
        // Si no tiene código de país, agregar el de Colombia
        if (strlen($telefono) === 10) {
            $telefono = '57' . $telefono;
        }
        return $telefono;
    }

    /**
     * Función auxiliar para enviar mensajes
     */
    private function enviarMensaje($telefono, $mensaje) {
        return $this->enviarMensajeWhatsApp($telefono, $mensaje);
    }
/**
 * Envía mensaje de confirmación de refinanciación por WhatsApp
 */
public function enviarConfirmacionRefinanciacion($id_credito_anterior, $id_nuevo_credito, $tipo_refinanciacion, $saldo_refinanciado)

 {
    global $conexion;

    // Crédito anterior
$sql_anterior = "SELECT cr.id_credito, cr.monto_credito, cr.saldo_actual, cr.seguro, cr.tasa_interes, cr.cuotas, cr.frecuencia_pago, cr.fecha_toma_credito, cr.fecha_finaliza_credito,
                        cl.nombres, cl.apellidos, cl.telefono
                 FROM creditos cr
                 JOIN clientes cl ON cr.id_cliente = cl.id_cliente
                 WHERE cr.id_credito = ?";
    $stmt_anterior = $conexion->prepare($sql_anterior);
    $stmt_anterior->bind_param("i", $id_credito_anterior);
    $stmt_anterior->execute();
    $credito_anterior = $stmt_anterior->get_result()->fetch_assoc();

    // Crédito nuevo
    $sql_nuevo = "SELECT cr.*, cl.nombres, cl.apellidos, cl.telefono
                  FROM creditos cr
                  JOIN clientes cl ON cr.id_cliente = cl.id_cliente
                  WHERE cr.id_credito = ?";
    $stmt_nuevo = $conexion->prepare($sql_nuevo);
    $stmt_nuevo->bind_param("i", $id_nuevo_credito);
    $stmt_nuevo->execute();
    $credito_nuevo = $stmt_nuevo->get_result()->fetch_assoc();

    if (!$credito_anterior || !$credito_nuevo) {
        return false;
    }

    // Calcular valores
// ✅ USAR EL SALDO QUE VIENE DESDE EL CONTROLADOR
$saldo_anterior = $saldo_refinanciado; // renombrado solo para claridad


    $intereses_nuevo = $credito_nuevo['monto_credito'] * ($credito_nuevo['tasa_interes'] / 100);
    $total_a_pagar_nuevo = $credito_nuevo['monto_credito'] + $intereses_nuevo;
    $monto_entregar_nuevo = $credito_nuevo['monto_credito'] - $credito_nuevo['seguro'] - $saldo_refinanciado;


    $fecha_inicio_nuevo = date('d/m/Y', strtotime($credito_nuevo['fecha_toma_credito']));
    $fecha_fin_nuevo = date('d/m/Y', strtotime($credito_nuevo['fecha_finaliza_credito']));

    // Mensaje
    $mensaje = "🔄 *REFINANCIACIÓN DE CRÉDITO COMPLETADA*\n\n";
    $mensaje .= "📋 *Crédito Anterior:* #{$id_credito_anterior}\n";
    $mensaje .= "📋 *Nuevo Crédito:* #{$id_nuevo_credito}\n";
    $mensaje .= "👤 *Cliente:* {$credito_anterior['nombres']} {$credito_anterior['apellidos']}\n";
    $mensaje .= "💰 *Saldo del crédito anterior:* $" . number_format($saldo_anterior, 2) . "\n";

    $mensaje .= "💰 *Monto a entregar:* $" . number_format($monto_entregar_nuevo, 2) . "\n";
    $mensaje .= "💵 *Nuevo monto:* $" . number_format($credito_nuevo['monto_credito'], 2) . "\n";

    // Seguro y políticas según plazo
    if (!empty($credito_nuevo['seguro']) && $credito_nuevo['seguro'] > 0) {
        $mensaje .= "🛡️ *Seguro:* $" . number_format($credito_nuevo['seguro'], 2) . "\n";
        $dias_credito = $credito_nuevo['dias'] ?? $credito_nuevo['cuotas'] ?? 0;
    $mensaje .= "💸 *Total a pagar:* $" . number_format($total_a_pagar_nuevo, 2) . "\n";
    $mensaje .= "📅 *Plazo:* {$credito_nuevo['cuotas']} días\n";
    $mensaje .= "🔄 *Frecuencia de pago:* " . ucfirst($credito_nuevo['frecuencia_pago']) . "\n";
    $mensaje .= "📅 *Fecha de inicio:* $fecha_inicio_nuevo\n";
    $mensaje .= "📅 *Fecha de finalización:* $fecha_fin_nuevo\n";
    $mensaje .= "⚡ *Tipo de refinanciación:* " . ($tipo_refinanciacion === 'descontar' ? 'Descontar saldo' : 'Sumar saldo') . "\n\n";
    $mensaje .= "✅ *¡Su crédito ha sido refinanciado exitosamente!*\n\n";

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

    // Recordatorio final
    $mensaje .= "💡 *Recuerde:*\n";
    $mensaje .= "• Mantenga sus pagos al día\n";
    $mensaje .= "• Cumpla con las fechas establecidas\n";
    $mensaje .= "• Ante cualquier duda, contáctenos\n\n";
    $mensaje .= "¡Gracias por confiar en nosotros!";

    // Enviar mensaje
    return $this->enviarMensajeWhatsApp($credito_anterior['telefono'], $mensaje);
}
}
?>