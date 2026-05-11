<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: origin, X-Requested-With, Content-Type, Accept");


require_once "../config/conexion.php";
require_once "../models/fiadoresmodelos.php";

$control = $_GET['control'];
$fiadores = new Fiadores($conexion);

switch ($control) {
    case 'consultar':
        $vec = $fiadores->consultar();
        break;
    case 'insertar':
        $json = file_get_contents('php://input');
        $params = json_decode($json);
        $vec = $fiadores->insertar ($params);
        break;
    case 'eliminar':
        $id = $_GET['id'];          
        $vec = $fiadores->eliminar($id);
        break;
    case 'editar':
        $json = file_get_contents('php://input');
        $params = json_decode($json);
        $id = $_GET['id'];
        $vec = $fiadores->editar($id, $params);
        break;
    case 'filtrar':
        $dato = $_GET['dato'];
        $vec = $fiadores->filtrar($dato);  
        break;
    
        
    $datosj = json_decode($vec);
    echo $datosj;
    header('Content-Type: application/json');
}
    




?>