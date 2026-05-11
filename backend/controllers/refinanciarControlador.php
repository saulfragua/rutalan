<?php
// Desactivar errores de visualización para evitar que interfieran con el JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
    header("Access-Control-Max-Age: 3600");
    http_response_code(200);
    exit;
}

// Headers CORS para todas las respuestas

header("Content-Type: application/json; charset=UTF-8");

require_once "../config/conexion.php";
require_once "../models/creditosModelos.php";
require_once "../models/cajasModelos.php";
require_once "../services/whatsapp_service.php";

$control = $_GET['control'] ?? '';
$creditos = new Creditos($conexion);
$cajas = new Cajas($conexion);
// Inicializar WhatsAppService (si falla, no bloquea el flujo)
$whatsappService = null;
try {
    $whatsappService = new WhatsAppService();
} catch (Exception $e) {
    error_log("Advertencia: No se pudo inicializar WhatsAppService: " . $e->getMessage());
}

try {
    switch ($control) {
        case 'consultarPorId':
            $idCredito = $_GET['id_credito'] ?? null;
            if ($idCredito) {
                $credito = $creditos->consultarPorId($idCredito);
                if ($credito && isset($credito['id_credito'])) {
                    echo json_encode($credito);
                } else {
                    echo json_encode([
                        "resultado" => "error",
                        "mensaje" => "Crédito no encontrado"
                    ]);
                }
            } else {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "ID de crédito no proporcionado"
                ]);
            }
            break;

        case 'refinanciar':
            $json = file_get_contents('php://input');
            $params = json_decode($json, true);

            if (!$params) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "Error en el formato de los datos"
                ]);
                exit;
            }

            // Si no se proporciona id_caja, obtenerla automáticamente de la caja abierta del usuario
            if (empty($params['id_caja']) && !empty($params['id_usuario'])) {
                $cajaAbierta = $cajas->obtenerCajaAbierta($params['id_usuario']);
                if ($cajaAbierta && isset($cajaAbierta['id_caja'])) {
                    $params['id_caja'] = $cajaAbierta['id_caja'];
                }
            }

            // Si no se proporciona id_ruta pero hay caja abierta, usar la ruta de la caja
            if (empty($params['id_ruta']) && !empty($params['id_usuario'])) {
                $cajaAbierta = $cajas->obtenerCajaAbierta($params['id_usuario']);
                if ($cajaAbierta && isset($cajaAbierta['id_ruta'])) {
                    $params['id_ruta'] = $cajaAbierta['id_ruta'];
                }
            }

            $resultado = $creditos->refinanciarCredito($params);

            // Log para depuración
            error_log("🟡 REFINANCIACIÓN - Resultado: " . json_encode($resultado));
            error_log("🟡 REFINANCIACIÓN - WhatsApp Service disponible: " . ($whatsappService !== null ? "SÍ" : "NO"));

            // Enviar mensaje de WhatsApp si la refinanciación fue exitosa
            if ($resultado['resultado'] === 'ok'
                && isset($resultado['id_credito_anterior'])
                && isset($resultado['id_nuevo_credito'])) {
                error_log("🟡 REFINANCIACIÓN - Intentando enviar WhatsApp. Crédito anterior: " . $resultado['id_credito_anterior'] . ", Nuevo: " . $resultado['id_nuevo_credito']);
                
                if ($whatsappService !== null) {
                    try {
                        $tipoRefinanciacion = $params['tipo_refinanciacion'] ?? 'descontar';
                        $saldoRefinanciado = floatval($params['saldo_pendiente'] ?? 0);
                        $enviado = $whatsappService->enviarConfirmacionRefinanciacion(
                            $resultado['id_credito_anterior'],
                            $resultado['id_nuevo_credito'],
                            $tipoRefinanciacion,
                            $saldoRefinanciado
                        );
                        error_log("🟡 REFINANCIACIÓN - WhatsApp enviado: " . ($enviado ? "SÍ" : "NO"));
                    } catch (Exception $e) {
                        error_log("❌ REFINANCIACIÓN - Error al enviar WhatsApp: " . $e->getMessage());
                        error_log("❌ REFINANCIACIÓN - Stack trace: " . $e->getTraceAsString());
                    }
                } else {
                    error_log("⚠️ REFINANCIACIÓN - WhatsApp Service no está disponible");
                }
            } else {
                error_log("⚠️ REFINANCIACIÓN - No se puede enviar WhatsApp. Resultado: " . ($resultado['resultado'] ?? 'desconocido'));
            }

            echo json_encode($resultado);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "Control no válido"
            ]);
            break;
    }
} catch (Exception $e) {
    error_log("Error en refinanciarControlador: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "resultado" => "error",
        "mensaje" => "Error interno del servidor: " . $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log("Error PDO en refinanciarControlador: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "resultado" => "error",
        "mensaje" => "Error de base de datos: " . $e->getMessage()
    ]);
}
?>
