<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: origin, X-Requested-With, Content-Type, Accept");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

require_once "../config/conexion.php";
require_once "../models/planPagosModelos.php";

$control = $_GET['control'] ?? '';
$plan_pagos = new PlanPagos($conexion);

switch ($control) {
    case 'consultar':
        $vec = $plan_pagos->consultar();
        echo json_encode($vec);
        break;
        
    case 'consultarPorIdCredito':
        $id_credito = $_GET['id_credito'] ?? null;
        if ($id_credito) {
            $vec = $plan_pagos->consultarPorIdCredito($id_credito);
            echo json_encode($vec);
        } else {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID de crédito no proporcionado"
            ]);
        }
        break;
        
    case 'insertar':
        $json = file_get_contents('php://input');
        $params = json_decode($json);
        $vec = $plan_pagos->insertar($params);
        echo json_encode($vec);
        break;
        
    case 'eliminar':
        $id = $_GET['id'] ?? null;
        if ($id) {
            $vec = $plan_pagos->eliminar($id);
            echo json_encode($vec);
        } else {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID no proporcionado"
            ]);
        }
        break;
        
    case 'editar':
        $json = file_get_contents('php://input');
        $params = json_decode($json);
        $id = $_GET['id'] ?? null;
        if ($id) {
            $vec = $plan_pagos->editar($id, $params);
            echo json_encode($vec);
        } else {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID no proporcionado"
            ]);
        }
        break;
        
    case 'filtrar':
        $dato = $_GET['dato'] ?? '';
        if ($dato) {
            $vec = $plan_pagos->filtrar($dato);
            echo json_encode($vec);
        } else {
            echo json_encode([]);
        }
        break;
        
    default:
        echo json_encode([
            "resultado" => "error",
            "mensaje" => "Control no válido"
        ]);
        break;
}
?>
