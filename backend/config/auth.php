<?php
/**
 * Autenticación y roles del lado del servidor.
 *
 * Token firmado (HMAC-SHA256, sin dependencias): base64url(payload).base64url(firma).
 * - loginControlador llama a emitirToken() tras un login correcto.
 * - Todos los demás controladores llaman a requerirAuth() justo después de leer $control.
 * El frontend lo envía en el header Authorization: Bearer <token>.
 */

const AUTH_TTL = 43200; // 12 horas

// Acciones que solo puede ejecutar un admin: controlador => acciones, o '*' para todas.
// Espejo de lo que el frontend ya oculta con AdminGuard; aquí se aplica de verdad.
const ACCIONES_SOLO_ADMIN = [
    'dashboardControlador.php'      => '*',
    'informesControlador.php'       => '*',
    'erroresControlador.php'        => '*',
    'clavesCobradorControlador.php' => '*',
    'usuariosControlador.php'       => ['insertar', 'eliminar', 'editar', 'cambiarEstado'],
    'rutasControlador.php'          => ['insertar', 'eliminar', 'editar', 'estado'],
    'usuarioRutaControlador.php'    => ['insertar', 'eliminar'],
    'cajasControlador.php'          => ['consultarCajasAbiertasConResumen', 'consultarCajasCerradasConResumen'],
    'movimientosCajaControlador.php' => ['consultarTodos'],
];

function b64u(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

// Secreto: AUTH_SECRET en key.php, o uno aleatorio que se genera una vez en config/.auth_secret (ignorado por git).
function authSecret(): string
{
    if (defined('AUTH_SECRET') && AUTH_SECRET !== '') {
        return AUTH_SECRET;
    }
    $f = __DIR__ . '/.auth_secret';
    if (!is_file($f)) {
        file_put_contents($f, bin2hex(random_bytes(32)), LOCK_EX);
    }
    return trim(file_get_contents($f));
}

function emitirToken(int $idUsuario, string $rol): string
{
    $p = b64u(json_encode(['id' => $idUsuario, 'rol' => $rol, 'exp' => time() + AUTH_TTL]));
    return $p . '.' . b64u(hash_hmac('sha256', $p, authSecret(), true));
}

function denegar(int $codigo, string $mensaje): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['estado' => 'error', 'resultado' => 'error', 'mensaje' => $mensaje]);
    exit;
}

function requerirAuth(): array
{
    // Pisa los CORS de cada controlador: muchos no permitían Authorization.
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Max-Age: 3600');
        http_response_code(200);
        exit;
    }

    // Apache/CGI a veces no pasa Authorization a $_SERVER; se revisa también en getallheaders().
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? (function_exists('getallheaders') ? (array_change_key_case(getallheaders())['authorization'] ?? '') : '');
    if (!preg_match('/^Bearer\s+([\w-]+)\.([\w-]+)$/', $h, $m)) {
        denegar(401, 'Sesión requerida');
    }
    if (!hash_equals(b64u(hash_hmac('sha256', $m[1], authSecret(), true)), $m[2])) {
        denegar(401, 'Token inválido');
    }
    $p = json_decode(base64_decode(strtr($m[1], '-_', '+/')), true);
    if (!is_array($p) || ($p['exp'] ?? 0) < time()) {
        denegar(401, 'Sesión expirada');
    }

    $regla = ACCIONES_SOLO_ADMIN[basename($_SERVER['SCRIPT_NAME'])] ?? [];
    $accion = $_GET['control'] ?? $_POST['control'] ?? '';
    if (($regla === '*' || in_array($accion, (array) $regla, true)) && $p['rol'] !== 'admin') {
        denegar(403, 'No tiene permisos para esta acción');
    }
    return $p;
}
