<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: origin, X-Requested-With, Content-Type, Accept");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

require_once "../config/conexion.php";
require_once "../models/clientesModelos.php";
require_once "../models/fiadoresmodelos.php";

$control = $_GET['control'] ?? '';
$clientes = new Clientes($conexion);
$fiadores = new Fiadores($conexion);

// Función para guardar imagen
function guardarImagen($archivo, $directorio, $nombreArchivo) {
    if (!isset($archivo) || !is_array($archivo) || $archivo['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // Crear directorio si no existe
    if (!file_exists($directorio)) {
        mkdir($directorio, 0777, true);
    }

    // Obtener extensión del archivo
    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $rutaCompleta = $directorio . '/' . $nombreArchivo . '.' . $extension;

    // Mover archivo
    if (move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        return $rutaCompleta;
    }

    return null;
}

switch ($control) {
    case 'consultar':
        header("Content-Type: application/json");
        $vec = $clientes->consultar();
        echo json_encode($vec);
        break;

    case 'consultarPorId':
        header("Content-Type: application/json");
        $id = $_GET['id'] ?? null;
        if ($id) {
            $vec = $clientes->consultarPorId($id);
            echo json_encode($vec);
        } else {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID no proporcionado"
            ]);
        }
        break;

    case 'insertar':
        header("Content-Type: application/json");
        
        try {
            // Verificar si es FormData o JSON
            // FormData siempre envía datos en $_POST
            // También verificar Content-Type para mayor seguridad
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $esFormData = !empty($_POST) || strpos($contentType, 'multipart/form-data') !== false;
            
            // Log para debugging (comentar en producción)
            // error_log("POST data: " . print_r($_POST, true));
            // error_log("FILES data: " . print_r($_FILES, true));
            // error_log("Content-Type: " . $contentType);
            // error_log("Es FormData: " . ($esFormData ? 'SI' : 'NO'));
            
            if ($esFormData) {
                // Procesar FormData
                $documentoCliente = trim($_POST['documento'] ?? '');
                
                // Validar que el documento no esté vacío
                if (empty($documentoCliente)) {
                    echo json_encode([
                        "resultado" => "error",
                        "mensaje" => "El documento del cliente es obligatorio"
                    ]);
                    exit;
                }
                
                $tieneFiador = isset($_POST['tiene_fiador']) && ($_POST['tiene_fiador'] === 'true' || $_POST['tiene_fiador'] === true);
                
                // Verificar si el cliente ya existe
                $clienteExistente = $clientes->buscarPorDocumento($documentoCliente);
                
                $idCliente = null;
                $idFiador = null;
                
                // Si el cliente existe
                if ($clienteExistente) {
                    $idCliente = $clienteExistente['id_cliente'];
                    
                    // Si tiene fiador, procesar fiador
                    if ($tieneFiador) {
                        $documentoFiador = trim($_POST['documento_fiador'] ?? '');
                        
                        // Validar documento del fiador
                        if (empty($documentoFiador)) {
                            echo json_encode([
                                "resultado" => "error",
                                "mensaje" => "El documento del fiador es obligatorio"
                            ]);
                            exit;
                        }
                        
                        // Verificar si el fiador ya existe
                        $fiadorExistente = $fiadores->buscarPorDocumento($documentoFiador);
                        
                        if ($fiadorExistente) {
                            $idFiador = $fiadorExistente['id_fiador'];
                        } else {
                            // Crear nuevo fiador
                            $nombresFiador = trim($_POST['nombres_fiador'] ?? '');
                            $apellidosFiador = trim($_POST['apellidos_fiador'] ?? '');
                            
                            if (empty($nombresFiador) || empty($apellidosFiador)) {
                                echo json_encode([
                                    "resultado" => "error",
                                    "mensaje" => "Los nombres y apellidos del fiador son obligatorios"
                                ]);
                                exit;
                            }
                            
                            $paramsFiador = [
                                'documento' => $documentoFiador,
                                'nombres' => $nombresFiador,
                                'apellidos' => $apellidosFiador,
                                'direccion' => trim($_POST['direccion_fiador'] ?? ''),
                                'telefono' => trim($_POST['telefono_fiador'] ?? ''),
                                'telefono2' => trim($_POST['telefono2_fiador'] ?? ''),
                                'activo' => 1,
                                'foto_fiador' => '',
                                'foto_cedula_frontal' => '',
                                'foto_cedula_atras' => ''
                            ];
                            
                            try {
                                $fiadores->insertar($paramsFiador);
                                $idFiador = $fiadores->obtenerUltimoId();
                                
                                if (!$idFiador || $idFiador <= 0) {
                                    throw new Exception("Error al obtener el ID del fiador insertado");
                                }
                            } catch (Exception $e) {
                                echo json_encode([
                                    "resultado" => "error",
                                    "mensaje" => "Error al insertar fiador: " . $e->getMessage()
                                ]);
                                exit;
                            }
                            
                            // Guardar imágenes del fiador
                            $dirFiador = "../uploads/fiadores/" . $idFiador;
                            $fotoFiador = guardarImagen($_FILES['foto_fiador'] ?? null, $dirFiador, 'fotoperfil');
                            $fotoCedulaFrontalFiador = guardarImagen($_FILES['cedula_frontal_fiador'] ?? null, $dirFiador, 'fotocedulafrontal');
                            $fotoCedulaAtrasFiador = guardarImagen($_FILES['cedula_atras_fiador'] ?? null, $dirFiador, 'fotocedulaatras');
                            
                            // Actualizar rutas de imágenes en BD (guardar solo el nombre del archivo o ruta relativa)
                            $paramsUpdateFiador = $paramsFiador;
                            if ($fotoFiador) {
                                $paramsUpdateFiador['foto_fiador'] = str_replace('../uploads/', 'uploads/', $fotoFiador);
                            }
                            if ($fotoCedulaFrontalFiador) {
                                $paramsUpdateFiador['foto_cedula_frontal'] = str_replace('../uploads/', 'uploads/', $fotoCedulaFrontalFiador);
                            }
                            if ($fotoCedulaAtrasFiador) {
                                $paramsUpdateFiador['foto_cedula_atras'] = str_replace('../uploads/', 'uploads/', $fotoCedulaAtrasFiador);
                            }
                            if ($fotoFiador || $fotoCedulaFrontalFiador || $fotoCedulaAtrasFiador) {
                                $fiadores->editar($idFiador, $paramsUpdateFiador);
                            }
                        }
                        
                        // Asignar fiador al cliente existente
                        $clientes->actualizarFiador($idCliente, $idFiador);
                    }
                    
                    echo json_encode([
                        "resultado" => "success",
                        "mensaje" => "Cliente existente. Fiador asignado correctamente.",
                        "id_cliente" => $idCliente,
                        "id_fiador" => $idFiador
                    ]);
                } else {
                    // Cliente no existe, crear nuevo cliente
                    $nombresCliente = trim($_POST['nombres'] ?? '');
                    $apellidosCliente = trim($_POST['apellidos'] ?? '');
                    
                    // Validar campos obligatorios
                    if (empty($nombresCliente) || empty($apellidosCliente)) {
                        echo json_encode([
                            "resultado" => "error",
                            "mensaje" => "Los nombres y apellidos del cliente son obligatorios"
                        ]);
                        exit;
                    }
                    
                    // Validar que el id_usuario existe si se proporciona
                    $idUsuario = !empty($_POST['id_usuario']) ? (int)$_POST['id_usuario'] : null;
                    if ($idUsuario !== null) {
                        $stmtUsuario = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = ?");
                        $stmtUsuario->execute([$idUsuario]);
                        if (!$stmtUsuario->fetch()) {
                            // El usuario no existe, permitir NULL o mostrar error
                            // Por ahora, lo dejamos como NULL para evitar el error
                            error_log("Advertencia: El id_usuario $idUsuario no existe en la tabla usuarios. Se asignará NULL.");
                            $idUsuario = null;
                        }
                    }
                    
                    $paramsCliente = [
                        'documento' => $documentoCliente,
                        'nombres' => $nombresCliente,
                        'apellidos' => $apellidosCliente,
                        'direccion' => trim($_POST['direccion'] ?? ''),
                        'telefono' => trim($_POST['telefono'] ?? ''),
                        'telefono2' => trim($_POST['telefono2'] ?? ''),
                        'id_ruta' => !empty($_POST['id_ruta']) ? (int)$_POST['id_ruta'] : null,
                        'id_usuario' => $idUsuario,
                        'orden_cobranza' => !empty($_POST['orden_cobranza']) ? (int)$_POST['orden_cobranza'] : 0,
                        'activo' => 1,
                        'foto_cliente' => '',
                        'foto_cedula_frontal' => '',
                        'foto_cedula_atras' => '',
                        'id_fiador' => null,
                        'latitud' => !empty($_POST['latitud']) ? (float)$_POST['latitud'] : null,
                        'longitud' => !empty($_POST['longitud']) ? (float)$_POST['longitud'] : null
                    ];
                    
                    // Insertar cliente
                    try {
                        $resultadoInsertar = $clientes->insertar($paramsCliente);
                        $idCliente = $clientes->obtenerUltimoId();
                        
                        if (!$idCliente || $idCliente <= 0) {
                            throw new Exception("Error al obtener el ID del cliente insertado");
                        }
                    } catch (Exception $e) {
                        echo json_encode([
                            "resultado" => "error",
                            "mensaje" => "Error al insertar cliente: " . $e->getMessage()
                        ]);
                        exit;
                    }
                    
                    // Guardar imágenes del cliente
                    $dirCliente = "../uploads/clientes/" . $idCliente;
                    $fotoCliente = guardarImagen($_FILES['foto_cliente'] ?? null, $dirCliente, 'fotoperfil');
                    $fotoCedulaFrontal = guardarImagen($_FILES['cedula_frontal'] ?? null, $dirCliente, 'fotocedulafrontal');
                    $fotoCedulaAtras = guardarImagen($_FILES['cedula_atras'] ?? null, $dirCliente, 'fotocedulaatras');
                    
                    // Actualizar rutas de imágenes en BD (guardar solo el nombre del archivo o ruta relativa)
                    $paramsUpdateCliente = $paramsCliente;
                    if ($fotoCliente) {
                        $paramsUpdateCliente['foto_cliente'] = str_replace('../uploads/', 'uploads/', $fotoCliente);
                    }
                    if ($fotoCedulaFrontal) {
                        $paramsUpdateCliente['foto_cedula_frontal'] = str_replace('../uploads/', 'uploads/', $fotoCedulaFrontal);
                    }
                    if ($fotoCedulaAtras) {
                        $paramsUpdateCliente['foto_cedula_atras'] = str_replace('../uploads/', 'uploads/', $fotoCedulaAtras);
                    }
                    if ($fotoCliente || $fotoCedulaFrontal || $fotoCedulaAtras) {
                        $clientes->editar($idCliente, $paramsUpdateCliente);
                    }
                    
                    // Si tiene fiador, procesar fiador
                    if ($tieneFiador) {
                        $documentoFiador = trim($_POST['documento_fiador'] ?? '');
                        
                        // Validar documento del fiador
                        if (empty($documentoFiador)) {
                            echo json_encode([
                                "resultado" => "error",
                                "mensaje" => "El documento del fiador es obligatorio"
                            ]);
                            exit;
                        }
                        
                        $fiadorExistente = $fiadores->buscarPorDocumento($documentoFiador);
                        
                        if ($fiadorExistente) {
                            $idFiador = $fiadorExistente['id_fiador'];
                        } else {
                            // Crear nuevo fiador
                            $nombresFiador = trim($_POST['nombres_fiador'] ?? '');
                            $apellidosFiador = trim($_POST['apellidos_fiador'] ?? '');
                            
                            if (empty($nombresFiador) || empty($apellidosFiador)) {
                                echo json_encode([
                                    "resultado" => "error",
                                    "mensaje" => "Los nombres y apellidos del fiador son obligatorios"
                                ]);
                                exit;
                            }
                            
                            $paramsFiador = [
                                'documento' => $documentoFiador,
                                'nombres' => $nombresFiador,
                                'apellidos' => $apellidosFiador,
                                'direccion' => trim($_POST['direccion_fiador'] ?? ''),
                                'telefono' => trim($_POST['telefono_fiador'] ?? ''),
                                'telefono2' => trim($_POST['telefono2_fiador'] ?? ''),
                                'activo' => 1,
                                'foto_fiador' => '',
                                'foto_cedula_frontal' => '',
                                'foto_cedula_atras' => ''
                            ];
                            
                            try {
                                $fiadores->insertar($paramsFiador);
                                $idFiador = $fiadores->obtenerUltimoId();
                                
                                if (!$idFiador || $idFiador <= 0) {
                                    throw new Exception("Error al obtener el ID del fiador insertado");
                                }
                            } catch (Exception $e) {
                                echo json_encode([
                                    "resultado" => "error",
                                    "mensaje" => "Error al insertar fiador: " . $e->getMessage()
                                ]);
                                exit;
                            }
                            
                            // Guardar imágenes del fiador
                            $dirFiador = "../uploads/fiadores/" . $idFiador;
                            $fotoFiador = guardarImagen($_FILES['foto_fiador'] ?? null, $dirFiador, 'fotoperfil');
                            $fotoCedulaFrontalFiador = guardarImagen($_FILES['cedula_frontal_fiador'] ?? null, $dirFiador, 'fotocedulafrontal');
                            $fotoCedulaAtrasFiador = guardarImagen($_FILES['cedula_atras_fiador'] ?? null, $dirFiador, 'fotocedulaatras');
                            
                            // Actualizar rutas de imágenes en BD (guardar solo el nombre del archivo o ruta relativa)
                            $paramsUpdateFiador = $paramsFiador;
                            if ($fotoFiador) {
                                $paramsUpdateFiador['foto_fiador'] = str_replace('../uploads/', 'uploads/', $fotoFiador);
                            }
                            if ($fotoCedulaFrontalFiador) {
                                $paramsUpdateFiador['foto_cedula_frontal'] = str_replace('../uploads/', 'uploads/', $fotoCedulaFrontalFiador);
                            }
                            if ($fotoCedulaAtrasFiador) {
                                $paramsUpdateFiador['foto_cedula_atras'] = str_replace('../uploads/', 'uploads/', $fotoCedulaAtrasFiador);
                            }
                            if ($fotoFiador || $fotoCedulaFrontalFiador || $fotoCedulaAtrasFiador) {
                                $fiadores->editar($idFiador, $paramsUpdateFiador);
                            }
                        }
                        
                        // Asignar fiador al cliente
                        $clientes->actualizarFiador($idCliente, $idFiador);
                    }
                    
                    echo json_encode([
                        "resultado" => "success",
                        "mensaje" => "Cliente insertado correctamente",
                        "id_cliente" => $idCliente,
                        "id_fiador" => $idFiador
                    ]);
                }
            } else {
                // Procesar JSON (método antiguo para compatibilidad)
                $json = file_get_contents('php://input');
                $params = json_decode($json, true);
                $vec = $clientes->insertar($params);
                echo json_encode($vec);
            }
        } catch (Exception $e) {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "Error al procesar: " . $e->getMessage()
            ]);
        }
        break;

    case 'eliminar':
        header("Content-Type: application/json");
        $id = $_GET['id'] ?? null;
        if ($id) {
            $vec = $clientes->eliminar($id);
            echo json_encode($vec);
        } else {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID no proporcionado"
            ]);
        }
        break;

    case 'activar':
        header("Content-Type: application/json");
        $id = $_GET['id'] ?? null;
        if ($id) {
            $vec = $clientes->activar($id);
            echo json_encode($vec);
        } else {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID no proporcionado"
            ]);
        }
        break;

    case 'inactivar':
        header("Content-Type: application/json");
        $id = $_GET['id'] ?? null;
        if ($id) {
            $vec = $clientes->inactivar($id);
            echo json_encode($vec);
        } else {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID no proporcionado"
            ]);
        }
        break;

    case 'editar':
        header("Content-Type: application/json");
        
        try {
            $id = $_GET['id'] ?? null;
            if (!$id) {
                echo json_encode([
                    "resultado" => "error",
                    "mensaje" => "ID no proporcionado"
                ]);
                exit;
            }

            // Verificar si es FormData o JSON
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $esFormData = !empty($_POST) || strpos($contentType, 'multipart/form-data') !== false;
            
            if ($esFormData) {
                // Procesar FormData para edición
                $documentoCliente = trim($_POST['documento'] ?? '');
                
                if (empty($documentoCliente)) {
                    echo json_encode([
                        "resultado" => "error",
                        "mensaje" => "El documento del cliente es obligatorio"
                    ]);
                    exit;
                }
                
                $tieneFiador = isset($_POST['tiene_fiador']) && ($_POST['tiene_fiador'] === 'true' || $_POST['tiene_fiador'] === true);
                
                // Obtener cliente actual para preservar imágenes si no se suben nuevas
                $clienteActual = $clientes->consultarPorId($id);
                if (!$clienteActual) {
                    echo json_encode([
                        "resultado" => "error",
                        "mensaje" => "Cliente no encontrado"
                    ]);
                    exit;
                }
                
                $idFiador = null;
                
                // Si tiene fiador, procesar fiador
                if ($tieneFiador) {
                    $documentoFiador = trim($_POST['documento_fiador'] ?? '');
                    
                    if (empty($documentoFiador)) {
                        echo json_encode([
                            "resultado" => "error",
                            "mensaje" => "El documento del fiador es obligatorio"
                        ]);
                        exit;
                    }
                    
                    $fiadorExistente = $fiadores->buscarPorDocumento($documentoFiador);
                    
                    if ($fiadorExistente) {
                        $idFiador = $fiadorExistente['id_fiador'];
                        
                        // Actualizar datos del fiador existente
                        $paramsFiador = [
                            'documento' => $documentoFiador,
                            'nombres' => trim($_POST['nombres_fiador'] ?? ''),
                            'apellidos' => trim($_POST['apellidos_fiador'] ?? ''),
                            'direccion' => trim($_POST['direccion_fiador'] ?? ''),
                            'telefono' => trim($_POST['telefono_fiador'] ?? ''),
                            'telefono2' => trim($_POST['telefono2_fiador'] ?? ''),
                            'activo' => 1,
                            'foto_fiador' => $fiadorExistente['foto_fiador'] ?? '',
                            'foto_cedula_frontal' => $fiadorExistente['foto_cedula_frontal'] ?? '',
                            'foto_cedula_atras' => $fiadorExistente['foto_cedula_atras'] ?? ''
                        ];
                        
                        // Guardar nuevas imágenes si se suben
                        $dirFiador = "../uploads/fiadores/" . $idFiador;
                        $fotoFiador = guardarImagen($_FILES['foto_fiador'] ?? null, $dirFiador, 'fotoperfil');
                        $fotoCedulaFrontalFiador = guardarImagen($_FILES['cedula_frontal_fiador'] ?? null, $dirFiador, 'fotocedulafrontal');
                        $fotoCedulaAtrasFiador = guardarImagen($_FILES['cedula_atras_fiador'] ?? null, $dirFiador, 'fotocedulaatras');
                        
                        if ($fotoFiador) {
                            $paramsFiador['foto_fiador'] = str_replace('../uploads/', 'uploads/', $fotoFiador);
                        }
                        if ($fotoCedulaFrontalFiador) {
                            $paramsFiador['foto_cedula_frontal'] = str_replace('../uploads/', 'uploads/', $fotoCedulaFrontalFiador);
                        }
                        if ($fotoCedulaAtrasFiador) {
                            $paramsFiador['foto_cedula_atras'] = str_replace('../uploads/', 'uploads/', $fotoCedulaAtrasFiador);
                        }
                        
                        $fiadores->editar($idFiador, $paramsFiador);
                    } else {
                        // Crear nuevo fiador
                        $nombresFiador = trim($_POST['nombres_fiador'] ?? '');
                        $apellidosFiador = trim($_POST['apellidos_fiador'] ?? '');
                        
                        if (empty($nombresFiador) || empty($apellidosFiador)) {
                            echo json_encode([
                                "resultado" => "error",
                                "mensaje" => "Los nombres y apellidos del fiador son obligatorios"
                            ]);
                            exit;
                        }
                        
                        $paramsFiador = [
                            'documento' => $documentoFiador,
                            'nombres' => $nombresFiador,
                            'apellidos' => $apellidosFiador,
                            'direccion' => trim($_POST['direccion_fiador'] ?? ''),
                            'telefono' => trim($_POST['telefono_fiador'] ?? ''),
                            'telefono2' => trim($_POST['telefono2_fiador'] ?? ''),
                            'activo' => 1,
                            'foto_fiador' => '',
                            'foto_cedula_frontal' => '',
                            'foto_cedula_atras' => ''
                        ];
                        
                        $fiadores->insertar($paramsFiador);
                        $idFiador = $fiadores->obtenerUltimoId();
                        
                        // Guardar imágenes del fiador
                        $dirFiador = "../uploads/fiadores/" . $idFiador;
                        $fotoFiador = guardarImagen($_FILES['foto_fiador'] ?? null, $dirFiador, 'fotoperfil');
                        $fotoCedulaFrontalFiador = guardarImagen($_FILES['cedula_frontal_fiador'] ?? null, $dirFiador, 'fotocedulafrontal');
                        $fotoCedulaAtrasFiador = guardarImagen($_FILES['cedula_atras_fiador'] ?? null, $dirFiador, 'fotocedulaatras');
                        
                        if ($fotoFiador) {
                            $paramsFiador['foto_fiador'] = str_replace('../uploads/', 'uploads/', $fotoFiador);
                        }
                        if ($fotoCedulaFrontalFiador) {
                            $paramsFiador['foto_cedula_frontal'] = str_replace('../uploads/', 'uploads/', $fotoCedulaFrontalFiador);
                        }
                        if ($fotoCedulaAtrasFiador) {
                            $paramsFiador['foto_cedula_atras'] = str_replace('../uploads/', 'uploads/', $fotoCedulaAtrasFiador);
                        }
                        
                        if ($fotoFiador || $fotoCedulaFrontalFiador || $fotoCedulaAtrasFiador) {
                            $fiadores->editar($idFiador, $paramsFiador);
                        }
                    }
                }
                
                // Actualizar datos del cliente
                $idUsuario = !empty($_POST['id_usuario']) ? (int)$_POST['id_usuario'] : null;
                $idRuta = !empty($_POST['id_ruta']) ? (int)$_POST['id_ruta'] : null;
                
                $paramsCliente = [
                    'documento' => $documentoCliente,
                    'nombres' => trim($_POST['nombres'] ?? ''),
                    'apellidos' => trim($_POST['apellidos'] ?? ''),
                    'direccion' => trim($_POST['direccion'] ?? ''),
                    'telefono' => trim($_POST['telefono'] ?? ''),
                    'telefono2' => trim($_POST['telefono2'] ?? ''),
                    'id_ruta' => $idRuta,
                    'orden_cobranza' => !empty($_POST['orden_cobranza']) ? (int)$_POST['orden_cobranza'] : 0,
                    'activo' => isset($_POST['activo']) ? (int)$_POST['activo'] : $clienteActual['activo'],
                    'fecha_cancelacion' => $clienteActual['fecha_cancelacion'] ?? null,
                    'foto_cliente' => $clienteActual['foto_cliente'] ?? '',
                    'foto_cedula_frontal' => $clienteActual['foto_cedula_frontal'] ?? '',
                    'foto_cedula_atras' => $clienteActual['foto_cedula_atras'] ?? '',
                    'id_fiador' => $idFiador,
                    'latitud' => !empty($_POST['latitud']) ? (float)$_POST['latitud'] : ($clienteActual['latitud'] ?? null),
                    'longitud' => !empty($_POST['longitud']) ? (float)$_POST['longitud'] : ($clienteActual['longitud'] ?? null)
                ];
                
                // Guardar nuevas imágenes si se suben
                $dirCliente = "../uploads/clientes/" . $id;
                $fotoCliente = guardarImagen($_FILES['foto_cliente'] ?? null, $dirCliente, 'fotoperfil');
                $fotoCedulaFrontal = guardarImagen($_FILES['cedula_frontal'] ?? null, $dirCliente, 'fotocedulafrontal');
                $fotoCedulaAtras = guardarImagen($_FILES['cedula_atras'] ?? null, $dirCliente, 'fotocedulaatras');
                
                if ($fotoCliente) {
                    $paramsCliente['foto_cliente'] = str_replace('../uploads/', 'uploads/', $fotoCliente);
                }
                if ($fotoCedulaFrontal) {
                    $paramsCliente['foto_cedula_frontal'] = str_replace('../uploads/', 'uploads/', $fotoCedulaFrontal);
                }
                if ($fotoCedulaAtras) {
                    $paramsCliente['foto_cedula_atras'] = str_replace('../uploads/', 'uploads/', $fotoCedulaAtras);
                }
                
                $clientes->editar($id, $paramsCliente);
                
                echo json_encode([
                    "resultado" => "success",
                    "mensaje" => "Cliente actualizado correctamente",
                    "id_cliente" => $id,
                    "id_fiador" => $idFiador
                ]);
            } else {
                // Procesar JSON (método antiguo para compatibilidad)
                $json = file_get_contents('php://input');
                $params = json_decode($json, true);
                $vec = $clientes->editar($id, $params);
                echo json_encode($vec);
            }
        } catch (Exception $e) {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "Error al procesar: " . $e->getMessage()
            ]);
        }
        break;

    case 'filtrar':
        header("Content-Type: application/json");
        $dato = $_GET['dato'] ?? '';
        $vec = $clientes->filtrar($dato);
        echo json_encode($vec);
        break;

    case 'actualizarUbicacion':
        header("Content-Type: application/json");
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "ID no proporcionado"
            ]);
            break;
        }
        
        $json = file_get_contents('php://input');
        $params = json_decode($json, true);
        
        if (empty($params['latitud']) || empty($params['longitud'])) {
            echo json_encode([
                "resultado" => "error",
                "mensaje" => "Latitud y longitud son requeridas"
            ]);
            break;
        }
        
        $vec = $clientes->actualizarUbicacion($id, $params['latitud'], $params['longitud']);
        echo json_encode($vec);
        break;

    case 'consultarConUbicacion':
        header("Content-Type: application/json");
        $id_ruta = isset($_GET['id_ruta']) ? (int)$_GET['id_ruta'] : null;
        $vec = $clientes->consultarConUbicacion($id_ruta);
        echo json_encode($vec);
        break;

    default:
        header("Content-Type: application/json");
        echo json_encode([
            "resultado" => "error",
            "mensaje" => "Control no válido"
        ]);
        break;
}

?>
