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
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
header("Content-Type: application/json; charset=UTF-8");

require_once "../config/conexion.php";
require_once "../models/pagosModelos.php";
require_once "../models/cajasModelos.php";
require_once "../services/whatsapp_service.php";

$control = $_GET['control'] ?? '';
$pagos = new Pagos($conexion);
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
        case 'consultar':
            $listaPagos = $pagos->consultar();
            echo json_encode($listaPagos);
            break;

        case 'consultarClientesPorRuta':
            $idRuta = $_GET['id_ruta'] ?? null;
            if ($idRuta) {
                $clientes = $pagos->consultarClientesPorRuta($idRuta);
                echo json_encode($clientes);
            } else {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "ID de ruta no proporcionado"
                ]);
            }
            break;

        case 'registrarPago':
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
            if (empty($params['id_ruta']) && !empty($params['id_caja'])) {
                $cajaAbierta = $cajas->obtenerCajaAbierta($params['id_usuario']);
                if ($cajaAbierta && isset($cajaAbierta['id_ruta'])) {
                    $params['id_ruta'] = $cajaAbierta['id_ruta'];
                }
            }

            $resultado = $pagos->registrarPago($params);

            // Log para depuración
            error_log("🔵 PAGO - Resultado: " . json_encode($resultado));
            error_log("🔵 PAGO - WhatsApp Service disponible: " . ($whatsappService !== null ? "SÍ" : "NO"));

            // Enviar mensaje de WhatsApp si el pago fue exitoso
            if ($resultado['resultado'] === 'ok' && isset($resultado['id_pago'])) {
                error_log("🔵 PAGO - Intentando enviar WhatsApp para pago ID: " . $resultado['id_pago']);
                
                if ($whatsappService !== null) {
                    try {
                        $enviado = $whatsappService->enviarConfirmacionPago($resultado['id_pago']);
                        error_log("🔵 PAGO - WhatsApp enviado: " . ($enviado ? "SÍ" : "NO"));
                    } catch (Exception $e) {
                        error_log("❌ PAGO - Error al enviar WhatsApp: " . $e->getMessage());
                        error_log("❌ PAGO - Stack trace: " . $e->getTraceAsString());
                    }
                } else {
                    error_log("⚠️ PAGO - WhatsApp Service no está disponible");
                }
            } else {
                error_log("⚠️ PAGO - No se puede enviar WhatsApp. Resultado: " . ($resultado['resultado'] ?? 'desconocido') . ", ID Pago: " . ($resultado['id_pago'] ?? 'no disponible'));
            }

            echo json_encode($resultado);
            break;

        case 'actualizarOrdenCobranza':
            require_once "../models/creditosModelos.php";
            $creditos = new Creditos($conexion);
            
            $json = file_get_contents('php://input');
            $params = json_decode($json, true);

            if (!$params || !isset($params['id_ruta']) || !isset($params['orden'])) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "Datos incompletos"
                ]);
                exit;
            }

            // id_ruta puede ser null para caja general
            $idRuta = isset($params['id_ruta']) && $params['id_ruta'] !== '' && $params['id_ruta'] !== 0 
                ? (int)$params['id_ruta'] 
                : null;
            
            $resultado = $creditos->actualizarOrdenCobranza($idRuta, $params['orden']);
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
    error_log("Error en pagosControlador: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "resultado" => "error",
        "mensaje" => "Error interno del servidor: " . $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log("Error PDO en pagosControlador: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "resultado" => "error",
        "mensaje" => "Error de base de datos: " . $e->getMessage()
    ]);
}
?>
