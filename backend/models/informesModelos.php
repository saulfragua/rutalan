<?php
/**
 * Modelo de Informes
 * Consultas por rango de fechas: pagos, créditos y gastos con datos de cliente y usuario que registró
 */
class Informes {
    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    /**
     * Pagos realizados en el rango de fechas (con datos del cliente y usuario que registró el pago)
     * @param string $fechaDesde Fecha inicio (Y-m-d)
     * @param string $fechaHasta Fecha fin (Y-m-d)
     * @return array
     */
    public function obtenerPagosPorRango($fechaDesde, $fechaHasta) {
        $sql = "SELECT 
                    p.id_pago,
                    p.fecha_pago,
                    p.hora_pago,
                    p.monto_pagado,
                    p.descuento,
                    p.id_credito,
                    c.id_cliente,
                    c.documento AS documento_cliente,
                    c.nombres AS nombres_cliente,
                    c.apellidos AS apellidos_cliente,
                    CONCAT(c.nombres, ' ', c.apellidos) AS nombre_completo_cliente,
                    c.direccion AS direccion_cliente,
                    c.telefono AS telefono_cliente,
                    c.telefono2 AS telefono2_cliente,
                    u.id_usuario AS id_usuario_registro,
                    u.nombre_completo AS usuario_registro,
                    r.nombre_ruta
                FROM pagos p
                INNER JOIN clientes c ON p.id_cliente = c.id_cliente
                INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
                LEFT JOIN rutas r ON p.id_ruta = r.id_ruta
                WHERE p.fecha_pago BETWEEN :fecha_desde AND :fecha_hasta
                ORDER BY p.fecha_pago DESC, p.hora_pago DESC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(":fecha_desde", $fechaDesde);
        $stmt->bindParam(":fecha_hasta", $fechaHasta);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Créditos realizados en el rango de fechas (con datos del cliente y usuario que creó el crédito)
     * @param string $fechaDesde Fecha inicio (Y-m-d)
     * @param string $fechaHasta Fecha fin (Y-m-d)
     * @return array
     */
    public function obtenerCreditosPorRango($fechaDesde, $fechaHasta) {
        $sql = "SELECT 
                    cr.id_credito,
                    cr.fecha_toma_credito,
                    cr.hora_toma_credito,
                    cr.monto_credito,
                    cr.cuotas,
                    cr.tasa_interes,
                    cr.frecuencia_pago,
                    cr.seguro,
                    cr.saldo_actual,
                    cr.tipo_credito,
                    c.id_cliente,
                    c.documento AS documento_cliente,
                    c.nombres AS nombres_cliente,
                    c.apellidos AS apellidos_cliente,
                    CONCAT(c.nombres, ' ', c.apellidos) AS nombre_completo_cliente,
                    c.direccion AS direccion_cliente,
                    c.telefono AS telefono_cliente,
                    c.telefono2 AS telefono2_cliente,
                    u.id_usuario AS id_usuario_registro,
                    u.nombre_completo AS usuario_registro,
                    r.nombre_ruta
                FROM creditos cr
                INNER JOIN clientes c ON cr.id_cliente = c.id_cliente
                LEFT JOIN usuarios u ON cr.id_usuario = u.id_usuario
                LEFT JOIN rutas r ON cr.id_ruta = r.id_ruta
                WHERE cr.fecha_toma_credito BETWEEN :fecha_desde AND :fecha_hasta
                ORDER BY cr.fecha_toma_credito DESC, cr.hora_toma_credito DESC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(":fecha_desde", $fechaDesde);
        $stmt->bindParam(":fecha_hasta", $fechaHasta);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Gastos realizados en el rango de fechas (con usuario que registró y ruta)
     * @param string $fechaDesde Fecha inicio (Y-m-d)
     * @param string $fechaHasta Fecha fin (Y-m-d)
     * @return array
     */
    public function obtenerGastosPorRango($fechaDesde, $fechaHasta) {
        $sql = "SELECT 
                    g.id_gasto,
                    g.descripcion,
                    g.monto,
                    g.fecha_gasto,
                    g.hora_gasto,
                    u.id_usuario AS id_usuario_registro,
                    u.nombre_completo AS usuario_registro,
                    r.nombre_ruta
                FROM gastos g
                INNER JOIN usuarios u ON g.id_usuario = u.id_usuario
                LEFT JOIN rutas r ON g.id_ruta = r.id_ruta
                WHERE g.fecha_gasto BETWEEN :fecha_desde AND :fecha_hasta
                ORDER BY g.fecha_gasto DESC, g.hora_gasto DESC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(":fecha_desde", $fechaDesde);
        $stmt->bindParam(":fecha_hasta", $fechaHasta);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
