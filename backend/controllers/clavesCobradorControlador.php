<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: origin, X-Requested-With, Content-Type, Accept");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

// Manejar solicitudes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once "../config/conexion.php";
require_once "../models/clavesCobradorModelos.php";

$control = $_GET['control'] ?? '';
$clavesCobrador = new ClavesCobrador($conexion);

switch ($control) {
    case 'generarClave':
        // Genera una nueva clave para un cobrador
        $idUsuario = $_GET['id_usuario'] ?? null;
        
        if (!$idUsuario) {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID de usuario no proporcionado"
            ]);
            break;
        }
        
        $resultado = $clavesCobrador->generarClave($idUsuario);
        echo json_encode($resultado);
        break;
    
    case 'obtenerClaveActiva':
        // Obtiene la clave activa del día para un usuario
        $idUsuario = $_GET['id_usuario'] ?? null;
        
        if (!$idUsuario) {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID de usuario no proporcionado"
            ]);
            break;
        }
        
        $resultado = $clavesCobrador->obtenerClaveActiva($idUsuario);
        echo json_encode($resultado);
        break;
    
    case 'consultarPorUsuario':
        // Consulta todas las claves de un usuario
        $idUsuario = $_GET['id_usuario'] ?? null;
        
        if (!$idUsuario) {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID de usuario no proporcionado"
            ]);
            break;
        }
        
        $claves = $clavesCobrador->consultarPorUsuario($idUsuario);
        echo json_encode($claves);
        break;
    
    case 'validarClave':
        // Valida una clave para un usuario
        $json = file_get_contents('php://input');
        $params = json_decode($json);
        
        if (!isset($params->id_usuario) || !isset($params->clave)) {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "Parámetros incompletos"
            ]);
            break;
        }
        
        $resultado = $clavesCobrador->validarClave($params->id_usuario, $params->clave);
        echo json_encode($resultado);
        break;
    
    case 'desactivarClavesExpiradas':
        // Desactiva todas las claves expiradas
        // Esta operación puede ejecutarse automáticamente con un cron job
        $resultado = $clavesCobrador->desactivarClavesExpiradas();
        echo json_encode($resultado);
        break;
    
    default:
        echo json_encode([
            "resultado" => "error",
            "mensaje" => "Control no válido"
        ]);
        break;
}
?>
