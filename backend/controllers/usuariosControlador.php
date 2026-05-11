<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: origin, X-Requested-With, Content-Type, Accept");


require_once "../config/conexion.php";
require_once "../models/usuariosModelos.php";

$control = $_GET['control'];
$usuarios = new Usuarios($conexion);

switch ($control) {
case 'consultar':

    header("Content-Type: application/json");

    $vec = $usuarios->consultar();

    echo json_encode($vec);
    break;

case 'insertar':

    header("Content-Type: application/json");

    $json = file_get_contents('php://input');
    $params = json_decode($json, true);

    $vec = $usuarios->insertar($params);

    echo json_encode($vec);
    break;

case 'eliminar':
    header("Content-Type: application/json");

    $id = $_GET['id'];
    $vec = $usuarios->eliminar($id);

    echo json_encode($vec);
    break;

case 'editar':
    header("Content-Type: application/json");

    $json = file_get_contents('php://input');
    $params = json_decode($json, true);
    $id = $_GET['id'];

    $vec = $usuarios->editar($id, $params);
    echo json_encode($vec);
    break;


    case 'filtrar':
        $dato = $_GET['dato'];
        $vec = $usuarios->filtrar($dato);  
        break;
    case 'cambiarEstado':

        $id = $_GET['id'] ?? null;

        $json = file_get_contents('php://input');
        $params = json_decode($json, true);

        if (!$id || !isset($params['estado'])) {
            echo json_encode([
                'resultado' => 'error',
                'mensaje' => 'Estado no recibido',
                'debug' => $params
            ]);
            exit;
        }

        $vec = $usuarios->cambiarEstado($id, $params['estado']);
        echo json_encode($vec);
        exit;



        
    $datosj = json_decode($vec);
    echo $datosj;
    header('Content-Type: application/json');
}
    




?>