<?php 
/**
 * Modelo de Plan de Pagos
 * Maneja todas las operaciones relacionadas con plan de pagos en la base de datos
 */
class PlanPagos {
    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    /**
     * Consulta todos los planes de pago
     * @return array Lista de planes de pago
     */
    public function consultar() {
        $sql = "SELECT * FROM planpagos ORDER BY id_credito, numero_cuota ASC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Consulta el plan de pagos de un crédito específico
     * @param int $idCredito ID del crédito
     * @return array Lista de cuotas del plan de pago
     */
    public function consultarPorIdCredito($idCredito) {
        $sql = "SELECT 
                    pp.*,
                    c.nombres AS nombres_cliente,
                    c.apellidos AS apellidos_cliente,
                    cr.monto_credito,
                    cr.cuotas,
                    cr.tasa_interes,
                    cr.frecuencia_pago,
                    cr.saldo_actual,
                    cr.fecha_toma_credito,
                    cr.fecha_finaliza_credito,
                    cr.tipo_credito
                FROM planpagos pp
                JOIN creditos cr ON pp.id_credito = cr.id_credito
                JOIN clientes c ON cr.id_cliente = c.id_cliente
                WHERE pp.id_credito = :id_credito
                ORDER BY pp.numero_cuota ASC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(":id_credito", $idCredito, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Inserta un nuevo plan de pago
     * @param object $params Parámetros del plan de pago
     * @return array Resultado de la operación
     */
    public function insertar($params) {
        try {
            $sql = "INSERT INTO planpagos (id_credito, numero_cuota, monto_cuota, monto_restante, fecha_pago, estado) 
                    VALUES (:id_credito, :numero_cuota, :monto_cuota, :monto_restante, :fecha_pago, :estado)";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":id_credito", $params->id_credito, PDO::PARAM_INT);
            $stmt->bindParam(":numero_cuota", $params->numero_cuota, PDO::PARAM_INT);
            $stmt->bindParam(":monto_cuota", $params->monto_cuota);
            $stmt->bindParam(":monto_restante", $params->monto_restante);
            $stmt->bindParam(":fecha_pago", $params->fecha_pago);
            $stmt->bindParam(":estado", $params->estado);
            $stmt->execute();

            return [
                "resultado" => "ok",
                "mensaje" => "Plan de pago insertado correctamente"
            ];
        } catch (Exception $e) {
            return [
                "resultado" => "error",
                "mensaje" => "Error al insertar plan de pago: " . $e->getMessage()
            ];
        }
    }

    /**
     * Edita un plan de pago
     * @param int $id ID del plan de pago
     * @param object $params Parámetros a actualizar
     * @return array Resultado de la operación
     */
    public function editar($id, $params) {
        try {
            $sql = "UPDATE planpagos 
                    SET monto_cuota = :monto_cuota, 
                        monto_restante = :monto_restante, 
                        fecha_pago = :fecha_pago, 
                        estado = :estado
                    WHERE id_plan_pago = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->bindParam(":monto_cuota", $params->monto_cuota);
            $stmt->bindParam(":monto_restante", $params->monto_restante);
            $stmt->bindParam(":fecha_pago", $params->fecha_pago);
            $stmt->bindParam(":estado", $params->estado);
            $stmt->execute();

            return [
                "resultado" => "ok",
                "mensaje" => "Plan de pago actualizado correctamente"
            ];
        } catch (Exception $e) {
            return [
                "resultado" => "error",
                "mensaje" => "Error al actualizar plan de pago: " . $e->getMessage()
            ];
        }
    }

    /**
     * Elimina un plan de pago
     * @param int $id ID del plan de pago
     * @return array Resultado de la operación
     */
    public function eliminar($id) {
        try {
            $sql = "DELETE FROM planpagos WHERE id_plan_pago = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->execute();

            return [
                "resultado" => "ok",
                "mensaje" => "Plan de pago eliminado correctamente"
            ];
        } catch (Exception $e) {
            return [
                "resultado" => "error",
                "mensaje" => "Error al eliminar plan de pago: " . $e->getMessage()
            ];
        }
    }

    /**
     * Filtra planes de pago
     * @param string $dato Dato para filtrar
     * @return array Lista de planes de pago filtrados
     */
    public function filtrar($dato) {
        $sql = "SELECT * FROM planpagos 
                WHERE id_credito LIKE :dato 
                OR numero_cuota LIKE :dato
                ORDER BY id_credito, numero_cuota ASC";
        $stmt = $this->conexion->prepare($sql);
        $termino = "%" . $dato . "%";
        $stmt->bindParam(":dato", $termino);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crea el plan de pagos completo para un crédito
     * @param int $idCredito ID del crédito
     * @param float $montoCredito Monto del crédito
     * @param float $intereses Intereses calculados
     * @param int $cuotas Número de días del crédito
     * @param string $frecuenciaPago Frecuencia de pago (diario, semanal, quincenal, mensual)
     * @param string $fechaInicio Fecha de inicio del crédito (Y-m-d)
     * @return bool True si se creó correctamente
     * @throws Exception Si hay un error al crear el plan
     */
    public function crearPlanPagos($idCredito, $montoCredito, $intereses, $cuotas, $frecuenciaPago, $fechaInicio) {
        try {
            $totalAPagar = $montoCredito + $intereses;
            
            // Calcular número de pagos según la frecuencia
            $numeroPagos = match ($frecuenciaPago) {
                'diario' => $cuotas,
                'semanal' => ceil($cuotas / 8),
                'quincenal' => ceil($cuotas / 16),
                'mensual' => ceil($cuotas / 31),
                default => $cuotas
            };

            // Asegurar que haya al menos una cuota
            if ($numeroPagos < 1) {
                $numeroPagos = 1;
            }

            $montoCuota = round($totalAPagar / $numeroPagos, 2);
            
            // Ajustar la última cuota para que la suma sea exacta
            $sumaCuotas = $montoCuota * ($numeroPagos - 1);
            $ultimaCuota = $totalAPagar - $sumaCuotas;

            // Insertar cada cuota
            for ($i = 1; $i <= $numeroPagos; $i++) {
                // Calcular fecha según frecuencia
                switch ($frecuenciaPago) {
                    case 'diario':
                        $fechaPago = date('Y-m-d', strtotime("$fechaInicio +" . ($i - 1) . " days"));
                        break;
                    case 'semanal':
                        $fechaPago = date('Y-m-d', strtotime("$fechaInicio +" . (($i - 1) * 8) . " days"));
                        break;
                    case 'quincenal':
                        $fechaPago = date('Y-m-d', strtotime("$fechaInicio +" . (($i - 1) * 16) . " days"));
                        break;
                    case 'mensual':
                        $fechaPago = date('Y-m-d', strtotime("$fechaInicio +" . (($i - 1) * 31) . " days"));
                        break;
                    default:
                        $fechaPago = date('Y-m-d', strtotime("$fechaInicio +" . ($i - 1) . " days"));
                        break;
                }

                // Usar la última cuota ajustada para el último pago
                $montoCuotaActual = ($i == $numeroPagos) ? $ultimaCuota : $montoCuota;

                $sql = "INSERT INTO planpagos (id_credito, numero_cuota, monto_cuota, monto_restante, fecha_pago, estado) 
                        VALUES (:id_credito, :numero_cuota, :monto_cuota, :monto_restante, :fecha_pago, 'pendiente')";
                $stmt = $this->conexion->prepare($sql);
                
                if (!$stmt) {
                    throw new Exception("Error al preparar la consulta SQL: " . implode(", ", $this->conexion->errorInfo()));
                }
                
                // Usar bindValue en lugar de bindParam para evitar problemas en loops
                $stmt->bindValue(":id_credito", $idCredito, PDO::PARAM_INT);
                $stmt->bindValue(":numero_cuota", $i, PDO::PARAM_INT);
                // Para valores decimales, formatear correctamente
                $stmt->bindValue(":monto_cuota", number_format($montoCuotaActual, 2, '.', ''), PDO::PARAM_STR);
                $stmt->bindValue(":monto_restante", number_format($montoCuotaActual, 2, '.', ''), PDO::PARAM_STR);
                $stmt->bindValue(":fecha_pago", $fechaPago, PDO::PARAM_STR);
                
                if (!$stmt->execute()) {
                    $errorInfo = $stmt->errorInfo();
                    throw new Exception("Error al insertar cuota $i: " . ($errorInfo[2] ?? "Error desconocido"));
                }
            }

            return true;
        } catch (PDOException $e) {
            error_log("Error PDO al crear plan de pagos: " . $e->getMessage());
            throw new Exception("Error al crear plan de pagos: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error al crear plan de pagos: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Elimina todos los planes de pago de un crédito
     * @param int $idCredito ID del crédito
     * @return bool True si se eliminó correctamente
     */
    public function eliminarPorIdCredito($idCredito) {
        try {
            $sql = "DELETE FROM planpagos WHERE id_credito = :id_credito";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":id_credito", $idCredito, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error al eliminar plan de pagos: " . $e->getMessage());
            return false;
        }
    }
}
?>
