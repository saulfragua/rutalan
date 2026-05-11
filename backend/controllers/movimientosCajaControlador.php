<?php
// Desactivar errores de visualización para evitar que interfieran con el JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Max-Age: 3600");
    http_response_code(200);
    exit;
}

// Headers CORS para todas las respuestas
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/conexion.php';
require_once '../models/movimientosCajaModelos.php';

try {
    // $conexion ya está disponible después del require_once
    $movimientosCaja = new MovimientosCaja($conexion);

    $control = $_GET['control'] ?? '';

    switch ($control) {
        case 'registrar':
            $json = file_get_contents('php://input');
            $datos = json_decode($json, true);
            
            if (!$datos) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "Datos inválidos o JSON mal formado"
                ]);
                exit;
            }
            
            $resultado = $movimientosCaja->registrarMovimiento($datos);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'consultarPorCaja':
            $idCaja = $_GET['id_caja'] ?? null;
            
            if (!$idCaja) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "ID de caja no proporcionado"
                ]);
                exit;
            }
            
            $movimientos = $movimientosCaja->consultarPorCaja($idCaja);
            echo json_encode($movimientos, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'consultarTodos':
            $movimientos = $movimientosCaja->consultarTodos();
            echo json_encode($movimientos, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
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
    error_log("Error en movimientosCajaControlador: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "resultado" => "error",
        "mensaje" => "Error interno del servidor: " . $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log("Error PDO en movimientosCajaControlador: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "resultado" => "error",
        "mensaje" => "Error de base de datos: " . $e->getMessage()
    ]);
}
?>
