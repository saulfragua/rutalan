<?php

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

require_once "../config/conexion.php";
require_once "../models/usuariosModelos.php";
require_once "../models/cajasModelos.php";
require_once "../models/usuarioRutaModelos.php";
require_once "../models/clavesCobradorModelos.php";
require_once "../config/key.php";

$control = $_GET['control'] ?? '';
$usuarios = new Usuarios($conexion);
$cajas = new Cajas($conexion);
$usuarioRuta = new UsuarioRuta($conexion);
$clavesCobrador = new ClavesCobrador($conexion);

switch ($control) {
    case 'login':
        try {
            // Verificar que sea una petición POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode([
                    'estado' => 'error',
                    'mensaje' => 'Método no permitido. Use POST.'
                ]);
                exit;
            }

            $json = file_get_contents('php://input');

            if (empty($json)) {
                echo json_encode([
                    'estado' => 'error',
                    'mensaje' => 'No se recibieron datos'
                ]);
                exit;
            }

            $params = json_decode($json, true);

            // Agrega la verificación del reCAPTCHA:
            $recaptchaToken = $params['recaptcha'] ?? '';
            $secretKey = RECAPTCHA_SECRET;

            if (empty($recaptchaToken)) {
                echo json_encode([
                    'estado' => 'error',
                    'mensaje' => 'Por favor completa el reCAPTCHA'
                ]);
                exit;
            }

            $verificacion = file_get_contents(
                "https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$recaptchaToken}"
            );
            $resultado = json_decode($verificacion);

            if (!$resultado->success) {
                echo json_encode([
                    'estado' => 'error',
                    'mensaje' => 'reCAPTCHA inválido, intenta de nuevo'
                ]);
                exit;
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode([
                    'estado' => 'error',
                    'mensaje' => 'Error en el formato de los datos: ' . json_last_error_msg()
                ]);
                exit;
            }

            // Validar que los parámetros existan
            if (!isset($params['usuario']) || !isset($params['clave'])) {
                echo json_encode([
                    'estado' => 'error',
                    'mensaje' => 'Usuario y contraseña son requeridos'
                ]);
                exit;
            }

            $usuario = trim($params['usuario']);
            $clave = trim($params['clave']);

            // Validar que no estén vacíos
            if (empty($usuario) || empty($clave)) {
                echo json_encode([
                    'estado' => 'error',
                    'mensaje' => 'Usuario y contraseña no pueden estar vacíos'
                ]);
                exit;
            }

            // Intentar login normal primero
            $vec = $usuarios->login($usuario, $clave);

            // Si el login normal falla y la clave tiene 8 dígitos, intentar con clave dinámica
            if ((!$vec || $vec['estado'] !== 'ok') && strlen($clave) === 8 && ctype_digit($clave)) {
                // Buscar el usuario por nombre de usuario
                $usuarioBuscado = $usuarios->buscarPorNombreUsuario($usuario);

                if ($usuarioBuscado && $usuarioBuscado['rol'] === 'cobrador') {
                    // Validar clave dinámica
                    $resultadoClave = $clavesCobrador->validarClave($usuarioBuscado['id_usuario'], $clave);

                    if ($resultadoClave['resultado'] === 'ok' && $resultadoClave['valida'] === true) {
                        // Clave dinámica válida - crear respuesta de login exitoso
                        $vec = [
                            'estado' => 'ok',
                            'mensaje' => 'Login exitoso con clave dinámica',
                            'usuario' => [
                                'id_usuario' => $usuarioBuscado['id_usuario'],
                                'nombre_completo' => $usuarioBuscado['nombre_completo'],
                                'nombre_usuario' => $usuarioBuscado['nombre_usuario'],
                                'rol' => $usuarioBuscado['rol'],
                                'email' => $usuarioBuscado['email'],
                                'estado' => $usuarioBuscado['estado']
                            ]
                        ];
                    }
                }
            }

            if (!$vec) {
                echo json_encode([
                    'estado' => 'error',
                    'mensaje' => 'Error al procesar el login'
                ]);
                exit;
            }

            // Si el login fue exitoso, validar caja para cobradores
            if ($vec['estado'] === 'ok' && isset($vec['usuario'])) {
                $rol = $vec['usuario']['rol'];
                $idUsuario = $vec['usuario']['id_usuario'];

                // Obtener las rutas asignadas al usuario
                $rutasAsignadas = $usuarioRuta->filtrar($idUsuario);
                $listaRutas = [];

                if (!empty($rutasAsignadas)) {
                    foreach ($rutasAsignadas as $ruta) {
                        if (isset($ruta['id_ruta'])) {
                            $listaRutas[] = [
                                'id_ruta' => (int) $ruta['id_ruta'],
                                'nombre_ruta' => $ruta['nombre_ruta'] ?? 'Ruta ' . $ruta['id_ruta']
                            ];
                        }
                    }
                }

                // Si es cobrador, validar caja
                if ($rol === 'cobrador') {
                    $cajaAbierta = $cajas->obtenerCajaAbierta($idUsuario);

                    $vec['usuario']['rutas_asignadas'] = $listaRutas;
                    $vec['usuario']['tiene_caja_abierta'] = !empty($cajaAbierta);

                    if (empty($cajaAbierta)) {
                        // No tiene caja abierta, debe abrirla
                        $vec['requiere_apertura_caja'] = true;
                        $vec['mensaje'] = 'Debe abrir caja para continuar';
                    } else {
                        // Tiene caja abierta, incluir información de la caja
                        $vec['usuario']['id_caja'] = $cajaAbierta['id_caja'];
                        $vec['requiere_apertura_caja'] = false;
                    }
                } else {
                    // Admin no requiere caja
                    $vec['usuario']['rutas_asignadas'] = $listaRutas;
                    $vec['requiere_apertura_caja'] = false;
                }
            }

            echo json_encode($vec);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'estado' => 'error',
                'mensaje' => 'Error de base de datos: ' . $e->getMessage()
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'estado' => 'error',
                'mensaje' => 'Error al procesar el login: ' . $e->getMessage()
            ]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'estado' => 'error',
            'mensaje' => 'Control no válido'
        ]);
        break;
}

?>