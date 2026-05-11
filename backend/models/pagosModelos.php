<?php 
/**
 * Modelo de Pagos
 * Maneja todas las operaciones relacionadas con pagos en la base de datos
 */
class Pagos {
    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    /**
     * Consulta todos los pagos
     * @return array Lista de pagos
     */
    public function consultar() {
        $sql = "SELECT p.*, c.nombres, c.apellidos, cr.id_credito 
                FROM pagos p
                LEFT JOIN clientes c ON p.id_cliente = c.id_cliente
                LEFT JOIN creditos cr ON p.id_credito = cr.id_credito
                ORDER BY p.fecha_pago DESC, p.hora_pago DESC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Consulta clientes de una ruta con saldo pendiente
     * @param int $idRuta ID de la ruta
     * @return array Lista de clientes con información de créditos
     */
    public function consultarClientesPorRuta($idRuta) {
        // Primero actualizar cuotas vencidas para todos los créditos de esta ruta
        $fechaActual = date('Y-m-d');
        $sqlActualizarVencidas = "UPDATE planpagos pp
                                  INNER JOIN creditos cr ON pp.id_credito = cr.id_credito
                                  INNER JOIN clientes c ON cr.id_cliente = c.id_cliente
                                  SET pp.estado = 'vencida'
                                  WHERE c.id_ruta = :id_ruta 
                                  AND pp.estado = 'pendiente' 
                                  AND pp.fecha_pago < :fecha_actual";
        $stmtActualizarVencidas = $this->conexion->prepare($sqlActualizarVencidas);
        $stmtActualizarVencidas->bindParam(":id_ruta", $idRuta, PDO::PARAM_INT);
        $stmtActualizarVencidas->bindParam(":fecha_actual", $fechaActual);
        $stmtActualizarVencidas->execute();

        // Actualizar cuotas con monto restante 0 a pagadas
        $sqlActualizarPagadas = "UPDATE planpagos pp
                                 INNER JOIN creditos cr ON pp.id_credito = cr.id_credito
                                 INNER JOIN clientes c ON cr.id_cliente = c.id_cliente
                                 SET pp.estado = 'pagada'
                                 WHERE c.id_ruta = :id_ruta 
                                 AND pp.monto_restante = 0";
        $stmtActualizarPagadas = $this->conexion->prepare($sqlActualizarPagadas);
        $stmtActualizarPagadas->bindParam(":id_ruta", $idRuta, PDO::PARAM_INT);
        $stmtActualizarPagadas->execute();

        // Consultar clientes con información actualizada ordenados por orden_cobranza del crédito
        $sql = "SELECT c.*, cr.id_credito, cr.saldo_actual, cr.fecha_toma_credito, cr.cuotas, 
                       cr.frecuencia_pago, cr.tipo_credito, cr.orden_cobranza, r.nombre_ruta,
                       cr.fecha_finaliza_credito, c.foto_cliente,
                       (SELECT COUNT(*) FROM pagos WHERE id_credito = cr.id_credito) AS num_pagos,
                       (SELECT MAX(fecha_pago) FROM pagos WHERE id_credito = cr.id_credito) AS ultimo_pago,
                       (SELECT COUNT(*) FROM planpagos WHERE id_credito = cr.id_credito AND estado = 'pendiente') AS cuotas_pendientes,
                       (SELECT COUNT(*) FROM planpagos WHERE id_credito = cr.id_credito AND estado = 'pagada') AS cuotas_pagadas,
                       (SELECT COUNT(*) FROM planpagos WHERE id_credito = cr.id_credito AND estado = 'vencida') AS cuotas_vencidas
                FROM clientes c
                JOIN creditos cr ON c.id_cliente = cr.id_cliente
                LEFT JOIN rutas r ON c.id_ruta = r.id_ruta
                WHERE c.id_ruta = :id_ruta AND cr.activo = 1 AND cr.saldo_actual > 0
                ORDER BY cr.orden_cobranza ASC, cr.id_credito ASC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(":id_ruta", $idRuta, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Registra un nuevo pago y actualiza el plan de pagos
     * @param array $params Parámetros del pago
     * @return array Resultado de la operación
     */
    public function registrarPago($params) {
        try {
            $this->conexion->beginTransaction();

            $idCliente = $params['id_cliente'];
            $idCredito = $params['id_credito'];
            $montoPagado = floatval($params['monto_pagado']);
            $descuento = floatval($params['descuento'] ?? 0);
            $idUsuario = $params['id_usuario'];
            $idRuta = $params['id_ruta'] ?? null;
            $idCaja = $params['id_caja'] ?? null;
            
            // Calcular monto neto (lo que se resta del crédito)
            $montoNeto = $montoPagado + $descuento;
            
            // Obtener saldo actual, orden_cobranza y ruta del crédito
            $sqlCredito = "SELECT saldo_actual, orden_cobranza, id_ruta FROM creditos WHERE id_credito = :id_credito";
            $stmtCredito = $this->conexion->prepare($sqlCredito);
            $stmtCredito->bindParam(":id_credito", $idCredito, PDO::PARAM_INT);
            $stmtCredito->execute();
            $credito = $stmtCredito->fetch(PDO::FETCH_ASSOC);
            
            if (!$credito) {
                throw new Exception("Crédito no encontrado");
            }
            
            $saldoActual = floatval($credito['saldo_actual']);
            $ordenCobranza = intval($credito['orden_cobranza'] ?? 0);
            $idRutaCredito = $credito['id_ruta'] ?? null;
            
            // Validar que el monto no sea mayor al saldo
            if ($montoNeto > $saldoActual) {
                throw new Exception("El monto a pagar no puede ser mayor que el saldo actual");
            }
            
            // Fecha y hora actual
            $fechaPago = date('Y-m-d');
            $horaPago = date('H:i:s');
            
            // Registrar el pago (monto_pagado es solo lo cobrado, descuento es aparte)
            $sqlPago = "INSERT INTO pagos (id_cliente, id_credito, fecha_pago, hora_pago, monto_pagado, descuento, id_usuario, id_ruta, id_caja) 
                       VALUES (:id_cliente, :id_credito, :fecha_pago, :hora_pago, :monto_pagado, :descuento, :id_usuario, :id_ruta, :id_caja)";
            $stmtPago = $this->conexion->prepare($sqlPago);
            $stmtPago->bindParam(":id_cliente", $idCliente, PDO::PARAM_INT);
            $stmtPago->bindParam(":id_credito", $idCredito, PDO::PARAM_INT);
            $stmtPago->bindParam(":fecha_pago", $fechaPago);
            $stmtPago->bindParam(":hora_pago", $horaPago);
            $stmtPago->bindParam(":monto_pagado", $montoPagado);
            $stmtPago->bindParam(":descuento", $descuento);
            $stmtPago->bindParam(":id_usuario", $idUsuario, PDO::PARAM_INT);
            $stmtPago->bindParam(":id_ruta", $idRuta, PDO::PARAM_INT);
            $stmtPago->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
            $stmtPago->execute();
            
            $idPago = $this->conexion->lastInsertId();
            
            // Actualizar saldo del crédito (el trigger también lo hace, pero lo hacemos explícitamente)
            $nuevoSaldo = $saldoActual - $montoNeto;
            $sqlUpdateCredito = "UPDATE creditos SET saldo_actual = :saldo_actual WHERE id_credito = :id_credito";
            $stmtUpdateCredito = $this->conexion->prepare($sqlUpdateCredito);
            $stmtUpdateCredito->bindParam(":saldo_actual", $nuevoSaldo);
            $stmtUpdateCredito->bindParam(":id_credito", $idCredito, PDO::PARAM_INT);
            $stmtUpdateCredito->execute();
            
            // Si el crédito se completó (saldo = 0), reordenar automáticamente
            if ($nuevoSaldo <= 0 && $ordenCobranza > 0) {
                require_once "creditosModelos.php";
                $creditos = new Creditos($this->conexion);
                $creditos->reordenarCobranza($idRutaCredito, $ordenCobranza);
            }
            
            // Actualizar plan de pagos
            $montoRestante = $montoNeto;
            $sqlCuotas = "SELECT id_plan_pago, monto_cuota, monto_restante 
                         FROM planpagos 
                         WHERE id_credito = :id_credito AND (estado = 'pendiente' OR estado = 'vencida') 
                         ORDER BY fecha_pago ASC";
            $stmtCuotas = $this->conexion->prepare($sqlCuotas);
            $stmtCuotas->bindParam(":id_credito", $idCredito, PDO::PARAM_INT);
            $stmtCuotas->execute();
            $cuotas = $stmtCuotas->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($cuotas as $cuota) {
                if ($montoRestante <= 0) break;
                
                $montoRestanteCuota = floatval($cuota['monto_restante']);
                $idPlanPago = $cuota['id_plan_pago'];
                
                if ($montoRestante >= $montoRestanteCuota) {
                    // Pagar la cuota completa
                    $sqlActualizarCuota = "UPDATE planpagos SET estado = 'pagada', monto_restante = 0 WHERE id_plan_pago = :id_plan_pago";
                    $stmtActualizarCuota = $this->conexion->prepare($sqlActualizarCuota);
                    $stmtActualizarCuota->bindParam(":id_plan_pago", $idPlanPago, PDO::PARAM_INT);
                    $stmtActualizarCuota->execute();
                    
                    $montoRestante -= $montoRestanteCuota;
                } else {
                    // Pagar parcialmente la cuota
                    $sqlActualizarCuota = "UPDATE planpagos SET monto_restante = monto_restante - :monto WHERE id_plan_pago = :id_plan_pago";
                    $stmtActualizarCuota = $this->conexion->prepare($sqlActualizarCuota);
                    $stmtActualizarCuota->bindParam(":monto", $montoRestante);
                    $stmtActualizarCuota->bindParam(":id_plan_pago", $idPlanPago, PDO::PARAM_INT);
                    $stmtActualizarCuota->execute();
                    
                    $montoRestante = 0;
                }
            }
            
            // Actualizar cuotas vencidas
            $this->actualizarCuotasVencidas($idCredito);
            
            // Actualizar cuotas con monto restante 0 a pagadas
            $sqlActualizarPagadas = "UPDATE planpagos SET estado = 'pagada' WHERE id_credito = :id_credito AND monto_restante = 0";
            $stmtActualizarPagadas = $this->conexion->prepare($sqlActualizarPagadas);
            $stmtActualizarPagadas->bindParam(":id_credito", $idCredito, PDO::PARAM_INT);
            $stmtActualizarPagadas->execute();
            
            $this->conexion->commit();
            
            return [
                "resultado" => "ok",
                "mensaje" => "Pago registrado correctamente",
                "id_pago" => $idPago
            ];
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("Error al registrar pago: " . $e->getMessage());
            return [
                "resultado" => "error",
                "mensaje" => "Error al registrar pago: " . $e->getMessage()
            ];
        }
    }

    /**
     * Actualiza las cuotas vencidas de un crédito
     * @param int $idCredito ID del crédito
     */
    private function actualizarCuotasVencidas($idCredito) {
        $fechaActual = date('Y-m-d');
        $sql = "UPDATE planpagos SET estado = 'vencida' 
                WHERE id_credito = :id_credito AND estado = 'pendiente' AND fecha_pago < :fecha_actual";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(":id_credito", $idCredito, PDO::PARAM_INT);
        $stmt->bindParam(":fecha_actual", $fechaActual);
        $stmt->execute();
    }

    /**
     * Actualiza el orden de cobranza de los clientes
     * @param int $idRuta ID de la ruta
     * @param array $orden Array con los IDs de clientes en orden
     * @return array Resultado de la operación
     */
    public function actualizarOrdenCobranza($idRuta, $orden) {
        try {
            $this->conexion->beginTransaction();
            
            // Resetear todos los órdenes a 0 para esta ruta
            $sqlReset = "UPDATE clientes SET orden_cobranza = 0 WHERE id_ruta = :id_ruta";
            $stmtReset = $this->conexion->prepare($sqlReset);
            $stmtReset->bindParam(":id_ruta", $idRuta, PDO::PARAM_INT);
            $stmtReset->execute();
            
            // Asignar nuevos órdenes
            foreach ($orden as $index => $idCliente) {
                $ordenReal = $index + 1;
                $sql = "UPDATE clientes SET orden_cobranza = :orden WHERE id_cliente = :id_cliente AND id_ruta = :id_ruta";
                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(":orden", $ordenReal, PDO::PARAM_INT);
                $stmt->bindParam(":id_cliente", $idCliente, PDO::PARAM_INT);
                $stmt->bindParam(":id_ruta", $idRuta, PDO::PARAM_INT);
                $stmt->execute();
            }
            
            $this->conexion->commit();
            
            return [
                "resultado" => "ok",
                "mensaje" => "Orden guardado correctamente"
            ];
        } catch (Exception $e) {
            $this->conexion->rollBack();
            return [
                "resultado" => "error",
                "mensaje" => "Error al guardar orden: " . $e->getMessage()
            ];
        }
    }
}
?>
