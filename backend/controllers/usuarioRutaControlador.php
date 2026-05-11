<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: origin, X-Requested-With, Content-Type, Accept");


require_once "../config/conexion.php";
require_once "../models/usuarioRutaModelos.php";

$control = $_GET['control'];
$usuariorutas = new UsuarioRuta($conexion);

switch ($control) {
case 'consultar':
        echo json_encode($usuariorutas->consultar());
        break;

    case 'filtrar':
        $id_usuario = $_GET['id_usuario'];
        echo json_encode($usuariorutas->filtrar($id_usuario));
        break;

    case 'insertar':
        $json = file_get_contents('php://input');
        $params = json_decode($json, true);
        echo json_encode($usuariorutas->insertar($params));
        break;

    case 'eliminar':
        $json = file_get_contents('php://input');
        $params = json_decode($json, true);
        echo json_encode($usuariorutas->eliminar($params));
        break;

    default:
        echo json_encode([
            "resultado" => "ERROR",
            "mensaje" => "Control no válido"
        ]);
        break;

    
        
    $datosj = json_decode($vec);
    echo $datosj;
    header('Content-Type: application/json');
}
    




?>