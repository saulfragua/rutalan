<?php 
/**
 * Modelo de Cajas
 * Maneja todas las operaciones relacionadas con cajas en la base de datos
 */
class Cajas {
    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    /**
     * Verifica si un usuario tiene una caja ABIERTA
     * @param int $idUsuario ID del usuario
     * @return array|null Datos de la caja abierta o null si no existe
     */
    public function obtenerCajaAbierta($idUsuario) {
        $sql = "SELECT * FROM cajas 
                WHERE id_usuario = :id_usuario 
                AND estado = 'ABIERTA'
                ORDER BY fecha_apertura DESC
                LIMIT 1";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(":id_usuario", $idUsuario, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica si un usuario tiene una caja ABIERTA (retorna boolean)
     * @param int $idUsuario ID del usuario
     * @return bool True si tiene caja abierta, False si no
     */
    public function tieneCajaAbierta($idUsuario) {
        $caja = $this->obtenerCajaAbierta($idUsuario);
        return $caja !== false && !empty($caja);
    }

    /**
     * Verifica si una ruta tiene una caja ABIERTA
     * @param int $idRuta ID de la ruta
     * @return array|null Datos de la caja abierta o null si no existe
     */
    public function obtenerCajaAbiertaPorRuta($idRuta) {
        $sql = "SELECT c.* 
                FROM cajas c
                INNER JOIN caja_ruta cr ON c.id_caja = cr.id_caja
                WHERE cr.id_ruta = :id_ruta 
                AND c.estado = 'ABIERTA'
                ORDER BY c.fecha_apertura DESC
                LIMIT 1";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(":id_ruta", $idRuta, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica si una ruta tiene una caja ABIERTA (retorna boolean)
     * @param int $idRuta ID de la ruta
     * @return bool True si tiene caja abierta, False si no
     */
    public function rutaTieneCajaAbierta($idRuta) {
        $caja = $this->obtenerCajaAbiertaPorRuta($idRuta);
        return $caja !== false && !empty($caja);
    }

    /**
     * Abre una nueva caja
     * @param array $params Parámetros de la caja (id_usuario, id_rutas (array), saldo_inicial, nombre_caja)
     * @return array Resultado de la operación
     */
    public function abrirCaja($params) {
        try {
            $this->conexion->beginTransaction();

            // Validar que no tenga caja abierta
            if ($this->tieneCajaAbierta($params['id_usuario'])) {
                return [
                    "resultado" => "error",
                    "mensaje" => "Ya existe una caja abierta para este usuario"
                ];
            }

            // Validar campos obligatorios
            if (empty($params['id_usuario']) || !isset($params['saldo_inicial'])) {
                return [
                    "resultado" => "error",
                    "mensaje" => "Faltan campos obligatorios"
                ];
            }

            // Rutas son opcionales - permitir caja general sin rutas
            $idRutas = $params['id_rutas'] ?? [];
            if (!is_array($idRutas)) {
                $idRutas = [];
            }

            // Validar que ninguna de las rutas tenga una caja abierta (solo si se proporcionan rutas)
            if (!empty($idRutas)) {
                foreach ($idRutas as $idRuta) {
                    if ($this->rutaTieneCajaAbierta($idRuta)) {
                        $this->conexion->rollBack();
                        return [
                            "resultado" => "error",
                            "mensaje" => "La ruta seleccionada ya tiene una caja abierta"
                        ];
                    }
                }
            }

            $saldoInicial = floatval($params['saldo_inicial']);
            if ($saldoInicial < 0) {
                return [
                    "resultado" => "error",
                    "mensaje" => "El saldo inicial no puede ser negativo"
                ];
            }

            $nombreCaja = $params['nombre_caja'] ?? 'Caja ' . date('Y-m-d');
            $fechaApertura = date('Y-m-d H:i:s');

            // Insertar la caja sin id_ruta (será null)
            $sql = "INSERT INTO cajas (id_usuario, id_ruta, nombre_caja, saldo_inicial, fecha_apertura, estado) 
                    VALUES (:id_usuario, NULL, :nombre_caja, :saldo_inicial, :fecha_apertura, 'ABIERTA')";
            $stmt = $this->conexion->prepare($sql);
            
            $stmt->bindParam(":id_usuario", $params['id_usuario'], PDO::PARAM_INT);
            $stmt->bindParam(":nombre_caja", $nombreCaja, PDO::PARAM_STR);
            $stmt->bindParam(":saldo_inicial", $saldoInicial, PDO::PARAM_STR);
            $stmt->bindParam(":fecha_apertura", $fechaApertura, PDO::PARAM_STR);
            
            $stmt->execute();
            
            $idCaja = $this->conexion->lastInsertId();

            // Insertar las relaciones en caja_ruta solo si hay rutas seleccionadas
            if (!empty($idRutas)) {
                $sqlRuta = "INSERT INTO caja_ruta (id_caja, id_ruta) VALUES (:id_caja, :id_ruta)";
                $stmtRuta = $this->conexion->prepare($sqlRuta);
                
                foreach ($idRutas as $idRuta) {
                    $stmtRuta->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
                    $stmtRuta->bindParam(":id_ruta", $idRuta, PDO::PARAM_INT);
                    $stmtRuta->execute();
                }
            }

            $this->conexion->commit();

            return [
                "resultado" => "ok",
                "mensaje" => "Caja abierta correctamente",
                "id_caja" => $idCaja
            ];
        } catch (PDOException $e) {
            $this->conexion->rollBack();
            return [
                "resultado" => "error",
                "mensaje" => "Error al abrir caja: " . $e->getMessage()
            ];
        }
    }

    /**
     * Cierra una caja
     * @param int $idCaja ID de la caja
     * @param float $saldoFinal Saldo final de la caja
     * @param string $observaciones Observaciones del cierre
     * @return array Resultado de la operación
     */
    public function cerrarCaja($idCaja, $saldoFinal = null, $observaciones = null) {
        try {
            $fechaCierre = date('Y-m-d H:i:s');
            
            // Verificar si la columna observaciones existe
            $sqlCheck = "SHOW COLUMNS FROM cajas LIKE 'observaciones'";
            $stmtCheck = $this->conexion->prepare($sqlCheck);
            $stmtCheck->execute();
            $tieneObservaciones = $stmtCheck->rowCount() > 0;
            
            if ($tieneObservaciones) {
                $sql = "UPDATE cajas 
                        SET estado = 'CERRADA', 
                            fecha_cierre = :fecha_cierre,
                            saldo_final = :saldo_final,
                            observaciones = :observaciones
                        WHERE id_caja = :id_caja 
                        AND estado = 'ABIERTA'";
                
                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
                $stmt->bindParam(":fecha_cierre", $fechaCierre, PDO::PARAM_STR);
                $stmt->bindParam(":saldo_final", $saldoFinal, PDO::PARAM_STR);
                $stmt->bindParam(":observaciones", $observaciones, PDO::PARAM_STR);
            } else {
                $sql = "UPDATE cajas 
                        SET estado = 'CERRADA', 
                            fecha_cierre = :fecha_cierre,
                            saldo_final = :saldo_final
                        WHERE id_caja = :id_caja 
                        AND estado = 'ABIERTA'";
                
                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
                $stmt->bindParam(":fecha_cierre", $fechaCierre, PDO::PARAM_STR);
                $stmt->bindParam(":saldo_final", $saldoFinal, PDO::PARAM_STR);
            }
            
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return [
                    "resultado" => "ok",
                    "mensaje" => "Caja cerrada correctamente"
                ];
            } else {
                return [
                    "resultado" => "error",
                    "mensaje" => "No se pudo cerrar la caja. Verifique que esté abierta."
                ];
            }
        } catch (PDOException $e) {
            return [
                "resultado" => "error",
                "mensaje" => "Error al cerrar caja: " . $e->getMessage()
            ];
        }
    }

    /**
     * Consulta todas las cajas de un usuario
     * @param int $idUsuario ID del usuario
     * @return array Lista de cajas
     */
    public function consultarPorUsuario($idUsuario) {
        $sql = "SELECT * FROM cajas 
                WHERE id_usuario = :id_usuario 
                ORDER BY fecha_apertura DESC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(":id_usuario", $idUsuario, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el ID de la última caja insertada
     * @return int ID de la última caja
     */
    public function obtenerUltimoId() {
        return $this->conexion->lastInsertId();
    }

    /**
     * Consulta todas las cajas abiertas con resumen de operaciones
     * @return array Lista de cajas abiertas con información agregada
     */
    public function consultarCajasAbiertasConResumen() {
        try {
            $sql = "SELECT 
                        c.id_caja,
                        c.id_usuario,
                        c.nombre_caja,
                        c.saldo_inicial,
                        c.fecha_apertura,
                        c.observaciones,
                        u.nombre_usuario,
                        u.nombre_completo AS nombres_usuario,
                        (SELECT GROUP_CONCAT(r.nombre_ruta SEPARATOR ', ') 
                         FROM caja_ruta cr 
                         INNER JOIN rutas r ON cr.id_ruta = r.id_ruta 
                         WHERE cr.id_caja = c.id_caja 
                         LIMIT 1) AS nombre_ruta
                    FROM cajas c
                    LEFT JOIN usuarios u ON c.id_usuario = u.id_usuario
                    WHERE c.estado = 'ABIERTA'
                    ORDER BY c.fecha_apertura DESC";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular totales para cada caja
            foreach ($resultados as &$caja) {
                $idCaja = $caja['id_caja'];
                $idUsuario = $caja['id_usuario'];
                $fechaApertura = !empty($caja['fecha_apertura']) ? date('Y-m-d', strtotime($caja['fecha_apertura'])) : date('Y-m-d');
                
                // Total de cobros y descuentos en esta caja
                try {
                    $sqlCobros = "SELECT 
                                    COALESCE(SUM(monto_pagado), 0) AS total_cobros,
                                    COALESCE(SUM(descuento), 0) AS total_descuentos 
                                 FROM pagos 
                                 WHERE id_caja = :id_caja";
                    $stmtCobros = $this->conexion->prepare($sqlCobros);
                    $stmtCobros->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
                    $stmtCobros->execute();
                    $resultadoCobros = $stmtCobros->fetch(PDO::FETCH_ASSOC);
                    $caja['total_cobros'] = floatval($resultadoCobros['total_cobros'] ?? 0);
                    $caja['total_descuentos'] = floatval($resultadoCobros['total_descuentos'] ?? 0);
                } catch (PDOException $e) {
                    error_log("Error al calcular cobros para caja $idCaja: " . $e->getMessage());
                    $caja['total_cobros'] = 0;
                    $caja['total_descuentos'] = 0;
                }
                
                // Total de gastos en esta caja
                try {
                    $sqlGastos = "SELECT COALESCE(SUM(monto), 0) AS total 
                                 FROM gastos 
                                 WHERE id_caja = :id_caja";
                    $stmtGastos = $this->conexion->prepare($sqlGastos);
                    $stmtGastos->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
                    $stmtGastos->execute();
                    $resultadoGastos = $stmtGastos->fetch(PDO::FETCH_ASSOC);
                    $caja['total_gastos'] = floatval($resultadoGastos['total'] ?? 0);
                } catch (PDOException $e) {
                    error_log("Error al calcular gastos para caja $idCaja: " . $e->getMessage());
                    $caja['total_gastos'] = 0;
                }
                
                // Total de entradas de dinero en esta caja (desde la fecha de apertura)
                try {
                    $fechaAperturaFormato = date('Y-m-d H:i:s', strtotime($caja['fecha_apertura']));
                    $sqlEntradas = "SELECT COALESCE(SUM(monto), 0) AS total 
                                   FROM movimientos_caja 
                                   WHERE id_caja = :id_caja 
                                   AND tipo = 'entrada'
                                   AND fecha_movimiento >= :fecha_apertura";
                    $stmtEntradas = $this->conexion->prepare($sqlEntradas);
                    $stmtEntradas->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
                    $stmtEntradas->bindParam(":fecha_apertura", $fechaAperturaFormato, PDO::PARAM_STR);
                    $stmtEntradas->execute();
                    $resultadoEntradas = $stmtEntradas->fetch(PDO::FETCH_ASSOC);
                    $caja['total_entradas'] = floatval($resultadoEntradas['total'] ?? 0);
                } catch (PDOException $e) {
                    error_log("Error al calcular entradas para caja $idCaja: " . $e->getMessage());
                    $caja['total_entradas'] = 0;
                }
                
                // Total de salidas de dinero en esta caja (desde la fecha de apertura)
                try {
                    $fechaAperturaFormato = date('Y-m-d H:i:s', strtotime($caja['fecha_apertura']));
                    $sqlSalidas = "SELECT COALESCE(SUM(monto), 0) AS total 
                                  FROM movimientos_caja 
                                  WHERE id_caja = :id_caja 
                                  AND tipo = 'salida'
                                  AND fecha_movimiento >= :fecha_apertura";
                    $stmtSalidas = $this->conexion->prepare($sqlSalidas);
                    $stmtSalidas->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
                    $stmtSalidas->bindParam(":fecha_apertura", $fechaAperturaFormato, PDO::PARAM_STR);
                    $stmtSalidas->execute();
                    $resultadoSalidas = $stmtSalidas->fetch(PDO::FETCH_ASSOC);
                    $caja['total_salidas'] = floatval($resultadoSalidas['total'] ?? 0);
                } catch (PDOException $e) {
                    error_log("Error al calcular salidas para caja $idCaja: " . $e->getMessage());
                    $caja['total_salidas'] = 0;
                }
                
                // Contar créditos y calcular monto total de créditos realizados por el usuario en esta caja específica
                // Contar créditos asociados directamente a esta caja usando id_caja
                try {
                    $sqlCreditos = "SELECT COUNT(*) AS total, 
                                    COALESCE(SUM(monto_credito), 0) AS total_monto,
                                    COALESCE(SUM(seguro), 0) AS total_seguros 
                                   FROM creditos 
                                   WHERE id_caja = :id_caja
                                   AND activo = 1";
                    $stmtCreditos = $this->conexion->prepare($sqlCreditos);
                    $stmtCreditos->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
                    $stmtCreditos->execute();
                    $resultadoCreditos = $stmtCreditos->fetch(PDO::FETCH_ASSOC);
                    $caja['total_creditos'] = intval($resultadoCreditos['total'] ?? 0);
                    $caja['total_monto_creditos'] = floatval($resultadoCreditos['total_monto'] ?? 0);
                    $caja['total_seguros'] = floatval($resultadoCreditos['total_seguros'] ?? 0);
                } catch (PDOException $e) {
                    error_log("Error al calcular créditos para caja $idCaja: " . $e->getMessage());
                    $caja['total_creditos'] = 0;
                    $caja['total_monto_creditos'] = 0;
                    $caja['total_seguros'] = 0;
                }
                
                // Obtener todas las rutas asignadas a esta caja
                try {
                    $sqlRutasCaja = "SELECT GROUP_CONCAT(r.nombre_ruta SEPARATOR ', ') AS rutas_asignadas
                                    FROM caja_ruta cr
                                    INNER JOIN rutas r ON cr.id_ruta = r.id_ruta
                                    WHERE cr.id_caja = :id_caja";
                    $stmtRutasCaja = $this->conexion->prepare($sqlRutasCaja);
                    $stmtRutasCaja->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
                    $stmtRutasCaja->execute();
                    $resultadoRutasCaja = $stmtRutasCaja->fetch(PDO::FETCH_ASSOC);
                    $caja['rutas_asignadas'] = $resultadoRutasCaja['rutas_asignadas'] ?? null;
                } catch (PDOException $e) {
                    error_log("Error al obtener rutas asignadas para caja $idCaja: " . $e->getMessage());
                    $caja['rutas_asignadas'] = null;
                }
                
                // Rutas cobradas (lista separada por comas)
                try {
                    $sqlRutas = "SELECT GROUP_CONCAT(DISTINCT r2.nombre_ruta SEPARATOR ', ') AS rutas
                                FROM pagos p
                                LEFT JOIN rutas r2 ON p.id_ruta = r2.id_ruta
                                WHERE p.id_caja = :id_caja
                                AND r2.nombre_ruta IS NOT NULL";
                    $stmtRutas = $this->conexion->prepare($sqlRutas);
                    $stmtRutas->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
                    $stmtRutas->execute();
                    $resultadoRutas = $stmtRutas->fetch(PDO::FETCH_ASSOC);
                    $caja['rutas_cobradas'] = $resultadoRutas['rutas'] ?? null;
                } catch (PDOException $e) {
                    error_log("Error al obtener rutas cobradas para caja $idCaja: " . $e->getMessage());
                    $caja['rutas_cobradas'] = null;
                }
                
                // Calcular total recolectado con nueva fórmula:
                // El 30% de los seguros se entrega al cobrador, solo el 70% va a la caja
                // Total Recolectado = Saldo Inicial + Total Cobrado + (70% Seguros) + Entradas - Total Gasto - Créditos - Salidas
                $saldoInicial = floatval($caja['saldo_inicial'] ?? 0);
                $totalCobros = floatval($caja['total_cobros'] ?? 0);
                $totalGastos = floatval($caja['total_gastos'] ?? 0);
                $totalDescuentos = floatval($caja['total_descuentos'] ?? 0);
                $totalSeguros = floatval($caja['total_seguros'] ?? 0);
                $totalMontoCreditos = floatval($caja['total_monto_creditos'] ?? 0);
                $totalEntradas = floatval($caja['total_entradas'] ?? 0);
                $totalSalidas = floatval($caja['total_salidas'] ?? 0);
                
                // Calcular 30% de seguros para el cobrador y 70% para la caja
                $segurosCobrador = $totalSeguros * 0.30;
                $segurosCaja = $totalSeguros * 0.70;
                
                // Fórmula: Saldo Inicial + Total Cobrado + (70% Seguros) + Entradas - Total Gasto - Créditos - Salidas
                $totalRecolectado = $saldoInicial + $totalCobros + $segurosCaja + $totalEntradas - $totalGastos - $totalMontoCreditos - $totalSalidas;
                
                $caja['total_recolectado'] = number_format($totalRecolectado, 2, '.', '');
                $caja['total_cobros'] = number_format($totalCobros, 2, '.', '');
                $caja['total_gastos'] = number_format($totalGastos, 2, '.', '');
                $caja['saldo_inicial'] = number_format($saldoInicial, 2, '.', '');
                $caja['total_monto_creditos'] = number_format(floatval($caja['total_monto_creditos'] ?? 0), 2, '.', '');
                $caja['total_descuentos'] = number_format($totalDescuentos, 2, '.', '');
                $caja['total_seguros'] = number_format(floatval($caja['total_seguros'] ?? 0), 2, '.', '');
                $caja['seguros_cobrador'] = number_format($segurosCobrador, 2, '.', '');
                $caja['seguros_caja'] = number_format($segurosCaja, 2, '.', '');
                $caja['total_entradas'] = number_format($totalEntradas, 2, '.', '');
                $caja['total_salidas'] = number_format($totalSalidas, 2, '.', '');
            }
            
            return $resultados;
        } catch (PDOException $e) {
            error_log("Error PDO en consultarCajasAbiertasConResumen: " . $e->getMessage());
            throw new Exception("Error al consultar cajas abiertas: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error en consultarCajasAbiertasConResumen: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Consulta todas las cajas cerradas con resumen de operaciones
     * @return array Lista de cajas cerradas con información agregada
     */
    public function consultarCajasCerradasConResumen() {
        try {
            $sql = "SELECT 
                        c.id_caja,
                        c.id_usuario,
                        c.nombre_caja,
                        c.saldo_inicial,
                        c.saldo_final,
                        c.fecha_apertura,
                        c.fecha_cierre,
                        c.observaciones,
                        u.nombre_usuario,
                        u.nombre_completo AS nombres_usuario,
                        (SELECT GROUP_CONCAT(r.nombre_ruta SEPARATOR ', ') 
                         FROM caja_ruta cr 
                         INNER JOIN rutas r ON cr.id_ruta = r.id_ruta 
                         WHERE cr.id_caja = c.id_caja 
                         LIMIT 1) AS nombre_ruta
                    FROM cajas c
                    LEFT JOIN usuarios u ON c.id_usuario = u.id_usuario
                    WHERE c.estado = 'CERRADA'
                    ORDER BY c.fecha_cierre DESC";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute();
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular totales para cada caja
            foreach ($resultados as &$caja) {
                $idCaja = $caja['id_caja'];
                $idUsuario = $caja['id_usuario'];
                $fechaApertura = !empty($caja['fecha_apertura']) ? date('Y-m-d', strtotime($caja['fecha_apertura'])) : date('Y-m-d');
                
                // Total de cobros y descuentos en esta caja
                try {
                    $sqlCobros = "SELECT 
                                    COALESCE(SUM(monto_pagado), 0) AS total_cobros,
                                    COALESCE(SUM(descuento), 0) AS total_descuentos 
                                 FROM pagos 
                                 WHERE id_caja = :id_caja";
                    $stmtCobros = $this->conexion->prepare($sqlCobros);
                    $stmtCobros->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
                    $stmtCobros->execute();
                    $resultadoCobros = $stmtCobros->fetch(PDO::FETCH_ASSOC);
                    $caja['total_cobros'] = floatval($resultadoCobros['total_cobros'] ?? 0);
                    $caja['total_descuentos'] = floatval($resultadoCobros['total_descuentos'] ?? 0);
                } catch (PDOException $e) {
                    error_log("Error al calcular cobros para caja $idCaja: " . $e->getMessage());
                    $caja['total_cobros'] = 0;
                    $caja['total_descuentos'] = 0;
                }
                
                // Total de gastos en esta caja
                try {
                    $sqlGastos = "SELECT COALESCE(SUM(monto), 0) AS total 
                                 FROM gastos 
                                 WHERE id_caja = :id_caja";
                    $stmtGastos = $this->conexion->prepare($sqlGastos);
                    $stmtGastos->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
                    $stmtGastos->execute();
                    $resultadoGastos = $stmtGastos->fetch(PDO::FETCH_ASSOC);
                    $caja['total_gastos'] = floatval($resultadoGastos['total'] ?? 0);
                } catch (PDOException $e) {
                    error_log("Error al calcular gastos para caja $idCaja: " . $e->getMessage());
                    $caja['total_gastos'] = 0;
                }
                
                // Total de entradas de dinero en esta caja (desde la fecha de apertura)
                try {
                    $fechaAperturaFormato = date('Y-m-d H:i:s', strtotime($caja['fecha_apertura']));
                    $sqlEntradas = "SELECT COALESCE(SUM(monto), 0) AS total 
                                   FROM movimientos_caja 
                                   WHERE id_caja = :id_caja 
                                   AND tipo = 'entrada'
                                   AND fecha_movimiento >= :fecha_apertura";
                    $stmtEntradas = $this->conexion->prepare($sqlEntradas);
                    $stmtEntradas->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
                    $stmtEntradas->bindParam(":fecha_apertura", $fechaAperturaFormato, PDO::PARAM_STR);
                    $stmtEntradas->execute();
                    $resultadoEntradas = $stmtEntradas->fetch(PDO::FETCH_ASSOC);
                    $caja['total_entradas'] = floatval($resultadoEntradas['total'] ?? 0);
                } catch (PDOException $e) {
                    error_log("Error al calcular entradas para caja $idCaja: " . $e->getMessage());
                    $caja['total_entradas'] = 0;
                }
                
                // Total de salidas de dinero en esta caja (desde la fecha de apertura)
                try {
                    $fechaAperturaFormato = date('Y-m-d H:i:s', strtotime($caja['fecha_apertura']));
                    $sqlSalidas = "SELECT COALESCE(SUM(monto), 0) AS total 
                                  FROM movimientos_caja 
                                  WHERE id_caja = :id_caja 
                                  AND tipo = 'salida'
                                  AND fecha_movimiento >= :fecha_apertura";
                    $stmtSalidas = $this->conexion->prepare($sqlSalidas);
                    $stmtSalidas->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
                    $stmtSalidas->bindParam(":fecha_apertura", $fechaAperturaFormato, PDO::PARAM_STR);
                    $stmtSalidas->execute();
                    $resultadoSalidas = $stmtSalidas->fetch(PDO::FETCH_ASSOC);
                    $caja['total_salidas'] = floatval($resultadoSalidas['total'] ?? 0);
                } catch (PDOException $e) {
                    error_log("Error al calcular salidas para caja $idCaja: " . $e->getMessage());
                    $caja['total_salidas'] = 0;
                }
                
                // Contar créditos y calcular monto total de créditos realizados por el usuario en esta caja específica
                // Contar créditos asociados directamente a esta caja usando id_caja
                try {
                    $sqlCreditos = "SELECT COUNT(*) AS total, 
                                    COALESCE(SUM(monto_credito), 0) AS total_monto,
                                    COALESCE(SUM(seguro), 0) AS total_seguros 
                                   FROM creditos 
                                   WHERE id_caja = :id_caja
                                   AND activo = 1";
                    $stmtCreditos = $this->conexion->prepare($sqlCreditos);
                    $stmtCreditos->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
                    $stmtCreditos->execute();
                    $resultadoCreditos = $stmtCreditos->fetch(PDO::FETCH_ASSOC);
                    $caja['total_creditos'] = intval($resultadoCreditos['total'] ?? 0);
                    $caja['total_monto_creditos'] = floatval($resultadoCreditos['total_monto'] ?? 0);
                    $caja['total_seguros'] = floatval($resultadoCreditos['total_seguros'] ?? 0);
                } catch (PDOException $e) {
                    error_log("Error al calcular créditos para caja $idCaja: " . $e->getMessage());
                    $caja['total_creditos'] = 0;
                    $caja['total_monto_creditos'] = 0;
                    $caja['total_seguros'] = 0;
                }
                
                // Obtener todas las rutas asignadas a esta caja
                try {
                    $sqlRutasCaja = "SELECT GROUP_CONCAT(r.nombre_ruta SEPARATOR ', ') AS rutas_asignadas
                                    FROM caja_ruta cr
                                    INNER JOIN rutas r ON cr.id_ruta = r.id_ruta
                                    WHERE cr.id_caja = :id_caja";
                    $stmtRutasCaja = $this->conexion->prepare($sqlRutasCaja);
                    $stmtRutasCaja->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
                    $stmtRutasCaja->execute();
                    $resultadoRutasCaja = $stmtRutasCaja->fetch(PDO::FETCH_ASSOC);
                    $caja['rutas_asignadas'] = $resultadoRutasCaja['rutas_asignadas'] ?? null;
                } catch (PDOException $e) {
                    error_log("Error al obtener rutas asignadas para caja $idCaja: " . $e->getMessage());
                    $caja['rutas_asignadas'] = null;
                }
                
                // Rutas cobradas (lista separada por comas)
                try {
                    $sqlRutas = "SELECT GROUP_CONCAT(DISTINCT r2.nombre_ruta SEPARATOR ', ') AS rutas
                                FROM pagos p
                                LEFT JOIN rutas r2 ON p.id_ruta = r2.id_ruta
                                WHERE p.id_caja = :id_caja
                                AND r2.nombre_ruta IS NOT NULL";
                    $stmtRutas = $this->conexion->prepare($sqlRutas);
                    $stmtRutas->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
                    $stmtRutas->execute();
                    $resultadoRutas = $stmtRutas->fetch(PDO::FETCH_ASSOC);
                    $caja['rutas_cobradas'] = $resultadoRutas['rutas'] ?? null;
                } catch (PDOException $e) {
                    error_log("Error al obtener rutas cobradas para caja $idCaja: " . $e->getMessage());
                    $caja['rutas_cobradas'] = null;
                }
                
                // Calcular total recolectado con nueva fórmula:
                // El 30% de los seguros se entrega al cobrador, solo el 70% va a la caja
                // Total Recolectado = Saldo Inicial + Total Cobrado + (70% Seguros) + Entradas - Total Gasto - Créditos - Salidas
                $saldoInicial = floatval($caja['saldo_inicial'] ?? 0);
                $totalCobros = floatval($caja['total_cobros'] ?? 0);
                $totalGastos = floatval($caja['total_gastos'] ?? 0);
                $totalDescuentos = floatval($caja['total_descuentos'] ?? 0);
                $totalSeguros = floatval($caja['total_seguros'] ?? 0);
                $totalMontoCreditos = floatval($caja['total_monto_creditos'] ?? 0);
                $totalEntradas = floatval($caja['total_entradas'] ?? 0);
                $totalSalidas = floatval($caja['total_salidas'] ?? 0);
                
                // Calcular 30% de seguros para el cobrador y 70% para la caja
                $segurosCobrador = $totalSeguros * 0.30;
                $segurosCaja = $totalSeguros * 0.70;
                
                // Fórmula: Saldo Inicial + Total Cobrado + (70% Seguros) + Entradas - Total Gasto - Créditos - Salidas
                $totalRecolectado = $saldoInicial + $totalCobros + $segurosCaja + $totalEntradas - $totalGastos - $totalMontoCreditos - $totalSalidas;
                
                $caja['total_recolectado'] = number_format($totalRecolectado, 2, '.', '');
                $caja['total_cobros'] = number_format($totalCobros, 2, '.', '');
                $caja['total_gastos'] = number_format($totalGastos, 2, '.', '');
                $caja['saldo_inicial'] = number_format($saldoInicial, 2, '.', '');
                $caja['saldo_final'] = !empty($caja['saldo_final']) ? number_format(floatval($caja['saldo_final']), 2, '.', '') : null;
                $caja['total_monto_creditos'] = number_format(floatval($caja['total_monto_creditos'] ?? 0), 2, '.', '');
                $caja['total_descuentos'] = number_format($totalDescuentos, 2, '.', '');
                $caja['total_seguros'] = number_format(floatval($caja['total_seguros'] ?? 0), 2, '.', '');
                $caja['seguros_cobrador'] = number_format($segurosCobrador, 2, '.', '');
                $caja['seguros_caja'] = number_format($segurosCaja, 2, '.', '');
                $caja['total_entradas'] = number_format($totalEntradas, 2, '.', '');
                $caja['total_salidas'] = number_format($totalSalidas, 2, '.', '');
            }
            
            return $resultados;
        } catch (PDOException $e) {
            error_log("Error PDO en consultarCajasCerradasConResumen: " . $e->getMessage());
            throw new Exception("Error al consultar cajas cerradas: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error en consultarCajasCerradasConResumen: " . $e->getMessage());
            throw $e;
        }
    }
}
?>
