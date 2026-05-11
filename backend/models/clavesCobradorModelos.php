<?php
/**
 * Modelo de Claves Dinámicas para Cobradores
 * Maneja la generación y gestión de claves temporales
 */
class ClavesCobrador {
    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    /**
     * Genera una clave aleatoria de 8 dígitos
     * @return string Clave de 8 dígitos
     */
    private function generarClaveAleatoria() {
        return str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    /**
     * Desactiva todas las claves anteriores de un usuario
     * @param int $idUsuario ID del usuario
     * @return bool True si se desactivaron correctamente
     */
    private function desactivarClavesAnteriores($idUsuario) {
        try {
            $sql = "UPDATE claves_cobrador 
                    SET activa = 0 
                    WHERE id_usuario = :id_usuario";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":id_usuario", $idUsuario, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error al desactivar claves anteriores: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Genera una nueva clave dinámica para un cobrador
     * @param int $idUsuario ID del usuario cobrador
     * @return array Resultado con la clave generada
     */
    public function generarClave($idUsuario) {
        try {
            // Verificar que el usuario sea cobrador
            $sqlVerificar = "SELECT rol FROM usuarios WHERE id_usuario = :id_usuario";
            $stmtVerificar = $this->conexion->prepare($sqlVerificar);
            $stmtVerificar->bindParam(":id_usuario", $idUsuario, PDO::PARAM_INT);
            $stmtVerificar->execute();
            $usuario = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                return [
                    "resultado" => "error",
                    "mensaje" => "Usuario no encontrado"
                ];
            }

            if ($usuario['rol'] !== 'cobrador') {
                return [
                    "resultado" => "error",
                    "mensaje" => "Las claves dinámicas solo están disponibles para cobradores"
                ];
            }

            // Desactivar claves anteriores
            $this->desactivarClavesAnteriores($idUsuario);

            // Generar nueva clave
            $claveNueva = $this->generarClaveAleatoria();
            $fechaHoy = date('Y-m-d');

            // Insertar nueva clave
            $sql = "INSERT INTO claves_cobrador (id_usuario, clave, fecha, activa) 
                    VALUES (:id_usuario, :clave, :fecha, 1)";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":id_usuario", $idUsuario, PDO::PARAM_INT);
            $stmt->bindParam(":clave", $claveNueva);
            $stmt->bindParam(":fecha", $fechaHoy);
            
            if ($stmt->execute()) {
                return [
                    "resultado" => "ok",
                    "mensaje" => "Clave generada exitosamente",
                    "clave" => $claveNueva,
                    "fecha" => $fechaHoy,
                    "vigencia" => "Válida hasta las 00:00 de hoy"
                ];
            } else {
                return [
                    "resultado" => "error",
                    "mensaje" => "Error al generar la clave"
                ];
            }
        } catch (Exception $e) {
            error_log("Error al generar clave: " . $e->getMessage());
            return [
                "resultado" => "error",
                "mensaje" => "Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene la clave activa del día para un usuario
     * @param int $idUsuario ID del usuario
     * @return array Clave activa o null
     */
    public function obtenerClaveActiva($idUsuario) {
        try {
            $fechaHoy = date('Y-m-d');
            
            $sql = "SELECT * FROM claves_cobrador 
                    WHERE id_usuario = :id_usuario 
                    AND fecha = :fecha 
                    AND activa = 1 
                    ORDER BY fecha_creacion DESC 
                    LIMIT 1";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":id_usuario", $idUsuario, PDO::PARAM_INT);
            $stmt->bindParam(":fecha", $fechaHoy);
            $stmt->execute();
            
            $clave = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                "resultado" => "ok",
                "clave" => $clave
            ];
        } catch (Exception $e) {
            error_log("Error al obtener clave activa: " . $e->getMessage());
            return [
                "resultado" => "error",
                "mensaje" => "Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Consulta todas las claves de un usuario
     * @param int $idUsuario ID del usuario
     * @return array Lista de claves
     */
    public function consultarPorUsuario($idUsuario) {
        try {
            $sql = "SELECT * FROM claves_cobrador 
                    WHERE id_usuario = :id_usuario 
                    ORDER BY fecha_creacion DESC 
                    LIMIT 30";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":id_usuario", $idUsuario, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error al consultar claves: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Desactiva todas las claves expiradas (anteriores al día actual)
     * Esta función debe ejecutarse automáticamente a medianoche
     * @return array Resultado de la operación
     */
    public function desactivarClavesExpiradas() {
        try {
            $fechaHoy = date('Y-m-d');
            
            $sql = "UPDATE claves_cobrador 
                    SET activa = 0 
                    WHERE fecha < :fecha 
                    AND activa = 1";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":fecha", $fechaHoy);
            
            if ($stmt->execute()) {
                $filasAfectadas = $stmt->rowCount();
                return [
                    "resultado" => "ok",
                    "mensaje" => "Claves expiradas desactivadas",
                    "claves_desactivadas" => $filasAfectadas
                ];
            } else {
                return [
                    "resultado" => "error",
                    "mensaje" => "Error al desactivar claves expiradas"
                ];
            }
        } catch (Exception $e) {
            error_log("Error al desactivar claves expiradas: " . $e->getMessage());
            return [
                "resultado" => "error",
                "mensaje" => "Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Valida una clave para un usuario
     * @param int $idUsuario ID del usuario
     * @param string $clave Clave a validar
     * @return array Resultado de la validación
     */
    public function validarClave($idUsuario, $clave) {
        try {
            $fechaHoy = date('Y-m-d');
            
            $sql = "SELECT * FROM claves_cobrador 
                    WHERE id_usuario = :id_usuario 
                    AND clave = :clave 
                    AND fecha = :fecha 
                    AND activa = 1";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":id_usuario", $idUsuario, PDO::PARAM_INT);
            $stmt->bindParam(":clave", $clave);
            $stmt->bindParam(":fecha", $fechaHoy);
            $stmt->execute();
            
            $claveValida = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($claveValida) {
                return [
                    "resultado" => "ok",
                    "mensaje" => "Clave válida",
                    "valida" => true
                ];
            } else {
                return [
                    "resultado" => "error",
                    "mensaje" => "Clave inválida o expirada",
                    "valida" => false
                ];
            }
        } catch (Exception $e) {
            error_log("Error al validar clave: " . $e->getMessage());
            return [
                "resultado" => "error",
                "mensaje" => "Error: " . $e->getMessage(),
                "valida" => false
            ];
        }
    }
}
?>
