<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
    header("Access-Control-Max-Age: 3600");
    http_response_code(200);
    exit;
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/../models/informesModelos.php";

$control = $_GET['control'] ?? '';
$informes = new Informes($conexion);

try {
    $fechaDesde = $_GET['fecha_desde'] ?? $_POST['fecha_desde'] ?? null;
    $fechaHasta = $_GET['fecha_hasta'] ?? $_POST['fecha_hasta'] ?? null;

    switch ($control) {
        case 'pagos':
            if (empty($fechaDesde) || empty($fechaHasta)) {
                echo json_encode([
                    'resultado' => 'error',
                    'mensaje' => 'Debe indicar fecha_desde y fecha_hasta (Y-m-d).'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $lista = $informes->obtenerPagosPorRango($fechaDesde, $fechaHasta);
            echo json_encode([
                'resultado' => 'ok',
                'datos' => $lista
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'creditos':
            if (empty($fechaDesde) || empty($fechaHasta)) {
                echo json_encode([
                    'resultado' => 'error',
                    'mensaje' => 'Debe indicar fecha_desde y fecha_hasta (Y-m-d).'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $lista = $informes->obtenerCreditosPorRango($fechaDesde, $fechaHasta);
            echo json_encode([
                'resultado' => 'ok',
                'datos' => $lista
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'gastos':
            if (empty($fechaDesde) || empty($fechaHasta)) {
                echo json_encode([
                    'resultado' => 'error',
                    'mensaje' => 'Debe indicar fecha_desde y fecha_hasta (Y-m-d).'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $lista = $informes->obtenerGastosPorRango($fechaDesde, $fechaHasta);
            echo json_encode([
                'resultado' => 'ok',
                'datos' => $lista
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        default:
            echo json_encode([
                'resultado' => 'error',
                'mensaje' => 'Control no válido. Use: pagos, creditos o gastos.'
            ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log("Error en informesControlador: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'resultado' => 'error',
        'mensaje' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
