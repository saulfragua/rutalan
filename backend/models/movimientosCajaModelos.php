<?php 
/**
 * Modelo de Movimientos de Caja
 * Maneja todas las operaciones relacionadas con entradas y salidas de dinero en cajas
 */
class MovimientosCaja {
    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    /**
     * Registra un movimiento de entrada o salida de dinero
     * @param array $params Parámetros del movimiento
     * @return array Resultado de la operación
     */
    public function registrarMovimiento($params) {
        try {
            $this->conexion->beginTransaction();

            // Validar que la caja existe y está abierta
            $sqlCaja = "SELECT id_caja, estado FROM cajas WHERE id_caja = :id_caja";
            $stmtCaja = $this->conexion->prepare($sqlCaja);
            $stmtCaja->bindParam(":id_caja", $params['id_caja'], PDO::PARAM_INT);
            $stmtCaja->execute();
            $caja = $stmtCaja->fetch(PDO::FETCH_ASSOC);

            if (!$caja) {
                $this->conexion->rollBack();
                return [
                    "resultado" => "error",
                    "mensaje" => "La caja no existe"
                ];
            }

            if ($caja['estado'] !== 'ABIERTA') {
                $this->conexion->rollBack();
                return [
                    "resultado" => "error",
                    "mensaje" => "La caja debe estar abierta para registrar movimientos"
                ];
            }

            // Validar monto
            $monto = floatval($params['monto']);
            if ($monto <= 0) {
                $this->conexion->rollBack();
                return [
                    "resultado" => "error",
                    "mensaje" => "El monto debe ser mayor a 0"
                ];
            }

            $tipo = $params['tipo']; // 'entrada' o 'salida'
            $causal = $params['causal'] ?? '';
            $metodoPago = $params['metodo_pago'] ?? 'efectivo';
            $observacion = $params['observacion'] ?? '';
            $idUsuario = $params['id_usuario'];
            $idCaja = $params['id_caja'];
            $fechaMovimiento = date('Y-m-d H:i:s');

            // Insertar el movimiento
            $sql = "INSERT INTO movimientos_caja 
                    (id_caja, id_usuario, tipo, monto, causal, metodo_pago, observacion, fecha_movimiento) 
                    VALUES 
                    (:id_caja, :id_usuario, :tipo, :monto, :causal, :metodo_pago, :observacion, :fecha_movimiento)";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
            $stmt->bindParam(":id_usuario", $idUsuario, PDO::PARAM_INT);
            $stmt->bindParam(":tipo", $tipo, PDO::PARAM_STR);
            $stmt->bindParam(":monto", $monto, PDO::PARAM_STR);
            $stmt->bindParam(":causal", $causal, PDO::PARAM_STR);
            $stmt->bindParam(":metodo_pago", $metodoPago, PDO::PARAM_STR);
            $stmt->bindParam(":observacion", $observacion, PDO::PARAM_STR);
            $stmt->bindParam(":fecha_movimiento", $fechaMovimiento, PDO::PARAM_STR);
            
            if (!$stmt->execute()) {
                $errorInfo = $stmt->errorInfo();
                $this->conexion->rollBack();
                error_log("Error SQL al insertar movimiento: " . print_r($errorInfo, true));
                return [
                    "resultado" => "error",
                    "mensaje" => "Error al registrar el movimiento: " . ($errorInfo[2] ?? "Error desconocido")
                ];
            }

            $idMovimiento = $this->conexion->lastInsertId();

            $this->conexion->commit();

            return [
                "resultado" => "ok",
                "mensaje" => "Movimiento registrado correctamente",
                "id_movimiento" => $idMovimiento
            ];
        } catch (PDOException $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            error_log("Error PDO al registrar movimiento de caja: " . $e->getMessage());
            $mensajeError = "Error de base de datos";
            // Si el error es porque la tabla no existe, dar un mensaje más claro
            if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "no existe") !== false) {
                $mensajeError = "La tabla movimientos_caja no existe. Por favor ejecute el script SQL para crearla.";
            }
            return [
                "resultado" => "error",
                "mensaje" => $mensajeError . ": " . $e->getMessage()
            ];
        } catch (Exception $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            error_log("Error al registrar movimiento de caja: " . $e->getMessage());
            return [
                "resultado" => "error",
                "mensaje" => "Error al registrar el movimiento: " . $e->getMessage()
            ];
        }
    }

    /**
     * Consulta todos los movimientos de una caja
     * @param int $idCaja ID de la caja
     * @return array Lista de movimientos
     */
    public function consultarPorCaja($idCaja) {
        try {
            $sql = "SELECT 
                        mc.*,
                        u.nombre_completo AS nombre_usuario
                    FROM movimientos_caja mc
                    LEFT JOIN usuarios u ON mc.id_usuario = u.id_usuario
                    WHERE mc.id_caja = :id_caja
                    ORDER BY mc.fecha_movimiento DESC";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error al consultar movimientos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Consulta todos los movimientos de todas las cajas
     * @return array Lista de movimientos
     */
    public function consultarTodos() {
        try {
            $sql = "SELECT 
                        mc.*,
                        u.nombre_completo AS nombre_usuario,
                        c.nombre_caja
                    FROM movimientos_caja mc
                    LEFT JOIN usuarios u ON mc.id_usuario = u.id_usuario
                    LEFT JOIN cajas c ON mc.id_caja = c.id_caja
                    ORDER BY mc.fecha_movimiento DESC";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error al consultar todos los movimientos: " . $e->getMessage());
            return [];
        }
    }
}
?>
