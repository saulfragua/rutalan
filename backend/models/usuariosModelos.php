<?php
class Usuarios {
    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    // Consulta todos los usuarios
    public function consultar() {
        $sql = "SELECT * FROM usuarios";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Elimina un usuario por ID
    public function eliminar($id) {
        $sql = "DELETE FROM usuarios WHERE id_usuario = :id";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();

        $vec = [];
        $vec['resultado'] = "Usuario eliminado correctamente";
        $vec['mensaje'] = "El usuario ha sido eliminado de la base de datos";
        return $vec;
    }

    // Inserta un nuevo usuario
public function insertar($params) {

    try {

        // Validaciones mínimas
        if (
            empty($params['nombre_completo']) ||
            empty($params['nombre_usuario']) ||
            empty($params['rol']) ||
            empty($params['clave'])
        ) {
            return [
                "resultado" => "error",
                "mensaje" => "Datos obligatorios incompletos"
            ];
        }

        // Solo roles permitidos
        if (!in_array($params['rol'], ['admin', 'cobrador'])) {
            return [
                "resultado" => "error",
                "mensaje" => "Rol no permitido"
            ];
        }

        // 🟢 Estado por defecto ACTIVO = 1
        $estado = isset($params['estado']) ? (int)$params['estado'] : 1;

        $sql = "INSERT INTO usuarios 
                (nombre_completo, nombre_usuario, rol, clave, estado, email)
                VALUES 
                (:nombre_completo, :nombre_usuario, :rol, :clave, :estado, :email)";

        $stmt = $this->conexion->prepare($sql);

        $claveHash = password_hash($params['clave'], PASSWORD_BCRYPT);

        $stmt->bindParam(":nombre_completo", $params['nombre_completo'], PDO::PARAM_STR);
        $stmt->bindParam(":nombre_usuario", $params['nombre_usuario'], PDO::PARAM_STR);
        $stmt->bindParam(":rol", $params['rol'], PDO::PARAM_STR);
        $stmt->bindParam(":clave", $claveHash, PDO::PARAM_STR);
        $stmt->bindParam(":estado", $estado, PDO::PARAM_INT);
        $stmt->bindParam(":email", $params['email'], PDO::PARAM_STR);

        $stmt->execute();

        // Obtener el ID del usuario recién creado
        $idUsuario = $this->conexion->lastInsertId();

        return [
            "resultado" => "ok",
            "mensaje" => "Usuario creado correctamente",
            "id_usuario" => (int)$idUsuario
        ];

    } catch (PDOException $e) {
        return [
            "resultado" => "error",
            "mensaje" => "Error al crear usuario",
            "debug" => $e->getMessage()
        ];
    }
}




    // Edita los datos de un usuario existente
public function editar($id, $params) {

    // Si se proporciona una nueva clave, incluirla en la actualización
    if (!empty($params['clave']) && trim($params['clave']) !== '') {
        $sql = "UPDATE usuarios SET
                  nombre_completo = :nombre_completo,
                  nombre_usuario  = :nombre_usuario,
                  rol             = :rol,
                  email           = :email,
                  estado          = :estado,
                  clave           = :clave
                WHERE id_usuario = :id";

        $stmt = $this->conexion->prepare($sql);

        // Hashear la nueva clave
        $claveHash = password_hash($params['clave'], PASSWORD_BCRYPT);

        $stmt->bindParam(':nombre_completo', $params['nombre_completo']);
        $stmt->bindParam(':nombre_usuario', $params['nombre_usuario']);
        $stmt->bindParam(':rol', $params['rol']);
        $stmt->bindParam(':email', $params['email']);
        $stmt->bindParam(':estado', $params['estado'], PDO::PARAM_INT);
        $stmt->bindParam(':clave', $claveHash, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    } else {
        // Si no se proporciona clave, actualizar sin modificar la contraseña
        $sql = "UPDATE usuarios SET
                  nombre_completo = :nombre_completo,
                  nombre_usuario  = :nombre_usuario,
                  rol             = :rol,
                  email           = :email,
                  estado          = :estado
                WHERE id_usuario = :id";

        $stmt = $this->conexion->prepare($sql);

        $stmt->bindParam(':nombre_completo', $params['nombre_completo']);
        $stmt->bindParam(':nombre_usuario', $params['nombre_usuario']);
        $stmt->bindParam(':rol', $params['rol']);
        $stmt->bindParam(':email', $params['email']);
        $stmt->bindParam(':estado', $params['estado'], PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    }

    $stmt->execute();

    $mensaje = !empty($params['clave']) && trim($params['clave']) !== ''
        ? 'Los datos y la contraseña fueron editados correctamente'
        : 'Los datos fueron editados correctamente';

    return [
        'resultado' => 'Usuario actualizado',
        'mensaje'   => $mensaje
    ];
}


    // Filtra usuarios por nombre, usuario o correo
    public function filtrar($valor) {
        $sql = "SELECT * FROM usuarios 
                WHERE nombre_completo LIKE :valor 
                   OR nombre_usuario LIKE :valor 
                   OR email LIKE :valor";

        $stmt = $this->conexion->prepare($sql);
        $like = "%$valor%";
        $stmt->bindParam(":valor", $like);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Cambia el estado de un usuario (activo/inactivo)
public function cambiarEstado($id, $estado) {

    $sql = "UPDATE usuarios SET estado = :estado WHERE id_usuario = :id";
    $stmt = $this->conexion->prepare($sql);
    $stmt->bindParam(':estado', $estado, PDO::PARAM_INT);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'resultado' => 'ok',
        'mensaje' => $estado == 1
            ? 'Usuario activado correctamente'
            : 'Usuario inactivado correctamente'
    ];
}

public function login($usuario, $clave) {

    // Log para depuración (comentar en producción)
    error_log("Intento de login - Usuario: " . $usuario);

    // Limpiar el nombre de usuario
    $usuario = trim($usuario);
    
    // Primero buscar el usuario (sin filtrar por estado para ver si existe)
    $sql = "SELECT * FROM usuarios 
            WHERE nombre_usuario = :usuario";

    $stmt = $this->conexion->prepare($sql);
    $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("Usuario no encontrado: " . $usuario);
        return [
            'estado' => 'error',
            'mensaje' => 'Usuario o contraseña incorrectos'
        ];
    }
    
    // Verificar si el usuario está activo
    if ($user['estado'] != 1) {
        error_log("Usuario inactivo: " . $usuario . " (estado: " . $user['estado'] . ")");
        return [
            'estado' => 'error',
            'mensaje' => 'Usuario inactivo. Contacte al administrador.'
        ];
    }

    error_log("Usuario encontrado - ID: " . $user['id_usuario'] . ", Estado: " . $user['estado']);
    error_log("Clave en BD (primeros 20 chars): " . substr($user['clave'], 0, 20));

    // Limpiar la contraseña recibida
    $clave = trim($clave);
    
    // Verificar contraseña: primero intentar con password_verify (hasheada)
    // Si no funciona, comparar directamente (texto plano - para compatibilidad con usuarios antiguos)
    $claveValida = false;
    
    // Verificar si la contraseña está hasheada (empieza con $2y$ o $2a$ o $2b$)
    $claveEnBD = trim($user['clave']);
    
    if (preg_match('/^\$2[ayb]\$/', $claveEnBD)) {
        // Contraseña hasheada, usar password_verify
        error_log("Verificando contraseña hasheada");
        $claveValida = password_verify($clave, $claveEnBD);
        error_log("Resultado password_verify: " . ($claveValida ? 'true' : 'false'));
        
        if (!$claveValida) {
            error_log("password_verify falló - Clave recibida: '" . $clave . "' (longitud: " . strlen($clave) . ")");
            error_log("Clave en BD (primeros 30 chars): " . substr($claveEnBD, 0, 30) . " (longitud total: " . strlen($claveEnBD) . ")");
        }
    } else {
        // Contraseña en texto plano (usuarios antiguos), comparar directamente
        error_log("Verificando contraseña en texto plano");
        error_log("Clave recibida: '" . $clave . "' (longitud: " . strlen($clave) . ")");
        error_log("Clave en BD: '" . $claveEnBD . "' (longitud: " . strlen($claveEnBD) . ")");
        
        // Comparar con trim para evitar problemas con espacios
        $claveValida = (trim($clave) === trim($claveEnBD));
        
        // Si no coincide exactamente, intentar sin case-sensitive
        if (!$claveValida) {
            $claveValida = (strtolower(trim($clave)) === strtolower(trim($claveEnBD)));
            if ($claveValida) {
                error_log("Contraseña coincide sin case-sensitive");
            }
        }
        
        error_log("Resultado comparación: " . ($claveValida ? 'true' : 'false'));
        
        // Si la contraseña es correcta y está en texto plano, actualizarla a hash
        if ($claveValida) {
            error_log("Contraseña correcta en texto plano, actualizando a hash");
            try {
                $claveHash = password_hash($clave, PASSWORD_BCRYPT);
                $updateSql = "UPDATE usuarios SET clave = :clave WHERE id_usuario = :id";
                $updateStmt = $this->conexion->prepare($updateSql);
                $updateStmt->bindParam(':clave', $claveHash);
                $updateStmt->bindParam(':id', $user['id_usuario'], PDO::PARAM_INT);
                $updateStmt->execute();
                error_log("Contraseña actualizada a hash correctamente");
            } catch (Exception $e) {
                error_log("Error al actualizar contraseña a hash: " . $e->getMessage());
                // No fallar el login si no se puede actualizar el hash
            }
        }
    }

    if ($claveValida) {
        error_log("Login exitoso para usuario: " . $usuario);
        return [
            'estado' => 'ok',
            'mensaje' => 'Login exitoso',
            'usuario' => [
                'id_usuario' => $user['id_usuario'],
                'nombre' => $user['nombre_completo'],
                'rol' => $user['rol']
            ]
        ];
    }

    error_log("Contraseña incorrecta para usuario: " . $usuario);
    error_log("Última verificación - Clave recibida (len): " . strlen($clave) . ", Clave BD (len): " . strlen($claveEnBD));
    error_log("Tipo de clave en BD: " . (preg_match('/^\$2[ayb]\$/', $claveEnBD) ? 'Hash' : 'Texto plano'));
    
    return [
        'estado' => 'error',
        'mensaje' => 'Usuario o contraseña incorrectos',
        'debug' => [
            'usuario_encontrado' => true,
            'usuario_activo' => true,
            'tipo_clave' => preg_match('/^\$2[ayb]\$/', $claveEnBD) ? 'hash' : 'texto_plano',
            'clave_recibida_len' => strlen($clave),
            'clave_bd_len' => strlen($claveEnBD)
        ]
    ];
}

/**
 * Busca un usuario por nombre de usuario
 * @param string $nombreUsuario Nombre de usuario
 * @return array|null Usuario encontrado o null
 */
public function buscarPorNombreUsuario($nombreUsuario) {
    try {
        $sql = "SELECT * FROM usuarios WHERE nombre_usuario = :usuario AND estado = 1";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':usuario', $nombreUsuario, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error al buscar usuario por nombre: " . $e->getMessage());
        return null;
    }
}


}
?>
