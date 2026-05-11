<?php
// Desactivar errores de visualización para evitar que interfieran con el JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
    header("Access-Control-Max-Age: 3600");
    http_response_code(200);
    exit;
}

// Headers CORS para todas las respuestas
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
header("Content-Type: application/json; charset=UTF-8");

$control = $_GET['control'] ?? '';

try {
    switch ($control) {
        case 'obtenerErrores':
            // Obtener la ruta del archivo de log de PHP
            $logFile = ini_get('error_log');
            $logFileFound = false;
            $debugInfo = [];
            
            // Si no está configurado, usar la ruta por defecto de XAMPP
            if (empty($logFile) || !file_exists($logFile)) {
                // Intentar rutas comunes de XAMPP
                $possiblePaths = [
                    'C:/xampp/php/logs/php_error_log',
                    'C:/xampp/apache/logs/error.log',
                    __DIR__ . '/../../logs/error.log',
                    __DIR__ . '/../../logs/php_error_log',
                    __DIR__ . '/../../logs/sistema.log',
                    ini_get('error_log')
                ];
                
                $debugInfo[] = "Buscando archivo de log en rutas posibles...";
                foreach ($possiblePaths as $path) {
                    if ($path && file_exists($path)) {
                        $logFile = $path;
                        $logFileFound = true;
                        $debugInfo[] = "Archivo encontrado: " . $path;
                        break;
                    } else if ($path) {
                        $debugInfo[] = "No encontrado: " . $path;
                    }
                }
            } else {
                $logFileFound = true;
                $debugInfo[] = "Archivo encontrado en configuración: " . $logFile;
            }
            
            // Si no se encontró, crear un archivo de log personalizado
            if (!$logFileFound) {
                $customLogPath = __DIR__ . '/../../logs/sistema.log';
                $logDir = dirname($customLogPath);
                
                // Crear directorio si no existe
                if (!is_dir($logDir)) {
                    @mkdir($logDir, 0755, true);
                }
                
                // Crear archivo si no existe
                if (!file_exists($customLogPath)) {
                    @file_put_contents($customLogPath, "[" . date('Y-m-d H:i:s') . "] Sistema de logs iniciado\n");
                }
                
                $logFile = $customLogPath;
                $logFileFound = true;
                $debugInfo[] = "Usando archivo de log personalizado: " . $customLogPath;
            }
            
            if (!$logFileFound || !file_exists($logFile)) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "No se encontró el archivo de log. Ruta configurada: " . ini_get('error_log'),
                    "debug" => $debugInfo,
                    "errores" => []
                ]);
                exit;
            }
            
            // Verificar permisos de lectura
            if (!is_readable($logFile)) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "No se tienen permisos para leer el archivo de log: " . $logFile,
                    "debug" => $debugInfo,
                    "errores" => []
                ]);
                exit;
            }
            
            // Obtener parámetros de paginación
            $limite = isset($_GET['limite']) ? intval($_GET['limite']) : 100;
            $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
            
            // Leer el archivo de log
            $fileContent = @file_get_contents($logFile);
            
            if ($fileContent === false) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "No se pudo leer el archivo de log. Archivo: " . $logFile,
                    "debug" => $debugInfo,
                    "errores" => []
                ]);
                exit;
            }
            
            // Si el archivo está vacío, retornar vacío
            if (empty(trim($fileContent))) {
                echo json_encode([
                    "resultado" => "ok",
                    "mensaje" => "El archivo de log está vacío",
                    "archivo_log" => $logFile,
                    "errores" => [],
                    "total" => 0,
                    "limite" => $limite,
                    "offset" => $offset
                ]);
                exit;
            }
            
            // Dividir en líneas
            $lines = explode("\n", $fileContent);
            
            // Filtrar líneas vacías y limpiar
            $lines = array_filter($lines, function($line) {
                return !empty(trim($line));
            });
            
            // Reindexar array
            $lines = array_values($lines);
            
            // Invertir el array para mostrar los errores más recientes primero
            $lines = array_reverse($lines);
            
            // Aplicar paginación
            $totalErrores = count($lines);
            $erroresPaginados = array_slice($lines, $offset, $limite);
            
            // Formatear los errores
            $erroresFormateados = [];
            foreach ($erroresPaginados as $line) {
                if (empty(trim($line))) continue;
                
                // Intentar parsear la línea del log
                // Formato común: [Fecha Hora] Tipo: Mensaje en archivo línea
                $error = [
                    'mensaje' => trim($line),
                    'fecha' => null,
                    'tipo' => 'INFO',
                    'archivo' => null,
                    'linea' => null
                ];
                
                // Intentar extraer fecha en diferentes formatos
                // Formato 1: [YYYY-MM-DD HH:MM:SS]
                if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                    $error['fecha'] = $matches[1];
                }
                // Formato 2: [DD-MM-YYYY HH:MM:SS]
                elseif (preg_match('/\[(\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                    $error['fecha'] = $matches[1];
                }
                // Formato 3: [Mon DD HH:MM:SS YYYY]
                elseif (preg_match('/\[([A-Za-z]{3} \d{2} \d{2}:\d{2}:\d{2} \d{4})\]/', $line, $matches)) {
                    $error['fecha'] = $matches[1];
                }
                
                // Intentar detectar tipo de error (más específico primero)
                $lineLower = strtolower($line);
                if (stripos($line, '❌') !== false || 
                    preg_match('/\b(error|exception|fatal|critical)\b/i', $line)) {
                    $error['tipo'] = 'ERROR';
                } elseif (stripos($line, '⚠️') !== false || 
                          preg_match('/\b(warning|warn)\b/i', $line)) {
                    $error['tipo'] = 'WARNING';
                } elseif (preg_match('/\b(notice|info)\b/i', $line)) {
                    $error['tipo'] = 'NOTICE';
                } elseif (stripos($line, '✅') !== false || 
                          stripos($line, '📱') !== false ||
                          stripos($line, '🔵') !== false ||
                          stripos($line, '🟢') !== false ||
                          stripos($line, '🟡') !== false) {
                    $error['tipo'] = 'INFO';
                }
                
                // Intentar extraer archivo y línea si están presentes
                // Formato 1: in /path/to/file.php on line 123
                if (preg_match('/in\s+(.+?)\s+on\s+line\s+(\d+)/i', $line, $matches)) {
                    $error['archivo'] = basename(trim($matches[1]));
                    $error['linea'] = intval($matches[2]);
                }
                // Formato 2: /path/to/file.php:123
                elseif (preg_match('/([\/\\\\][^\s:]+):(\d+)/', $line, $matches)) {
                    $error['archivo'] = basename($matches[1]);
                    $error['linea'] = intval($matches[2]);
                }
                
                $erroresFormateados[] = $error;
            }
            
            echo json_encode([
                "resultado" => "ok",
                "errores" => $erroresFormateados,
                "total" => $totalErrores,
                "limite" => $limite,
                "offset" => $offset,
                "archivo_log" => $logFile,
                "debug" => $debugInfo
            ]);
            break;
            
        case 'limpiarErrores':
            // Solo permitir limpiar si es una solicitud POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "Método no permitido"
                ]);
                exit;
            }
            
            $logFile = ini_get('error_log');
            
            // Intentar rutas comunes de XAMPP
            if (empty($logFile) || !file_exists($logFile)) {
                $possiblePaths = [
                    'C:/xampp/php/logs/php_error_log',
                    'C:/xampp/apache/logs/error.log',
                    __DIR__ . '/../../logs/error.log'
                ];
                
                foreach ($possiblePaths as $path) {
                    if ($path && file_exists($path)) {
                        $logFile = $path;
                        break;
                    }
                }
            }
            
            if (empty($logFile) || !file_exists($logFile)) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "No se encontró el archivo de log"
                ]);
                exit;
            }
            
            // Crear backup antes de limpiar
            $backupFile = $logFile . '.backup.' . date('Y-m-d_H-i-s');
            if (copy($logFile, $backupFile)) {
                // Limpiar el archivo
                file_put_contents($logFile, '');
                
                echo json_encode([
                    "resultado" => "ok",
                    "mensaje" => "Errores limpiados correctamente. Backup guardado en: " . basename($backupFile)
                ]);
            } else {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "No se pudo crear el backup del archivo de log"
                ]);
            }
            break;
            
        case 'escribirErrorPrueba':
            // Escribir un error de prueba para verificar que el sistema funciona
            $testMessage = "[" . date('Y-m-d H:i:s') . "] PRUEBA: Este es un mensaje de prueba del sistema de logs\n";
            
            // Intentar escribir en el archivo de log del sistema
            $logFile = ini_get('error_log');
            if (empty($logFile) || !file_exists($logFile)) {
                $customLogPath = __DIR__ . '/../../logs/sistema.log';
                $logDir = dirname($customLogPath);
                
                if (!is_dir($logDir)) {
                    @mkdir($logDir, 0755, true);
                }
                
                $logFile = $customLogPath;
            }
            
            // Escribir el mensaje de prueba
            if (@file_put_contents($logFile, $testMessage, FILE_APPEND | LOCK_EX) !== false) {
                echo json_encode([
                    "resultado" => "ok",
                    "mensaje" => "Mensaje de prueba escrito correctamente en: " . $logFile
                ]);
            } else {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "No se pudo escribir en el archivo de log: " . $logFile
                ]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "Control no válido"
            ]);
            break;
    }
} catch (Exception $e) {
    error_log("Error en erroresControlador: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "resultado" => "error",
        "mensaje" => "Error interno del servidor: " . $e->getMessage()
    ]);
}
