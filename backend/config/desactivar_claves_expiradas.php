<?php
/**
 * Script para desactivar claves de cobradores expiradas
 * Este script debe ejecutarse automáticamente a medianoche todos los días
 * 
 * Configurar con cron job:
 * 0 0 * * * /usr/bin/php /ruta/al/proyecto/rutalan/backend/config/desactivar_claves_expiradas.php
 */

require_once dirname(__FILE__) . '/../config/conexion.php';
require_once dirname(__FILE__) . '/../models/clavesCobradorModelos.php';

// Registrar inicio de ejecución
$fechaHora = date('Y-m-d H:i:s');
error_log("[$fechaHora] Iniciando desactivación de claves expiradas...");

try {
    $clavesCobrador = new ClavesCobrador($conexion);
    $resultado = $clavesCobrador->desactivarClavesExpiradas();
    
    if ($resultado['resultado'] === 'ok') {
        $mensaje = "[$fechaHora] Claves desactivadas correctamente. " . 
                   "Total: " . $resultado['claves_desactivadas'];
        error_log($mensaje);
        echo $mensaje . "\n";
    } else {
        $mensaje = "[$fechaHora] Error al desactivar claves: " . $resultado['mensaje'];
        error_log($mensaje);
        echo $mensaje . "\n";
    }
} catch (Exception $e) {
    $mensaje = "[$fechaHora] Excepción: " . $e->getMessage();
    error_log($mensaje);
    echo $mensaje . "\n";
}

echo "Proceso completado.\n";
?>
