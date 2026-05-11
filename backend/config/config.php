<?php
/**
 * Configuración global del sistema Rutalan
 * 
 * Este archivo debe ser incluido al inicio de todos los scripts PHP
 * para asegurar que la configuración se aplique correctamente
 */

// Configurar zona horaria de Colombia (GMT-5) para todo el sistema PHP
date_default_timezone_set('America/Bogota');

// Configuración adicional del sistema
ini_set('display_errors', 0); // En producción, ocultar errores
error_reporting(E_ALL); // Registrar todos los errores

// Configuración de caracteres
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
