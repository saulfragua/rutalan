<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: origin, X-Requested-With, Content-Type, Accept");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

require_once "../config/conexion.php";
require_once "../models/gastosModelos.php";
require_once "../models/cajasModelos.php";

$control = $_GET['control'] ?? '';
$gastos = new Gastos($conexion);
$cajas = new Cajas($conexion);

switch ($control) {
    case 'consultar':
        header("Content-Type: application/json");
        $vec = $gastos->consultar();
        echo json_encode($vec);
        break;

    case 'consultarPorUsuario':
        header("Content-Type: application/json");
        $idUsuario = $_GET['id_usuario'] ?? null;
        if ($idUsuario) {
            $vec = $gastos->consultarPorUsuario($idUsuario);
            echo json_encode($vec);
        } else {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID de usuario no proporcionado"
            ]);
        }
        break;

    case 'consultarPorCaja':
        header("Content-Type: application/json");
        $idCaja = $_GET['id_caja'] ?? null;
        if ($idCaja) {
            $vec = $gastos->consultarPorCaja($idCaja);
            echo json_encode($vec);
        } else {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID de caja no proporcionado"
            ]);
        }
        break;

    case 'consultarPorId':
        header("Content-Type: application/json");
        $id = $_GET['id'] ?? null;
        if ($id) {
            $vec = $gastos->consultarPorId($id);
            echo json_encode($vec);
        } else {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID no proporcionado"
            ]);
        }
        break;

    case 'insertar':
        header("Content-Type: application/json");
        try {
            $json = file_get_contents('php://input');
            $params = json_decode($json, true);

            // Obtener id_usuario del parámetro
            $idUsuario = $params['id_usuario'] ?? null;
            
            if (!$idUsuario) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "ID de usuario no proporcionado"
                ]);
                exit;
            }

            // Obtener la caja abierta del usuario
            $cajaAbierta = $cajas->obtenerCajaAbierta($idUsuario);
            
            if (!$cajaAbierta) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "No tiene una caja abierta. Debe abrir una caja antes de registrar gastos."
                ]);
                exit;
            }

            // Asignar id_caja automáticamente
            $params['id_caja'] = $cajaAbierta['id_caja'];
            
            // Si no se proporciona id_ruta, usar la ruta de la caja
            if (empty($params['id_ruta']) && !empty($cajaAbierta['id_ruta'])) {
                $params['id_ruta'] = $cajaAbierta['id_ruta'];
            }

            $vec = $gastos->insertar($params);
            echo json_encode($vec);
        } catch (Exception $e) {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => $e->getMessage()
            ]);
        }
        break;

    case 'editar':
        header("Content-Type: application/json");
        try {
            $json = file_get_contents('php://input');
            $params = json_decode($json, true);
            $id = $_GET['id'] ?? null;
            
            if ($id) {
                $vec = $gastos->editar($id, $params);
                echo json_encode($vec);
            } else {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "ID no proporcionado"
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => $e->getMessage()
            ]);
        }
        break;

    case 'eliminar':
        header("Content-Type: application/json");
        $id = $_GET['id'] ?? null;
        if ($id) {
            try {
                $vec = $gastos->eliminar($id);
                echo json_encode($vec);
            } catch (Exception $e) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => $e->getMessage()
                ]);
            }
        } else {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID no proporcionado"
            ]);
        }
        break;

    case 'filtrar':
        header("Content-Type: application/json");
        $dato = $_GET['dato'] ?? '';
        $vec = $gastos->filtrar($dato);
        echo json_encode($vec);
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
