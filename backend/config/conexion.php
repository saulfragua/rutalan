<?php
/**
 * Conexion a la base de datos Rutalan usando PDO
 * Configuración de zona horaria: Colombia (GMT-5)
 * 
 * IMPORTANTE: Este archivo NO debe enviar headers HTTP
 * Los headers se manejan en los controladores
 */

// Configurar zona horaria de Colombia para todo el sistema PHP
date_default_timezone_set('America/Bogota');

// Detectar si estamos en producción o desarrollo
$host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
$isProduction = !empty($host) && (
    strpos($host, 'rutalan.cloud') !== false || 
    strpos($host, 'www.rutalan.cloud') !== false
);

// Configuración de base de datos según el entorno
if ($isProduction) {
    // CONFIGURACIÓN DE PRODUCCIÓN
    // ⚠️ IMPORTANTE: Actualiza estas credenciales con las de tu servidor de producción
    $host     = "localhost";
    $dbname   = "rutalan";  // Ajusta si el nombre de la BD es diferente
    $user     = "admin";      // ⚠️ Cambiar por el usuario de MySQL de producción
    $password = "Colombia+";          // ⚠️ Cambiar por la contraseña de MySQL de producción
} else {
    // CONFIGURACIÓN DE DESARROLLO
    $host     = "localhost";
    $dbname   = "rutalan";
    $user     = "root";
    $password = "";
}

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

    $conexion = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    
    // Configurar zona horaria de MySQL para esta sesión
    $conexion->exec("SET time_zone = '-05:00'");

} catch (PDOException $e) {
    // En producción, no mostrar detalles del error por seguridad
    if ($isProduction) {
        error_log("Error de conexión a la base de datos Rutalan: " . $e->getMessage());
        die("Error de conexión a la base de datos. Por favor contacte al administrador.");
    } else {
        die("Error de conexión a la base de datos Rutalan: " . $e->getMessage());
    }
}
