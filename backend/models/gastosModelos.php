<?php 
/**
 * Modelo de Gastos
 * Maneja todas las operaciones relacionadas con gastos en la base de datos
 */
class Gastos {
    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    /**
     * Consulta todos los gastos con información de ruta y usuario
     * @return array Lista de gastos
     */
    public function consultar() {
        $sql = "SELECT 
                    g.id_gasto,
                    g.id_ruta,
                    g.id_usuario,
                    g.id_caja,
                    g.descripcion,
                    g.monto,
                    g.fecha_gasto,
                    g.hora_gasto,
                    r.nombre_ruta,
                    u.nombre_usuario,
                    c.nombre_caja
                FROM gastos g
                LEFT JOIN rutas r ON g.id_ruta = r.id_ruta
                LEFT JOIN usuarios u ON g.id_usuario = u.id_usuario
                LEFT JOIN cajas c ON g.id_caja = c.id_caja
                ORDER BY g.fecha_gasto DESC, g.hora_gasto DESC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Consulta gastos por usuario
     * @param int $idUsuario ID del usuario
     * @return array Lista de gastos del usuario
     */
    public function consultarPorUsuario($idUsuario) {
        $sql = "SELECT 
                    g.id_gasto,
                    g.id_ruta,
                    g.id_usuario,
                    g.id_caja,
                    g.descripcion,
                    g.monto,
                    g.fecha_gasto,
                    g.hora_gasto,
                    r.nombre_ruta,
                    u.nombre_usuario,
                    c.nombre_caja
                FROM gastos g
                LEFT JOIN rutas r ON g.id_ruta = r.id_ruta
                LEFT JOIN usuarios u ON g.id_usuario = u.id_usuario
                LEFT JOIN cajas c ON g.id_caja = c.id_caja
                WHERE g.id_usuario = :id_usuario
                ORDER BY g.fecha_gasto DESC, g.hora_gasto DESC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(":id_usuario", $idUsuario, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Consulta gastos por caja
     * @param int $idCaja ID de la caja
     * @return array Lista de gastos de la caja
     */
    public function consultarPorCaja($idCaja) {
        $sql = "SELECT 
                    g.id_gasto,
                    g.id_ruta,
                    g.id_usuario,
                    g.id_caja,
                    g.descripcion,
                    g.monto,
                    g.fecha_gasto,
                    g.hora_gasto,
                    r.nombre_ruta,
                    u.nombre_usuario,
                    c.nombre_caja
                FROM gastos g
                LEFT JOIN rutas r ON g.id_ruta = r.id_ruta
                LEFT JOIN usuarios u ON g.id_usuario = u.id_usuario
                LEFT JOIN cajas c ON g.id_caja = c.id_caja
                WHERE g.id_caja = :id_caja
                ORDER BY g.fecha_gasto DESC, g.hora_gasto DESC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Consulta un gasto por ID
     * @param int $id ID del gasto
     * @return array|null Datos del gasto o null si no existe
     */
    public function consultarPorId($id) {
        $sql = "SELECT 
                    g.id_gasto,
                    g.id_ruta,
                    g.id_usuario,
                    g.id_caja,
                    g.descripcion,
                    g.monto,
                    g.fecha_gasto,
                    g.hora_gasto,
                    r.nombre_ruta,
                    u.nombre_usuario,
                    c.nombre_caja
                FROM gastos g
                LEFT JOIN rutas r ON g.id_ruta = r.id_ruta
                LEFT JOIN usuarios u ON g.id_usuario = u.id_usuario
                LEFT JOIN cajas c ON g.id_caja = c.id_caja
                WHERE g.id_gasto = :id
                LIMIT 1";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Inserta un nuevo gasto
     * @param array $params Parámetros del gasto
     * @return array Resultado de la operación
     */
    public function insertar($params) {
        try {
            // Validar campos obligatorios
            if (empty($params['id_usuario']) || empty($params['monto']) || empty($params['fecha_gasto'])) {
                throw new Exception("Los campos id_usuario, monto y fecha_gasto son obligatorios");
            }

            $idRuta = !empty($params['id_ruta']) ? (int)$params['id_ruta'] : null;
            $idUsuario = (int)$params['id_usuario'];
            $idCaja = !empty($params['id_caja']) ? (int)$params['id_caja'] : null;
            $descripcion = $params['descripcion'] ?? null;
            $monto = floatval($params['monto']);
            $fechaGasto = $params['fecha_gasto'];
            $horaGasto = date('H:i:s'); // Hora actual del servidor

            if ($monto <= 0) {
                throw new Exception("El monto debe ser mayor a cero");
            }

            $sql = "INSERT INTO gastos (id_ruta, id_usuario, id_caja, descripcion, monto, fecha_gasto, hora_gasto) 
                    VALUES (:id_ruta, :id_usuario, :id_caja, :descripcion, :monto, :fecha_gasto, :hora_gasto)";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":id_ruta", $idRuta, PDO::PARAM_INT);
            $stmt->bindParam(":id_usuario", $idUsuario, PDO::PARAM_INT);
            $stmt->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
            $stmt->bindParam(":descripcion", $descripcion);
            $stmt->bindParam(":monto", $monto, PDO::PARAM_STR);
            $stmt->bindParam(":fecha_gasto", $fechaGasto);
            $stmt->bindParam(":hora_gasto", $horaGasto);
            
            $stmt->execute();

            return [
                "resultado" => "success",
                "mensaje" => "Gasto insertado correctamente",
                "id_gasto" => $this->conexion->lastInsertId()
            ];
        } catch (PDOException $e) {
            error_log("Error PDO al insertar gasto: " . $e->getMessage());
            throw new Exception("Error al insertar gasto: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error al insertar gasto: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Edita un gasto existente
     * @param int $id ID del gasto
     * @param array $params Parámetros a actualizar
     * @return array Resultado de la operación
     */
    public function editar($id, $params) {
        try {
            $idRuta = !empty($params['id_ruta']) ? (int)$params['id_ruta'] : null;
            $descripcion = $params['descripcion'] ?? null;
            $monto = floatval($params['monto']);
            $fechaGasto = $params['fecha_gasto'];

            if ($monto <= 0) {
                throw new Exception("El monto debe ser mayor a cero");
            }

            $sql = "UPDATE gastos SET
                        id_ruta = :id_ruta,
                        descripcion = :descripcion,
                        monto = :monto,
                        fecha_gasto = :fecha_gasto
                    WHERE id_gasto = :id";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":id_ruta", $idRuta, PDO::PARAM_INT);
            $stmt->bindParam(":descripcion", $descripcion);
            $stmt->bindParam(":monto", $monto, PDO::PARAM_STR);
            $stmt->bindParam(":fecha_gasto", $fechaGasto);
            
            $stmt->execute();

            return [
                "resultado" => "success",
                "mensaje" => "Gasto actualizado correctamente"
            ];
        } catch (PDOException $e) {
            error_log("Error PDO al editar gasto: " . $e->getMessage());
            throw new Exception("Error al editar gasto: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error al editar gasto: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Elimina un gasto
     * @param int $id ID del gasto
     * @return array Resultado de la operación
     */
    public function eliminar($id) {
        try {
            $sql = "DELETE FROM gastos WHERE id_gasto = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->execute();

            return [
                "resultado" => "success",
                "mensaje" => "Gasto eliminado correctamente"
            ];
        } catch (PDOException $e) {
            error_log("Error PDO al eliminar gasto: " . $e->getMessage());
            throw new Exception("Error al eliminar gasto: " . $e->getMessage());
        }
    }

    /**
     * Filtra gastos por término de búsqueda
     * @param string $valor Término de búsqueda
     * @return array Lista de gastos filtrados
     */
    public function filtrar($valor) {
        $sql = "SELECT 
                    g.id_gasto,
                    g.id_ruta,
                    g.id_usuario,
                    g.id_caja,
                    g.descripcion,
                    g.monto,
                    g.fecha_gasto,
                    g.hora_gasto,
                    r.nombre_ruta,
                    u.nombre_usuario,
                    c.nombre_caja
                FROM gastos g
                LEFT JOIN rutas r ON g.id_ruta = r.id_ruta
                LEFT JOIN usuarios u ON g.id_usuario = u.id_usuario
                LEFT JOIN cajas c ON g.id_caja = c.id_caja
                WHERE g.descripcion LIKE :valor
                   OR r.nombre_ruta LIKE :valor
                ORDER BY g.fecha_gasto DESC, g.hora_gasto DESC";
        
        $stmt = $this->conexion->prepare($sql);
        $like = "%$valor%";
        $stmt->bindParam(":valor", $like);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el ID del último gasto insertado
     * @return int ID del último gasto
     */
    public function obtenerUltimoId() {
        return $this->conexion->lastInsertId();
    }
}
?>
