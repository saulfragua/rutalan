<?php
class Dashboard {
    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    /**
     * Obtiene el valor total de créditos por ruta (suma de saldos según ruta del cliente)
     */
    public function obtenerCreditosPorRuta() {
        try {
            $sql = "SELECT 
                        r.id_ruta,
                        r.nombre_ruta,
                        COALESCE(SUM(cr.saldo_actual), 0) AS total_credito
                    FROM rutas r
                    LEFT JOIN clientes c ON r.id_ruta = c.id_ruta
                    LEFT JOIN creditos cr ON c.id_cliente = cr.id_cliente AND cr.activo = 1
                    WHERE r.activo = 1
                    GROUP BY r.id_ruta, r.nombre_ruta
                    ORDER BY r.nombre_ruta";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener créditos por ruta: " . $e->getMessage());
            throw new Exception("Error al obtener créditos por ruta: " . $e->getMessage());
        }
    }

    /**
     * Obtiene el total general de créditos (suma de todas las rutas)
     */
    public function obtenerTotalGeneralCreditos() {
        try {
            $sql = "SELECT COALESCE(SUM(cr.saldo_actual), 0) AS total_general
                    FROM creditos cr
                    WHERE cr.activo = 1";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return floatval($resultado['total_general'] ?? 0);
        } catch (PDOException $e) {
            error_log("Error al obtener total general de créditos: " . $e->getMessage());
            throw new Exception("Error al obtener total general de créditos: " . $e->getMessage());
        }
    }

    /**
     * Obtiene el total de clientes creados vs clientes con crédito
     */
    public function obtenerEstadisticasClientes() {
        try {
            $sql = "SELECT 
                        COUNT(DISTINCT c.id_cliente) AS total_clientes,
                        COUNT(DISTINCT CASE WHEN cr.activo = 1 THEN c.id_cliente END) AS clientes_con_credito
                    FROM clientes c
                    LEFT JOIN creditos cr ON c.id_cliente = cr.id_cliente
                    WHERE c.activo = 1";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener estadísticas de clientes: " . $e->getMessage());
            throw new Exception("Error al obtener estadísticas de clientes: " . $e->getMessage());
        }
    }

    /**
     * Obtiene estadísticas de clientes por ruta (total, con saldo, cobrado en el día)
     */
    public function obtenerClientesPorRuta($fechaInicio = null, $fechaFin = null) {
        try {
            $fechaHoy = date('Y-m-d');
            
            // Si no se proporcionan fechas, usar el día actual
            if (!$fechaInicio) {
                $fechaInicio = $fechaHoy;
            }
            if (!$fechaFin) {
                $fechaFin = $fechaHoy;
            }

            $sql = "SELECT 
                        r.id_ruta,
                        r.nombre_ruta,
                        COUNT(DISTINCT c.id_cliente) AS total_clientes,
                        COUNT(DISTINCT CASE WHEN cr.activo = 1 AND cr.saldo_actual > 0 THEN c.id_cliente END) AS clientes_con_saldo,
                        COALESCE(SUM(CASE WHEN p.fecha_pago BETWEEN :fecha_inicio AND :fecha_fin THEN p.monto_pagado ELSE 0 END), 0) AS cobrado_en_dia
                    FROM rutas r
                    LEFT JOIN clientes c ON r.id_ruta = c.id_ruta AND c.activo = 1
                    LEFT JOIN creditos cr ON c.id_cliente = cr.id_cliente
                    LEFT JOIN pagos p ON cr.id_credito = p.id_credito
                    WHERE r.activo = 1
                    GROUP BY r.id_ruta, r.nombre_ruta
                    ORDER BY r.nombre_ruta";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":fecha_inicio", $fechaInicio, PDO::PARAM_STR);
            $stmt->bindParam(":fecha_fin", $fechaFin, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener clientes por ruta: " . $e->getMessage());
            throw new Exception("Error al obtener clientes por ruta: " . $e->getMessage());
        }
    }

    /**
     * Obtiene el total cobrado en el día (suma de todos los cobros del día)
     */
    public function obtenerTotalCobradoEnDia($fechaInicio = null, $fechaFin = null) {
        try {
            $fechaHoy = date('Y-m-d');
            
            // Si no se proporcionan fechas, usar el día actual
            if (!$fechaInicio) {
                $fechaInicio = $fechaHoy;
            }
            if (!$fechaFin) {
                $fechaFin = $fechaHoy;
            }

            $sql = "SELECT COALESCE(SUM(monto_pagado), 0) AS total_cobrado
                    FROM pagos
                    WHERE fecha_pago BETWEEN :fecha_inicio AND :fecha_fin";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":fecha_inicio", $fechaInicio, PDO::PARAM_STR);
            $stmt->bindParam(":fecha_fin", $fechaFin, PDO::PARAM_STR);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return floatval($resultado['total_cobrado'] ?? 0);
        } catch (PDOException $e) {
            error_log("Error al obtener total cobrado en el día: " . $e->getMessage());
            throw new Exception("Error al obtener total cobrado en el día: " . $e->getMessage());
        }
    }

    /**
     * Obtiene gastos totales por ruta con filtro de fechas
     */
    public function obtenerGastosPorRuta($fechaInicio = null, $fechaFin = null) {
        try {
            $sql = "SELECT 
                        r.id_ruta,
                        r.nombre_ruta,
                        COALESCE(SUM(g.monto), 0) AS total_gastos
                    FROM rutas r
                    LEFT JOIN gastos g ON r.id_ruta = g.id_ruta";
            
            $params = [];
            $whereConditions = [];
            
            // Agregar filtro de fechas si se proporcionan
            if ($fechaInicio && $fechaFin) {
                $whereConditions[] = "(g.fecha_gasto IS NULL OR g.fecha_gasto BETWEEN :fecha_inicio AND :fecha_fin)";
                $params[':fecha_inicio'] = $fechaInicio;
                $params[':fecha_fin'] = $fechaFin;
            } elseif ($fechaInicio) {
                $whereConditions[] = "(g.fecha_gasto IS NULL OR g.fecha_gasto >= :fecha_inicio)";
                $params[':fecha_inicio'] = $fechaInicio;
            } elseif ($fechaFin) {
                $whereConditions[] = "(g.fecha_gasto IS NULL OR g.fecha_gasto <= :fecha_fin)";
                $params[':fecha_fin'] = $fechaFin;
            }
            
            $sql .= " WHERE r.activo = 1";
            
            if (!empty($whereConditions)) {
                $sql .= " AND " . implode(" AND ", $whereConditions);
            }
            
            $sql .= " GROUP BY r.id_ruta, r.nombre_ruta
                      ORDER BY r.nombre_ruta";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener gastos por ruta: " . $e->getMessage());
            throw new Exception("Error al obtener gastos por ruta: " . $e->getMessage());
        }
    }

    /**
     * Obtiene estadísticas de créditos por tipo
     */
    public function obtenerCreditosPorTipo() {
        try {
            $sql = "SELECT 
                        COALESCE(tipo_credito, 'comun') AS tipo_credito,
                        COUNT(*) AS cantidad,
                        COALESCE(SUM(saldo_actual), 0) AS total_saldo
                    FROM creditos
                    WHERE activo = 1
                    GROUP BY tipo_credito
                    ORDER BY tipo_credito";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener créditos por tipo: " . $e->getMessage());
            throw new Exception("Error al obtener créditos por tipo: " . $e->getMessage());
        }
    }

    /**
     * Obtiene estadísticas de seguros recaudados
     * El seguro está en la tabla creditos, se suma el seguro de los créditos únicos que tienen pagos en el período
     */
    public function obtenerEstadisticasSeguros($fechaInicio = null, $fechaFin = null) {
        try {
            $fechaHoy = date('Y-m-d');
            
            if (!$fechaInicio) {
                $fechaInicio = $fechaHoy;
            }
            if (!$fechaFin) {
                $fechaFin = $fechaHoy;
            }

            // Sumar el seguro de los créditos únicos que tienen pagos en el período
            // Agrupamos por crédito para evitar contar el mismo seguro múltiples veces
            $sql = "SELECT 
                        COALESCE(SUM(cr.seguro), 0) AS total_seguros,
                        COALESCE(SUM(cr.seguro) * 0.30, 0) AS comision_cobrador,
                        COALESCE(SUM(cr.seguro) * 0.70, 0) AS total_entregar
                    FROM (
                        SELECT DISTINCT p.id_credito
                        FROM pagos p
                        WHERE DATE(p.fecha_pago) BETWEEN :fecha_inicio AND :fecha_fin
                    ) AS creditos_pagados
                    INNER JOIN creditos cr ON creditos_pagados.id_credito = cr.id_credito
                    WHERE cr.activo = 1";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":fecha_inicio", $fechaInicio, PDO::PARAM_STR);
            $stmt->bindParam(":fecha_fin", $fechaFin, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener estadísticas de seguros: " . $e->getMessage());
            throw new Exception("Error al obtener estadísticas de seguros: " . $e->getMessage());
        }
    }

    /**
     * Obtiene estadísticas de cajas
     */
    public function obtenerEstadisticasCajas() {
        try {
            $sql = "SELECT 
                        COUNT(CASE WHEN estado = 'ABIERTA' THEN 1 END) AS cajas_abiertas,
                        COUNT(CASE WHEN estado = 'CERRADA' THEN 1 END) AS cajas_cerradas,
                        COUNT(*) AS total_cajas,
                        COALESCE(SUM(CASE WHEN estado = 'ABIERTA' THEN saldo_inicial ELSE 0 END), 0) AS total_saldo_inicial,
                        COALESCE(SUM(CASE WHEN estado = 'CERRADA' THEN saldo_final ELSE 0 END), 0) AS total_saldo_final
                    FROM cajas";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener estadísticas de cajas: " . $e->getMessage());
            throw new Exception("Error al obtener estadísticas de cajas: " . $e->getMessage());
        }
    }

    /**
     * Obtiene estadísticas de cuotas (pagadas, pendientes, vencidas)
     */
    public function obtenerEstadisticasCuotas() {
        try {
            $sql = "SELECT 
                        COUNT(CASE WHEN estado = 'pagada' THEN 1 END) AS cuotas_pagadas,
                        COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) AS cuotas_pendientes,
                        COUNT(CASE WHEN estado = 'vencida' THEN 1 END) AS cuotas_vencidas,
                        COUNT(*) AS total_cuotas,
                        COALESCE(SUM(CASE WHEN estado = 'pagada' THEN monto_cuota ELSE 0 END), 0) AS total_pagado,
                        COALESCE(SUM(CASE WHEN estado IN ('pendiente', 'vencida') THEN monto_restante ELSE 0 END), 0) AS total_pendiente
                    FROM planpagos";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener estadísticas de cuotas: " . $e->getMessage());
            throw new Exception("Error al obtener estadísticas de cuotas: " . $e->getMessage());
        }
    }

    /**
     * Obtiene evolución de pagos por día en un rango de fechas
     * El seguro se obtiene de la tabla creditos, sumando el seguro de créditos únicos por día
     */
    public function obtenerEvolucionPagos($fechaInicio = null, $fechaFin = null) {
        try {
            $fechaHoy = date('Y-m-d');
            
            if (!$fechaInicio) {
                // Por defecto, últimos 30 días
                $fechaInicio = date('Y-m-d', strtotime('-30 days'));
            }
            if (!$fechaFin) {
                $fechaFin = $fechaHoy;
            }

            // Para obtener los seguros, agrupamos por fecha y crédito para evitar duplicados
            $sql = "SELECT 
                        fecha,
                        SUM(cantidad_pagos) AS cantidad_pagos,
                        SUM(total_cobrado) AS total_cobrado,
                        SUM(total_seguros) AS total_seguros
                    FROM (
                        SELECT 
                            DATE(p.fecha_pago) AS fecha,
                            COUNT(*) AS cantidad_pagos,
                            SUM(p.monto_pagado) AS total_cobrado,
                            MAX(cr.seguro) AS total_seguros
                        FROM pagos p
                        LEFT JOIN creditos cr ON p.id_credito = cr.id_credito
                        WHERE DATE(p.fecha_pago) BETWEEN :fecha_inicio AND :fecha_fin
                        GROUP BY DATE(p.fecha_pago), p.id_credito
                    ) AS pagos_por_credito
                    GROUP BY fecha
                    ORDER BY fecha ASC";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":fecha_inicio", $fechaInicio, PDO::PARAM_STR);
            $stmt->bindParam(":fecha_fin", $fechaFin, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener evolución de pagos: " . $e->getMessage());
            throw new Exception("Error al obtener evolución de pagos: " . $e->getMessage());
        }
    }

    /**
     * Obtiene estadísticas de refinanciaciones
     */
    public function obtenerEstadisticasRefinanciaciones($fechaInicio = null, $fechaFin = null) {
        try {
            $fechaHoy = date('Y-m-d');
            
            if (!$fechaInicio) {
                $fechaInicio = $fechaHoy;
            }
            if (!$fechaFin) {
                $fechaFin = $fechaHoy;
            }

            $sql = "SELECT 
                        COUNT(CASE WHEN tipo_credito = 'refinanciado' THEN 1 END) AS refinanciados_manuales,
                        COUNT(CASE WHEN tipo_credito = 'refinanciado_por_sistema' THEN 1 END) AS refinanciados_sistema,
                        COUNT(CASE WHEN tipo_credito IN ('refinanciado', 'refinanciado_por_sistema') THEN 1 END) AS total_refinanciados,
                        COALESCE(SUM(CASE WHEN tipo_credito IN ('refinanciado', 'refinanciado_por_sistema') THEN saldo_actual ELSE 0 END), 0) AS total_saldo_refinanciado
                    FROM creditos
                    WHERE activo = 1
                    AND fecha_toma_credito BETWEEN :fecha_inicio AND :fecha_fin";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":fecha_inicio", $fechaInicio, PDO::PARAM_STR);
            $stmt->bindParam(":fecha_fin", $fechaFin, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener estadísticas de refinanciaciones: " . $e->getMessage());
            throw new Exception("Error al obtener estadísticas de refinanciaciones: " . $e->getMessage());
        }
    }

    /**
     * Obtiene top rutas por rendimiento (mayor cobrado)
     */
    public function obtenerTopRutasPorRendimiento($fechaInicio = null, $fechaFin = null, $limite = 5) {
        try {
            $fechaHoy = date('Y-m-d');
            
            if (!$fechaInicio) {
                $fechaInicio = $fechaHoy;
            }
            if (!$fechaFin) {
                $fechaFin = $fechaHoy;
            }

            $sql = "SELECT 
                        r.id_ruta,
                        r.nombre_ruta,
                        COALESCE(SUM(p.monto_pagado), 0) AS total_cobrado,
                        COUNT(DISTINCT p.id_pago) AS cantidad_pagos,
                        COUNT(DISTINCT c.id_cliente) AS clientes_atendidos
                    FROM rutas r
                    LEFT JOIN clientes c ON r.id_ruta = c.id_ruta AND c.activo = 1
                    LEFT JOIN creditos cr ON c.id_cliente = cr.id_cliente AND cr.activo = 1
                    LEFT JOIN pagos p ON cr.id_credito = p.id_credito 
                        AND DATE(p.fecha_pago) BETWEEN :fecha_inicio AND :fecha_fin
                    WHERE r.activo = 1
                    GROUP BY r.id_ruta, r.nombre_ruta
                    HAVING total_cobrado > 0
                    ORDER BY total_cobrado DESC
                    LIMIT :limite";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":fecha_inicio", $fechaInicio, PDO::PARAM_STR);
            $stmt->bindParam(":fecha_fin", $fechaFin, PDO::PARAM_STR);
            $stmt->bindParam(":limite", $limite, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener top rutas: " . $e->getMessage());
            throw new Exception("Error al obtener top rutas: " . $e->getMessage());
        }
    }

    /**
     * Obtiene estadísticas de morosidad (créditos vencidos)
     */
    public function obtenerEstadisticasMorosidad() {
        try {
            $sql = "SELECT 
                        COUNT(CASE WHEN cr.fecha_finaliza_credito < CURDATE() AND cr.saldo_actual > 0 THEN 1 END) AS creditos_vencidos,
                        COUNT(CASE WHEN cr.fecha_finaliza_credito < DATE_SUB(CURDATE(), INTERVAL 5 DAY) AND cr.saldo_actual > 0 THEN 1 END) AS creditos_vencidos_5_dias,
                        COALESCE(SUM(CASE WHEN cr.fecha_finaliza_credito < CURDATE() AND cr.saldo_actual > 0 THEN cr.saldo_actual ELSE 0 END), 0) AS total_morosidad,
                        COUNT(CASE WHEN pp.estado = 'vencida' THEN 1 END) AS cuotas_vencidas
                    FROM creditos cr
                    LEFT JOIN planpagos pp ON cr.id_credito = pp.id_credito
                    WHERE cr.activo = 1";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener estadísticas de morosidad: " . $e->getMessage());
            throw new Exception("Error al obtener estadísticas de morosidad: " . $e->getMessage());
        }
    }
}
?>
