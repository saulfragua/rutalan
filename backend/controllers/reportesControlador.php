<?php
require_once __DIR__ . '/../models/reportesModelos.php';
require_once __DIR__ . '/../config/conexion.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    $db = new Conexion();
    $conexion = $db->obtenerConexion();
    $reportes = new Reportes($conexion);
    
    $control = $_GET['control'] ?? $_POST['control'] ?? '';

    switch ($control) {
        case 'obtenerTotales':
            $fechaInicio = $_GET['fecha_inicio'] ?? $_POST['fecha_inicio'] ?? date('Y-m-d');
            $fechaFin = $_GET['fecha_fin'] ?? $_POST['fecha_fin'] ?? date('Y-m-d');
            $idUsuario = !empty($_GET['id_usuario']) ? intval($_GET['id_usuario']) : (!empty($_POST['id_usuario']) ? intval($_POST['id_usuario']) : null);
            $idRuta = !empty($_GET['id_ruta']) ? intval($_GET['id_ruta']) : (!empty($_POST['id_ruta']) ? intval($_POST['id_ruta']) : null);
            
            $totales = $reportes->obtenerTotales($fechaInicio, $fechaFin, $idUsuario, $idRuta);
            
            // Calcular cierre actual
            $cierreActual = ($totales['creditos_cobrados'] + ($totales['seguros_cobrados'] * 0.7)) 
                           - $totales['gastos_ruta'] 
                           + $totales['adelantos_ingresos'] 
                           - $totales['adelantos_egresos'] 
                           - $totales['prestamos_realizados']
                           - $totales['descuentos_creditos'];
            
            $totales['cierre_actual'] = $cierreActual;
            
            echo json_encode([
                'resultado' => 'ok',
                'datos' => $totales
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'obtenerCajaAnterior':
            $fecha = $_GET['fecha'] ?? $_POST['fecha'] ?? date('Y-m-d', strtotime('-1 day'));
            $idUsuario = !empty($_GET['id_usuario']) ? intval($_GET['id_usuario']) : (!empty($_POST['id_usuario']) ? intval($_POST['id_usuario']) : null);
            
            $cajaAnterior = $reportes->obtenerCajaAnterior($fecha, $idUsuario);
            
            echo json_encode([
                'resultado' => 'ok',
                'datos' => $cajaAnterior
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'obtenerAdelantos':
            $fechaInicio = $_GET['fecha_inicio'] ?? $_POST['fecha_inicio'] ?? date('Y-m-d');
            $fechaFin = $_GET['fecha_fin'] ?? $_POST['fecha_fin'] ?? date('Y-m-d');
            $tipo = $_GET['tipo'] ?? $_POST['tipo'] ?? 'ingreso';
            $idUsuario = !empty($_GET['id_usuario']) ? intval($_GET['id_usuario']) : (!empty($_POST['id_usuario']) ? intval($_POST['id_usuario']) : null);
            $idRuta = !empty($_GET['id_ruta']) ? intval($_GET['id_ruta']) : (!empty($_POST['id_ruta']) ? intval($_POST['id_ruta']) : null);
            $limite = !empty($_GET['limite']) ? intval($_GET['limite']) : 5;
            
            $adelantos = $reportes->obtenerAdelantos($fechaInicio, $fechaFin, $tipo, $idUsuario, $idRuta, $limite);
            
            echo json_encode([
                'resultado' => 'ok',
                'datos' => $adelantos
            ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        case 'guardarCierreCaja':
            $fecha = $_POST['fecha'] ?? date('Y-m-d');
            $monto = floatval($_POST['monto'] ?? 0);
            $idUsuario = intval($_POST['id_usuario'] ?? 0);
            $observaciones = $_POST['observaciones'] ?? '';
            
            if (empty($fecha) || $monto <= 0 || $idUsuario <= 0) {
                throw new Exception("Datos incompletos para guardar el cierre de caja");
            }
            
            $resultado = $reportes->guardarCierreCaja($fecha, $monto, $idUsuario, $observaciones);
            
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            break;

        default:
            throw new Exception("Control no válido");
    }
} catch (Exception $e) {
    error_log("Error en reportesControlador: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'resultado' => 'error',
        'mensaje' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
}
?>
