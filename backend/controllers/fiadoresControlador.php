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
        $vec = $fiadores->insertar($params);
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
    case 'buscarPorDocumento':
        header("Content-Type: application/json");
        $documento = $_GET['documento'] ?? '';
        if (empty($documento)) {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "Documento no proporcionado"
            ]);
            exit;
        }
        $resultado = $fiadores->buscarPorDocumento($documento);
        if ($resultado) {
            echo json_encode([
                "resultado" => "ok",
                "fiador" => $resultado
            ]);
        } else {
            echo json_encode([
                "resultado" => "no_encontrado"
            ]);
        }
        break;

    case 'contarClientes':
        header("Content-Type: application/json");
        $id_fiador = $_GET['id_fiador'] ?? null;
        if (!$id_fiador) {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID de fiador no proporcionado"
            ]);
            exit;
        }
        $resultado = $fiadores->contarClientesAsociados($id_fiador);
        echo json_encode([
            "resultado" => "ok",
            "total_clientes" => $resultado['total'] ?? 0,
            "nombres_clientes" => $resultado['nombres_clientes'] ?? ''
        ]);
        break;


        $datosj = json_decode($vec);
        echo $datosj;
        header('Content-Type: application/json');
}





?>