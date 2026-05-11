<?php

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

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once "../config/conexion.php";
require_once "../models/dashboardModelos.php";

$control = $_GET['control'] ?? '';
$dashboard = new Dashboard($conexion);

try {
    switch ($control) {
        case 'obtenerCreditosPorRuta':
            $resultado = $dashboard->obtenerCreditosPorRuta();
            echo json_encode([
                "resultado" => "ok",
                "datos" => $resultado
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'obtenerTotalGeneralCreditos':
            $total = $dashboard->obtenerTotalGeneralCreditos();
            echo json_encode([
                "resultado" => "ok",
                "total_general" => $total
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'obtenerEstadisticasClientes':
            $resultado = $dashboard->obtenerEstadisticasClientes();
            echo json_encode([
                "resultado" => "ok",
                "datos" => $resultado
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'obtenerClientesPorRuta':
            $fechaInicio = $_GET['fecha_inicio'] ?? null;
            $fechaFin = $_GET['fecha_fin'] ?? null;
            $resultado = $dashboard->obtenerClientesPorRuta($fechaInicio, $fechaFin);
            echo json_encode([
                "resultado" => "ok",
                "datos" => $resultado
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'obtenerTotalCobradoEnDia':
            $fechaInicio = $_GET['fecha_inicio'] ?? null;
            $fechaFin = $_GET['fecha_fin'] ?? null;
            $total = $dashboard->obtenerTotalCobradoEnDia($fechaInicio, $fechaFin);
            echo json_encode([
                "resultado" => "ok",
                "total_cobrado" => $total
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'obtenerGastosPorRuta':
            $fechaInicio = $_GET['fecha_inicio'] ?? null;
            $fechaFin = $_GET['fecha_fin'] ?? null;
            $resultado = $dashboard->obtenerGastosPorRuta($fechaInicio, $fechaFin);
            echo json_encode([
                "resultado" => "ok",
                "datos" => $resultado
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'obtenerCreditosPorTipo':
            $resultado = $dashboard->obtenerCreditosPorTipo();
            echo json_encode([
                "resultado" => "ok",
                "datos" => $resultado
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'obtenerEstadisticasSeguros':
            $fechaInicio = $_GET['fecha_inicio'] ?? null;
            $fechaFin = $_GET['fecha_fin'] ?? null;
            $resultado = $dashboard->obtenerEstadisticasSeguros($fechaInicio, $fechaFin);
            echo json_encode([
                "resultado" => "ok",
                "datos" => $resultado
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'obtenerEstadisticasCajas':
            $resultado = $dashboard->obtenerEstadisticasCajas();
            echo json_encode([
                "resultado" => "ok",
                "datos" => $resultado
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'obtenerEstadisticasCuotas':
            $resultado = $dashboard->obtenerEstadisticasCuotas();
            echo json_encode([
                "resultado" => "ok",
                "datos" => $resultado
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'obtenerEvolucionPagos':
            $fechaInicio = $_GET['fecha_inicio'] ?? null;
            $fechaFin = $_GET['fecha_fin'] ?? null;
            $resultado = $dashboard->obtenerEvolucionPagos($fechaInicio, $fechaFin);
            echo json_encode([
                "resultado" => "ok",
                "datos" => $resultado
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'obtenerEstadisticasRefinanciaciones':
            $fechaInicio = $_GET['fecha_inicio'] ?? null;
            $fechaFin = $_GET['fecha_fin'] ?? null;
            $resultado = $dashboard->obtenerEstadisticasRefinanciaciones($fechaInicio, $fechaFin);
            echo json_encode([
                "resultado" => "ok",
                "datos" => $resultado
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'obtenerTopRutasPorRendimiento':
            $fechaInicio = $_GET['fecha_inicio'] ?? null;
            $fechaFin = $_GET['fecha_fin'] ?? null;
            $limite = isset($_GET['limite']) ? intval($_GET['limite']) : 5;
            $resultado = $dashboard->obtenerTopRutasPorRendimiento($fechaInicio, $fechaFin, $limite);
            echo json_encode([
                "resultado" => "ok",
                "datos" => $resultado
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'obtenerEstadisticasMorosidad':
            $resultado = $dashboard->obtenerEstadisticasMorosidad();
            echo json_encode([
                "resultado" => "ok",
                "datos" => $resultado
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "Control no válido"
            ], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en dashboardControlador: " . $e->getMessage());
    echo json_encode([
        "resultado" => "error",
        "mensaje" => "Error al procesar la solicitud: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
