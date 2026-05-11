<?php
class Reportes {
    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    /**
     * Obtiene los totales de reportes según los filtros
     */
    public function obtenerTotales($fechaInicio, $fechaFin, $idUsuario = null, $idRuta = null) {
        try {
            $params = [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'id_usuario' => $idUsuario,
                'id_ruta' => $idRuta
            ];

            $totales = [];

            // Créditos cobrados
            $sqlCreditosCobrados = "SELECT COALESCE(SUM(p.monto_pagado), 0) AS total 
                                   FROM pagos p 
                                   JOIN creditos cr ON p.id_credito = cr.id_credito 
                                   WHERE p.fecha_pago BETWEEN :fecha_inicio AND :fecha_fin 
                                   AND (:id_usuario IS NULL OR cr.id_usuario = :id_usuario) 
                                   AND (:id_ruta IS NULL OR cr.id_ruta = :id_ruta)";
            $stmt = $this->conexion->prepare($sqlCreditosCobrados);
            $stmt->execute($params);
            $totales['creditos_cobrados'] = floatval($stmt->fetchColumn() ?? 0);

            // Clientes cobrados
            $sqlClientesCobrados = "SELECT COUNT(DISTINCT cr.id_cliente) AS total 
                                   FROM pagos p 
                                   JOIN creditos cr ON p.id_credito = cr.id_credito 
                                   WHERE p.fecha_pago BETWEEN :fecha_inicio AND :fecha_fin 
                                   AND (:id_usuario IS NULL OR cr.id_usuario = :id_usuario) 
                                   AND (:id_ruta IS NULL OR cr.id_ruta = :id_ruta)";
            $stmt = $this->conexion->prepare($sqlClientesCobrados);
            $stmt->execute($params);
            $totales['clientes_cobrados'] = intval($stmt->fetchColumn() ?? 0);

            // Préstamos realizados
            $sqlPrestamos = "SELECT COALESCE(SUM(monto_credito), 0) AS total 
                           FROM creditos 
                           WHERE fecha_toma_credito BETWEEN :fecha_inicio AND :fecha_fin 
                           AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                           AND (:id_ruta IS NULL OR id_ruta = :id_ruta)";
            $stmt = $this->conexion->prepare($sqlPrestamos);
            $stmt->execute($params);
            $totales['prestamos_realizados'] = floatval($stmt->fetchColumn() ?? 0);

            // Créditos nuevos
            $sqlCreditosNuevos = "SELECT COUNT(*) AS total 
                                 FROM creditos 
                                 WHERE fecha_toma_credito BETWEEN :fecha_inicio AND :fecha_fin 
                                 AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                                 AND (:id_ruta IS NULL OR id_ruta = :id_ruta)";
            $stmt = $this->conexion->prepare($sqlCreditosNuevos);
            $stmt->execute($params);
            $totales['creditos_nuevos'] = intval($stmt->fetchColumn() ?? 0);

            // Clientes nuevos
            $sqlClientesNuevos = "SELECT COUNT(*) AS total 
                                FROM clientes 
                                WHERE fecha_registro BETWEEN :fecha_inicio AND :fecha_fin 
                                AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                                AND (:id_ruta IS NULL OR id_ruta = :id_ruta)";
            $stmt = $this->conexion->prepare($sqlClientesNuevos);
            $stmt->execute($params);
            $totales['clientes_nuevos'] = intval($stmt->fetchColumn() ?? 0);

            // Seguros cobrados
            $sqlSeguros = "SELECT COALESCE(SUM(seguro), 0) AS total 
                          FROM creditos 
                          WHERE fecha_toma_credito BETWEEN :fecha_inicio AND :fecha_fin 
                          AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                          AND (:id_ruta IS NULL OR id_ruta = :id_ruta)";
            $stmt = $this->conexion->prepare($sqlSeguros);
            $stmt->execute($params);
            $totales['seguros_cobrados'] = floatval($stmt->fetchColumn() ?? 0);

            // Gastos por ruta
            $sqlGastos = "SELECT COALESCE(SUM(monto), 0) AS total 
                         FROM gastos 
                         WHERE fecha_gasto BETWEEN :fecha_inicio AND :fecha_fin 
                         AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                         AND (:id_ruta IS NULL OR id_ruta = :id_ruta)";
            $stmt = $this->conexion->prepare($sqlGastos);
            $stmt->execute($params);
            $totales['gastos_ruta'] = floatval($stmt->fetchColumn() ?? 0);

            // Adelantos ingresos
            $sqlAdelantosIngresos = "SELECT COALESCE(SUM(monto), 0) AS total 
                                    FROM adelantos 
                                    WHERE fecha_adelanto BETWEEN :fecha_inicio AND :fecha_fin 
                                    AND tipo = 'ingreso' 
                                    AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                                    AND (:id_ruta IS NULL OR id_ruta = :id_ruta)";
            $stmt = $this->conexion->prepare($sqlAdelantosIngresos);
            $stmt->execute($params);
            $totales['adelantos_ingresos'] = floatval($stmt->fetchColumn() ?? 0);

            // Adelantos egresos
            $sqlAdelantosEgresos = "SELECT COALESCE(SUM(monto), 0) AS total 
                                   FROM adelantos 
                                   WHERE fecha_adelanto BETWEEN :fecha_inicio AND :fecha_fin 
                                   AND tipo = 'egreso' 
                                   AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                                   AND (:id_ruta IS NULL OR id_ruta = :id_ruta)";
            $stmt = $this->conexion->prepare($sqlAdelantosEgresos);
            $stmt->execute($params);
            $totales['adelantos_egresos'] = floatval($stmt->fetchColumn() ?? 0);

            // Descuentos de créditos
            $sqlDescuentos = "SELECT COALESCE(SUM(p.descuento), 0) AS total 
                             FROM pagos p
                             JOIN creditos cr ON p.id_credito = cr.id_credito
                             WHERE p.fecha_pago BETWEEN :fecha_inicio AND :fecha_fin 
                             AND (:id_usuario IS NULL OR p.id_usuario = :id_usuario)
                             AND (:id_ruta IS NULL OR cr.id_ruta = :id_ruta)";
            $stmt = $this->conexion->prepare($sqlDescuentos);
            $stmt->execute($params);
            $totales['descuentos_creditos'] = floatval($stmt->fetchColumn() ?? 0);

            // Créditos cancelados
            $sqlCreditosCancelados = "SELECT COUNT(*) AS total 
                                     FROM creditos 
                                     WHERE saldo_actual = 0 
                                     AND fecha_toma_credito BETWEEN :fecha_inicio AND :fecha_fin 
                                     AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                                     AND (:id_ruta IS NULL OR id_ruta = :id_ruta)";
            $stmt = $this->conexion->prepare($sqlCreditosCancelados);
            $stmt->execute($params);
            $totales['creditos_cancelados'] = intval($stmt->fetchColumn() ?? 0);

            return $totales;
        } catch (PDOException $e) {
            error_log("Error en obtenerTotales: " . $e->getMessage());
            throw new Exception("Error al obtener totales: " . $e->getMessage());
        }
    }

    /**
     * Obtiene la caja anterior (cierre de caja del día anterior)
     */
    public function obtenerCajaAnterior($fecha, $idUsuario = null) {
        try {
            $sql = "SELECT r.cierre_caja, u.nombre_completo 
                   FROM reportes_diarios r
                   LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario
                   WHERE r.fecha = :fecha 
                   AND (:id_usuario IS NULL OR r.id_usuario = :id_usuario)
                   ORDER BY r.id DESC LIMIT 1";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([
                'fecha' => $fecha,
                'id_usuario' => $idUsuario
            ]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'caja_anterior' => floatval($resultado['cierre_caja'] ?? 0),
                'usuario_caja_anterior' => $resultado['nombre_completo'] ?? 'No registrado'
            ];
        } catch (PDOException $e) {
            error_log("Error en obtenerCajaAnterior: " . $e->getMessage());
            throw new Exception("Error al obtener caja anterior: " . $e->getMessage());
        }
    }

    /**
     * Obtiene registros detallados de adelantos
     */
    public function obtenerAdelantos($fechaInicio, $fechaFin, $tipo, $idUsuario = null, $idRuta = null, $limite = 5) {
        try {
            $sql = "SELECT * FROM adelantos 
                   WHERE fecha_adelanto BETWEEN :fecha_inicio AND :fecha_fin 
                   AND tipo = :tipo 
                   AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                   AND (:id_ruta IS NULL OR id_ruta = :id_ruta)
                   ORDER BY fecha_adelanto DESC LIMIT :limite";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindValue(':fecha_inicio', $fechaInicio);
            $stmt->bindValue(':fecha_fin', $fechaFin);
            $stmt->bindValue(':tipo', $tipo);
            $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmt->bindValue(':id_ruta', $idRuta, PDO::PARAM_INT);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en obtenerAdelantos: " . $e->getMessage());
            throw new Exception("Error al obtener adelantos: " . $e->getMessage());
        }
    }

    /**
     * Guarda el cierre de caja
     */
    public function guardarCierreCaja($fecha, $monto, $idUsuario, $observaciones = '') {
        try {
            $sql = "INSERT INTO reportes_diarios (fecha, cierre_caja, id_usuario, observaciones) 
                   VALUES (:fecha, :cierre_caja, :id_usuario, :observaciones)";
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([
                'fecha' => $fecha,
                'cierre_caja' => $monto,
                'id_usuario' => $idUsuario,
                'observaciones' => $observaciones
            ]);
            
            return ['resultado' => 'ok', 'mensaje' => 'Cierre de caja guardado correctamente'];
        } catch (PDOException $e) {
            error_log("Error en guardarCierreCaja: " . $e->getMessage());
            throw new Exception("Error al guardar cierre de caja: " . $e->getMessage());
        }
    }
}
?>
