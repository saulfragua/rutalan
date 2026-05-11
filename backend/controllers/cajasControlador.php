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
require_once "../models/cajasModelos.php";

$control = $_GET['control'] ?? '';
$cajas = new Cajas($conexion);

try {
    switch ($control) {
        case 'obtenerCajaAbierta':
            $id_usuario = $_GET['id_usuario'] ?? null;
            if ($id_usuario) {
                $caja = $cajas->obtenerCajaAbierta($id_usuario);
                if ($caja) {
                    echo json_encode($caja);
                } else {
                    echo json_encode(null);
                }
            } else {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "ID de usuario no proporcionado"
                ]);
            }
            break;

        case 'tieneCajaAbierta':
            $id_usuario = $_GET['id_usuario'] ?? null;
            if ($id_usuario) {
                $tieneCaja = $cajas->tieneCajaAbierta($id_usuario);
                echo json_encode([
                    "tiene_caja_abierta" => $tieneCaja
                ]);
            } else {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "ID de usuario no proporcionado"
                ]);
            }
            break;

        case 'abrirCaja':
            $json = file_get_contents('php://input');
            $params = json_decode($json, true);

            if (!$params) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "Error en el formato de los datos"
                ]);
                exit;
            }

            $resultado = $cajas->abrirCaja($params);
            echo json_encode($resultado);
            break;

        case 'cerrarCaja':
            $id_caja = $_GET['id_caja'] ?? null;
            if (!$id_caja) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "ID de caja no proporcionado"
                ]);
                exit;
            }

            $json = file_get_contents('php://input');
            $params = json_decode($json, true);
            
            $saldoFinal = $params['saldo_final'] ?? null;
            $observaciones = $params['observaciones'] ?? null;

            $resultado = $cajas->cerrarCaja($id_caja, $saldoFinal, $observaciones);
            echo json_encode($resultado);
            break;

        case 'consultarPorUsuario':
            $id_usuario = $_GET['id_usuario'] ?? null;
            if ($id_usuario) {
                $listaCajas = $cajas->consultarPorUsuario($id_usuario);
                echo json_encode($listaCajas);
            } else {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "ID de usuario no proporcionado"
                ]);
            }
            break;

        case 'consultarCajasAbiertasConResumen':
            try {
                $listaCajas = $cajas->consultarCajasAbiertasConResumen();
                $json = json_encode($listaCajas, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
                
                if ($json === false) {
                    throw new Exception("Error al codificar JSON: " . json_last_error_msg());
                }
                
                echo $json;
            } catch (Exception $e) {
                error_log("Error en consultarCajasAbiertasConResumen: " . $e->getMessage());
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "Error al consultar cajas: " . $e->getMessage()
                ]);
            } catch (PDOException $e) {
                error_log("Error PDO en consultarCajasAbiertasConResumen: " . $e->getMessage());
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "Error de base de datos: " . $e->getMessage()
                ]);
            }
            break;

        case 'consultarCajasCerradasConResumen':
            try {
                $listaCajas = $cajas->consultarCajasCerradasConResumen();
                $json = json_encode($listaCajas, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
                
                if ($json === false) {
                    throw new Exception("Error al codificar JSON: " . json_last_error_msg());
                }
                
                echo $json;
            } catch (Exception $e) {
                error_log("Error en consultarCajasCerradasConResumen: " . $e->getMessage());
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "Error al consultar cajas cerradas: " . $e->getMessage()
                ]);
            } catch (PDOException $e) {
                error_log("Error PDO en consultarCajasCerradasConResumen: " . $e->getMessage());
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "Error de base de datos: " . $e->getMessage()
                ]);
            }
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
    error_log("Error en cajasControlador: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "resultado" => "error",
        "mensaje" => "Error interno del servidor: " . $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log("Error PDO en cajasControlador: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "resultado" => "error",
        "mensaje" => "Error de base de datos: " . $e->getMessage()
    ]);
}
?>
