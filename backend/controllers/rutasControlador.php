<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: origin, X-Requested-With, Content-Type, Accept");


require_once "../config/conexion.php";
require_once "../models/rutasModelos.php";

$control = $_GET['control'];
$rutas = new Rutas($conexion);

switch ($control) {
case 'consultar':

    header("Content-Type: application/json");

    $vec = $rutas->consultar();

    echo json_encode($vec); // 🔥 ESTO ES LO QUE FALTABA
    break;

case 'insertar':

    header("Content-Type: application/json");

    $json = file_get_contents('php://input');
    $params = json_decode($json, true); // 👈 ARRAY

    if (!isset($params['nombre']) || empty($params['nombre'])) {
        echo json_encode([
            'resultado' => 'error',
            'mensaje' => 'El nombre de la ruta es obligatorio'
        ]);
        exit;
    }

    $vec = $rutas->insertar($params);

    echo json_encode($vec); // 🔥 ESTO ERA LO QUE FALTABA
    break;

case 'eliminar':

    header("Content-Type: application/json");

    if (!isset($_GET['id'])) {
        echo json_encode([
            'resultado' => 'error',
            'mensaje' => 'ID no recibido'
        ]);
        exit;
    }

    $id = $_GET['id'];
    $vec = $rutas->eliminar($id);

    echo json_encode($vec);
    break;


case 'editar':

    header("Content-Type: application/json");

    $json = file_get_contents('php://input');
    $params = json_decode($json, true);
    $id = $_GET['id'];

    $vec = $rutas->editar($id, $params);

    echo json_encode($vec);
    break;

    case 'filtrar':
        $dato = $_GET['dato'];
        $vec = $rutas->filtrar($dato);  
        break;

    case 'estado':

    header("Content-Type: application/json");

    if (!isset($_GET['id']) || !isset($_GET['estado'])) {
        echo json_encode([
            'resultado' => 'error',
            'mensaje' => 'Datos incompletos'
        ]);
        exit;
    }

    $id = $_GET['id'];
    $estado = $_GET['estado'];

    $vec = $rutas->cambiarEstado($id, $estado);

    echo json_encode($vec);
    break;

    
        
    $datosj = json_decode($vec);
    echo $datosj;
    header('Content-Type: application/json');
}


    




?>