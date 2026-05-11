<?php 
/**
 * Modelo de Créditos
 * Maneja todas las operaciones relacionadas con créditos en la base de datos
 */
class Creditos {
    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    /**
     * Consulta todos los créditos con información del cliente
     * @return array Lista de créditos con datos del cliente
     */
    public function consultar() {
        $sql = "SELECT 
                    c.id_credito,
                    c.id_cliente,
                    c.fecha_toma_credito,
                    c.hora_toma_credito,
                    c.monto_credito,
                    c.cuotas,
                    c.tasa_interes,
                    c.frecuencia_pago,
                    c.seguro,
                    c.saldo_actual,
                    c.tipo_credito,
                    c.activo,
                    c.id_usuario,
                    c.id_ruta,
                    c.fecha_finaliza_credito,
                    c.estado_credito,
                    c.fecha_cancelacion,
                    c.id_usuario_cancelacion,
                    c.orden_cobranza,
                    cl.nombres AS nombres_cliente,
                    cl.apellidos AS apellidos_cliente,
                    cl.documento AS documento_cliente,
                    r.nombre_ruta
                FROM creditos c
                JOIN clientes cl ON c.id_cliente = cl.id_cliente
                LEFT JOIN rutas r ON c.id_ruta = r.id_ruta
                WHERE c.activo = 1
                ORDER BY c.id_credito DESC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Consulta créditos con filtro de búsqueda por nombre del cliente
     * @param string $terminoBusqueda Término de búsqueda
     * @return array Lista de créditos filtrados
     */
    public function buscar($terminoBusqueda) {
        // Construir la consulta base
        $sql = "SELECT 
                    c.id_credito,
                    c.id_cliente,
                    c.fecha_toma_credito,
                    c.hora_toma_credito,
                    c.monto_credito,
                    c.cuotas,
                    c.tasa_interes,
                    c.frecuencia_pago,
                    c.seguro,
                    c.saldo_actual,
                    c.tipo_credito,
                    c.activo,
                    c.id_usuario,
                    c.id_ruta,
                    c.fecha_finaliza_credito,
                    c.estado_credito,
                    c.fecha_cancelacion,
                    c.id_usuario_cancelacion,
                    cl.nombres AS nombres_cliente,
                    cl.apellidos AS apellidos_cliente,
                    cl.documento AS documento_cliente,
                    r.nombre_ruta
                FROM creditos c
                JOIN clientes cl ON c.id_cliente = cl.id_cliente
                LEFT JOIN rutas r ON c.id_ruta = r.id_ruta
                WHERE c.activo = 1";
        
        // Construir condiciones de búsqueda
        $condiciones = [];
        $termino = "%$terminoBusqueda%";
        
        // Búsqueda por nombre o apellido del cliente
        $condiciones[] = "(cl.nombres LIKE :termino1 OR cl.apellidos LIKE :termino2)";
        
        // Búsqueda por documento del cliente
        $condiciones[] = "cl.documento LIKE :termino3";
        
        // Búsqueda por ID de crédito (solo si el término es numérico)
        if (is_numeric($terminoBusqueda) && $terminoBusqueda > 0) {
            $condiciones[] = "c.id_credito = :id_credito";
        }
        
        // Agregar condiciones a la consulta
        if (!empty($condiciones)) {
            $sql .= " AND (" . implode(" OR ", $condiciones) . ")";
        }
        
        $sql .= " ORDER BY c.id_credito DESC";
        
        $stmt = $this->conexion->prepare($sql);
        
        // Bind de los parámetros de término (todos con el mismo valor)
        $stmt->bindValue(":termino1", $termino);
        $stmt->bindValue(":termino2", $termino);
        $stmt->bindValue(":termino3", $termino);
        
        // Si el término es numérico, también buscar por ID exacto
        if (is_numeric($terminoBusqueda) && $terminoBusqueda > 0) {
            $idCredito = (int)$terminoBusqueda;
            $stmt->bindValue(":id_credito", $idCredito, PDO::PARAM_INT);
        }
        
        try {
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en buscar créditos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Consulta un crédito por su ID
     * @param int $idCredito ID del crédito
     * @return array|null Datos del crédito o null si no existe
     */
    public function consultarPorId($idCredito) {
        $sql = "SELECT 
                    c.*,
                    cl.nombres AS nombres_cliente,
                    cl.apellidos AS apellidos_cliente,
                    cl.documento AS documento_cliente
                FROM creditos c
                LEFT JOIN clientes cl ON c.id_cliente = cl.id_cliente
                WHERE c.id_credito = :id";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(":id", $idCredito, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica si un crédito tiene pagos registrados
     * @param int $idCredito ID del crédito
     * @return bool True si tiene pagos, False si no
     */
    public function tienePagos($idCredito) {
        $sql = "SELECT COUNT(*) AS total_pagos FROM pagos WHERE id_credito = :id";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(":id", $idCredito, PDO::PARAM_INT);
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado['total_pagos'] > 0;
    }

    /**
     * Verifica si un cliente tiene un crédito pendiente y devuelve la lista de créditos activos con saldo
     * @param int $idCliente ID del cliente
     * @return array Array con 'tiene_credito' (bool) y 'creditos' (array de créditos)
     */
    public function clienteTieneCreditoPendiente($idCliente) {
        $sql = "SELECT id_credito, saldo_actual, monto_credito, fecha_toma_credito, fecha_finaliza_credito, tipo_credito 
                FROM creditos 
                WHERE id_cliente = :id_cliente 
                AND saldo_actual > 0 
                AND activo = 1
                AND (estado_credito = 'activo' OR estado_credito IS NULL)
                ORDER BY id_credito DESC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(":id_cliente", $idCliente, PDO::PARAM_INT);
        $stmt->execute();
        $creditos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'tiene_credito' => count($creditos) > 0,
            'creditos' => $creditos
        ];
    }

    /**
     * Calcula el seguro según el monto y las cuotas
     * @param float $montoCredito Monto del crédito
     * @param int $cuotas Número de cuotas (días)
     * @return float Monto del seguro calculado
     */
    public function calcularSeguro($montoCredito, $cuotas) {
        $seguro = 0;
        
        // Calcular seguro según rangos
        if ($montoCredito >= 0 && $montoCredito <= 100) {
            $seguro = 5;
        } elseif ($montoCredito >= 101 && $montoCredito <= 200) {
            $seguro = 10;
        } elseif ($montoCredito >= 201 && $montoCredito <= 300) {
            $seguro = 15;
        } elseif ($montoCredito >= 301 && $montoCredito <= 400) {
            $seguro = 20;
        } elseif ($montoCredito >= 401 && $montoCredito <= 500) {
            $seguro = 25;
        } elseif ($montoCredito >= 501 && $montoCredito <= 600) {
            $seguro = 30;
        } elseif ($montoCredito >= 601 && $montoCredito <= 700) {
            $seguro = 35;
        } elseif ($montoCredito >= 701 && $montoCredito <= 800) {
            $seguro = 40;
        } elseif ($montoCredito >= 801 && $montoCredito <= 900) {
            $seguro = 45;
        } elseif ($montoCredito >= 901 && $montoCredito <= 1000) {
            $seguro = 50;
        } else {
            // Para montos mayores a 1000: $50 + $5 por cada $100 adicionales
            $seguro = 50 + (floor(($montoCredito - 1000) / 100) * 5);
        }
        
        // Doble del seguro si el crédito es de 70 días
        if ($cuotas == 70) {
            $seguro *= 2;
        }
        
        return $seguro;
    }

    /**
     * Calcula la tasa de interés según las cuotas
     * @param int $cuotas Número de cuotas (días)
     * @return float Tasa de interés
     */
    public function calcularTasaInteres($cuotas) {
        return ($cuotas == 70) ? 48 : 24;
    }

    /**
     * Inserta un nuevo crédito en la base de datos
     * @param array $params Parámetros del crédito
     * @return array Resultado de la operación
     */
    public function insertar($params) {
        // Obtener el siguiente orden de cobranza para esta ruta
        $idRuta = isset($params['id_ruta']) && $params['id_ruta'] !== null && $params['id_ruta'] !== '' && $params['id_ruta'] !== 0 
            ? (int)$params['id_ruta'] 
            : null;
        
        $ordenCobranza = $this->obtenerSiguienteOrdenCobranza($idRuta);
        
        $sql = "INSERT INTO creditos (
                    id_cliente, monto_credito, cuotas, tasa_interes, 
                    frecuencia_pago, seguro, saldo_actual, 
                    fecha_toma_credito, hora_toma_credito, fecha_finaliza_credito, 
                    id_usuario, id_ruta, id_caja, activo, orden_cobranza, tipo_credito
                ) VALUES (
                    :id_cliente, :monto_credito, :cuotas, :tasa_interes, 
                    :frecuencia_pago, :seguro, :saldo_actual, 
                    :fecha_toma_credito, :hora_toma_credito, :fecha_finaliza_credito, 
                    :id_usuario, :id_ruta, :id_caja, 1, :orden_cobranza, 'comun'
                )";

        $stmt = $this->conexion->prepare($sql);

        // Validar que todos los parámetros requeridos estén presentes
        $camposRequeridos = [
            'id_cliente', 'monto_credito', 'cuotas', 'tasa_interes',
            'frecuencia_pago', 'seguro', 'saldo_actual',
            'fecha_toma_credito', 'hora_toma_credito', 'fecha_finaliza_credito'
        ];
        
        foreach ($camposRequeridos as $campo) {
            if (!isset($params[$campo])) {
                throw new Exception("Falta el parámetro requerido: $campo");
            }
        }

        $stmt->bindValue(":id_cliente", (int)$params['id_cliente'], PDO::PARAM_INT);
        $stmt->bindValue(":monto_credito", $params['monto_credito']);
        $stmt->bindValue(":cuotas", (int)$params['cuotas'], PDO::PARAM_INT);
        $stmt->bindValue(":tasa_interes", $params['tasa_interes']);
        $stmt->bindValue(":frecuencia_pago", $params['frecuencia_pago']);
        $stmt->bindValue(":seguro", $params['seguro']);
        $stmt->bindValue(":saldo_actual", $params['saldo_actual']);
        $stmt->bindValue(":fecha_toma_credito", $params['fecha_toma_credito']);
        $stmt->bindValue(":hora_toma_credito", $params['hora_toma_credito']);
        $stmt->bindValue(":fecha_finaliza_credito", $params['fecha_finaliza_credito']);
        
        // Manejar id_usuario que puede ser null
        // Para valores null, no especificar el tipo (PDO lo detecta automáticamente)
        if (isset($params['id_usuario']) && $params['id_usuario'] !== null && $params['id_usuario'] !== '' && $params['id_usuario'] !== 0) {
            $idUsuario = (int)$params['id_usuario'];
            $stmt->bindValue(":id_usuario", $idUsuario, PDO::PARAM_INT);
        } else {
            // Para null, no especificar tipo - PDO lo maneja automáticamente
            $stmt->bindValue(":id_usuario", null);
        }
        
        // Manejar id_ruta que puede ser null
        if (isset($params['id_ruta']) && $params['id_ruta'] !== null && $params['id_ruta'] !== '' && $params['id_ruta'] !== 0) {
            $idRuta = (int)$params['id_ruta'];
            $stmt->bindValue(":id_ruta", $idRuta, PDO::PARAM_INT);
        } else {
            // Para null, no especificar tipo - PDO lo maneja automáticamente
            $stmt->bindValue(":id_ruta", null);
        }
        
        // Manejar id_caja que puede ser null
        if (isset($params['id_caja']) && $params['id_caja'] !== null && $params['id_caja'] !== '' && $params['id_caja'] !== 0) {
            $idCaja = (int)$params['id_caja'];
            $stmt->bindValue(":id_caja", $idCaja, PDO::PARAM_INT);
        } else {
            // Para null, no especificar tipo - PDO lo maneja automáticamente
            $stmt->bindValue(":id_caja", null);
        }
        
        // Asignar orden de cobranza
        $stmt->bindValue(":orden_cobranza", $ordenCobranza, PDO::PARAM_INT);

        try {
            $stmt->execute();
            $idCredito = $this->conexion->lastInsertId();
            
            // Si el crédito se completó (saldo = 0), reordenar automáticamente
            if (isset($params['saldo_actual']) && floatval($params['saldo_actual']) == 0) {
                $this->reordenarCobranza($idRuta, $ordenCobranza);
            }
        } catch (PDOException $e) {
            throw new Exception("Error al insertar crédito: " . $e->getMessage());
        }

        return [
            "resultado" => "success",
            "mensaje" => "Crédito insertado correctamente",
            "id_credito" => $this->conexion->lastInsertId()
        ];
    }

    /**
     * Actualiza un crédito existente
     * @param int $idCredito ID del crédito
     * @param array $params Parámetros actualizados
     * @return array Resultado de la operación
     */
    public function editar($idCredito, $params) {
        // Construir la consulta dinámicamente para incluir fecha_toma_credito solo si se proporciona
        $campos = [
            "id_cliente = :id_cliente",
            "monto_credito = :monto_credito",
            "cuotas = :cuotas",
            "tasa_interes = :tasa_interes",
            "frecuencia_pago = :frecuencia_pago",
            "seguro = :seguro",
            "saldo_actual = :saldo_actual",
            "fecha_finaliza_credito = :fecha_finaliza_credito"
        ];
        
        // Agregar fecha_toma_credito y hora_toma_credito si se proporcionan
        if (isset($params['fecha_toma_credito']) && !empty($params['fecha_toma_credito'])) {
            $campos[] = "fecha_toma_credito = :fecha_toma_credito";
        }
        if (isset($params['hora_toma_credito']) && !empty($params['hora_toma_credito'])) {
            $campos[] = "hora_toma_credito = :hora_toma_credito";
        }
        
        $sql = "UPDATE creditos SET " . implode(", ", $campos) . " WHERE id_credito = :id_credito";

        $stmt = $this->conexion->prepare($sql);

        $stmt->bindParam(":id_credito", $idCredito, PDO::PARAM_INT);
        $stmt->bindParam(":id_cliente", $params['id_cliente'], PDO::PARAM_INT);
        $stmt->bindParam(":monto_credito", $params['monto_credito']);
        $stmt->bindParam(":cuotas", $params['cuotas'], PDO::PARAM_INT);
        $stmt->bindParam(":tasa_interes", $params['tasa_interes']);
        $stmt->bindParam(":frecuencia_pago", $params['frecuencia_pago']);
        $stmt->bindParam(":seguro", $params['seguro']);
        $stmt->bindParam(":saldo_actual", $params['saldo_actual']);
        $stmt->bindParam(":fecha_finaliza_credito", $params['fecha_finaliza_credito']);
        
        // Bind de fecha_toma_credito y hora_toma_credito si se proporcionan
        if (isset($params['fecha_toma_credito']) && !empty($params['fecha_toma_credito'])) {
            $stmt->bindParam(":fecha_toma_credito", $params['fecha_toma_credito']);
        }
        if (isset($params['hora_toma_credito']) && !empty($params['hora_toma_credito'])) {
            $stmt->bindParam(":hora_toma_credito", $params['hora_toma_credito']);
        }

        $stmt->execute();

        return [
            "resultado" => "success",
            "mensaje" => "Crédito actualizado correctamente"
        ];
    }

    /**
     * Elimina un crédito (solo si no tiene pagos)
     * @param int $idCredito ID del crédito
     * @return array Resultado de la operación
     */
    public function eliminar($idCredito) {
        // Verificar si tiene pagos
        if ($this->tienePagos($idCredito)) {
            return [
                "resultado" => "error",
                "mensaje" => "No se puede eliminar el crédito porque ya tiene pagos registrados"
            ];
        }

        // Eliminar plan de pagos primero
        $sqlPlanPagos = "DELETE FROM planpagos WHERE id_credito = :id_credito";
        $stmtPlanPagos = $this->conexion->prepare($sqlPlanPagos);
        $stmtPlanPagos->bindParam(":id_credito", $idCredito, PDO::PARAM_INT);
        $stmtPlanPagos->execute();

        // Eliminar el crédito
        $sql = "DELETE FROM creditos WHERE id_credito = :id_credito";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(":id_credito", $idCredito, PDO::PARAM_INT);
        $stmt->execute();

        return [
            "resultado" => "success",
            "mensaje" => "Crédito eliminado correctamente"
        ];
    }

    /**
     * Cancela un crédito
     * @param int $idCredito ID del crédito a cancelar
     * @param int $idUsuario ID del usuario que cancela el crédito
     * @return array Resultado de la operación
     */
    public function cancelar($idCredito, $idUsuario) {
        $sql = "UPDATE creditos 
                SET estado_credito = 'cancelado',
                    fecha_cancelacion = CURDATE(),
                    id_usuario_cancelacion = :id_usuario
                WHERE id_credito = :id_credito
                AND activo = 1
                AND estado_credito = 'activo'";
        
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindValue(":id_credito", $idCredito, PDO::PARAM_INT);
        $stmt->bindValue(":id_usuario", $idUsuario, PDO::PARAM_INT);
        
        try {
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                return [
                    "resultado" => "ok",
                    "mensaje" => "Crédito cancelado correctamente"
                ];
            } else {
                return [
                    "resultado" => "error",
                    "mensaje" => "No se pudo cancelar el crédito. Verifique que el crédito esté activo."
                ];
            }
        } catch (PDOException $e) {
            return [
                "resultado" => "error",
                "mensaje" => "Error al cancelar el crédito: " . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene el último ID insertado
     * @return int ID del último crédito insertado
     */
    public function obtenerUltimoId() {
        return $this->conexion->lastInsertId();
    }

    /**
     * Refinancia un crédito existente
     * @param array $params Parámetros de refinanciación
     * @return array Resultado de la operación
     */
    public function refinanciarCredito($params) {
        try {
            $this->conexion->beginTransaction();

            $idCreditoAnterior = $params['id_credito_anterior'];
            $idCliente = $params['id_cliente'];
            $saldoPendiente = floatval($params['saldo_pendiente']);
            $nuevoMonto = floatval($params['nuevo_monto']);
            $nuevasCuotas = intval($params['cuotas']);
            $frecuenciaPago = $params['frecuencia_pago'];
            $tipoRefinanciacion = $params['tipo_refinanciacion'];
            $incluirSeguro = isset($params['incluir_seguro']) && $params['incluir_seguro'] === true;
            $idUsuario = $params['id_usuario'];
            $idRuta = $params['id_ruta'] ?? null;
            $idCaja = $params['id_caja'] ?? null;

            // Validar saldo pendiente
            if ($saldoPendiente < 0) {
                throw new Exception("El saldo pendiente no puede ser negativo");
            }

            // Calcular seguro
            $seguro = 0;
            if ($incluirSeguro && $nuevoMonto > 0) {
                // Regla de negocio: el seguro en refinanciacion se calcula solo sobre el nuevo monto.
                $baseSeguro = $nuevoMonto;
                $seguro = (floor(($baseSeguro - 1) / 100) + 1) * 5;
                if ($nuevasCuotas === 70) {
                    $seguro *= 2;
                }
            }

            // Calcular tasa de interés
            $tasaInteres = ($nuevasCuotas === 70) ? 48 : 24;
            $intereses = $nuevoMonto * ($tasaInteres / 100);

            // Calcular saldo actual según tipo de refinanciación
            if ($tipoRefinanciacion === 'descontar') {
                $saldoActualConIntereses = $nuevoMonto + $intereses;
            } else {
                $saldoActualConIntereses = $nuevoMonto + $intereses + $saldoPendiente;
            }

            // Fechas
            $fechaTomaCredito = date('Y-m-d');
            $horaTomaCredito = date('H:i:s');
            $fechaFinalizaCredito = date('Y-m-d', strtotime("+$nuevasCuotas days"));

            // 1. Registrar pago del crédito anterior (liquidación)
            // Si es refinanciación "sin descontar", el saldo no entra en caja (se transfiere al nuevo crédito), por tanto id_caja = null para que no sume en el cuadre de caja
            $fechaPago = date('Y-m-d');
            $horaPago = date('H:i:s');
            $idCajaPago = ($tipoRefinanciacion === 'descontar') ? $idCaja : null;
            $sqlPago = "INSERT INTO pagos (id_cliente, id_credito, fecha_pago, hora_pago, monto_pagado, descuento, id_usuario, id_ruta, id_caja) 
                       VALUES (:id_cliente, :id_credito, :fecha_pago, :hora_pago, :monto_pagado, 0, :id_usuario, :id_ruta, :id_caja)";
            $stmtPago = $this->conexion->prepare($sqlPago);
            $stmtPago->bindParam(":id_cliente", $idCliente, PDO::PARAM_INT);
            $stmtPago->bindParam(":id_credito", $idCreditoAnterior, PDO::PARAM_INT);
            $stmtPago->bindParam(":fecha_pago", $fechaPago);
            $stmtPago->bindParam(":hora_pago", $horaPago);
            $stmtPago->bindParam(":monto_pagado", $saldoPendiente);
            $stmtPago->bindParam(":id_usuario", $idUsuario, PDO::PARAM_INT);
            $stmtPago->bindParam(":id_ruta", $idRuta, PDO::PARAM_INT);
            if ($idCajaPago !== null && $idCajaPago !== '' && $idCajaPago !== 0) {
                $stmtPago->bindParam(":id_caja", $idCajaPago, PDO::PARAM_INT);
            } else {
                $stmtPago->bindValue(":id_caja", null, PDO::PARAM_NULL);
            }
            $stmtPago->execute();

            // 2. Actualizar crédito anterior (liquidar)
            $sqlActualizar = "UPDATE creditos SET saldo_actual = 0, activo = 0 WHERE id_credito = :id_credito";
            $stmtActualizar = $this->conexion->prepare($sqlActualizar);
            $stmtActualizar->bindParam(":id_credito", $idCreditoAnterior, PDO::PARAM_INT);
            $stmtActualizar->execute();

            // 3. Crear nuevo crédito refinanciado manualmente
            // Obtener el orden de cobranza del crédito anterior para mantenerlo
            $sqlOrdenAnterior = "SELECT orden_cobranza FROM creditos WHERE id_credito = :id_credito_anterior";
            $stmtOrdenAnterior = $this->conexion->prepare($sqlOrdenAnterior);
            $stmtOrdenAnterior->bindParam(":id_credito_anterior", $idCreditoAnterior, PDO::PARAM_INT);
            $stmtOrdenAnterior->execute();
            $ordenAnterior = $stmtOrdenAnterior->fetch(PDO::FETCH_ASSOC);
            $ordenCobranzaRefinanciado = $ordenAnterior ? intval($ordenAnterior['orden_cobranza']) : $this->obtenerSiguienteOrdenCobranza($idRuta);
            
            $sqlNuevoCredito = "INSERT INTO creditos (
                id_cliente, fecha_toma_credito, monto_credito, cuotas, tasa_interes, 
                frecuencia_pago, seguro, saldo_actual, activo, id_usuario, 
                id_ruta, id_caja, fecha_finaliza_credito, hora_toma_credito, tipo_credito, orden_cobranza
            ) VALUES (
                :id_cliente, :fecha_toma_credito, :monto_credito, :cuotas, :tasa_interes, 
                :frecuencia_pago, :seguro, :saldo_actual, 1, :id_usuario, 
                :id_ruta, :id_caja, :fecha_finaliza_credito, :hora_toma_credito, 'refinanciado', :orden_cobranza
            )";
            $stmtNuevoCredito = $this->conexion->prepare($sqlNuevoCredito);
            $stmtNuevoCredito->bindParam(":id_cliente", $idCliente, PDO::PARAM_INT);
            $stmtNuevoCredito->bindParam(":fecha_toma_credito", $fechaTomaCredito);
            $stmtNuevoCredito->bindParam(":monto_credito", $nuevoMonto);
            $stmtNuevoCredito->bindParam(":cuotas", $nuevasCuotas, PDO::PARAM_INT);
            $stmtNuevoCredito->bindParam(":tasa_interes", $tasaInteres);
            $stmtNuevoCredito->bindParam(":frecuencia_pago", $frecuenciaPago);
            $stmtNuevoCredito->bindParam(":seguro", $seguro);
            $stmtNuevoCredito->bindParam(":saldo_actual", $saldoActualConIntereses);
            $stmtNuevoCredito->bindParam(":id_usuario", $idUsuario, PDO::PARAM_INT);
            $stmtNuevoCredito->bindParam(":id_ruta", $idRuta, PDO::PARAM_INT);
            
            // Manejar id_caja que puede ser null
            if ($idCaja !== null && $idCaja !== '' && $idCaja !== 0) {
                $stmtNuevoCredito->bindParam(":id_caja", $idCaja, PDO::PARAM_INT);
            } else {
                $stmtNuevoCredito->bindValue(":id_caja", null);
            }
            
            $stmtNuevoCredito->bindParam(":fecha_finaliza_credito", $fechaFinalizaCredito);
            $stmtNuevoCredito->bindParam(":hora_toma_credito", $horaTomaCredito);
            $stmtNuevoCredito->bindParam(":orden_cobranza", $ordenCobranzaRefinanciado, PDO::PARAM_INT);
            
            $stmtNuevoCredito->execute();

            $idNuevoCredito = $this->conexion->lastInsertId();

            // 4. Generar plan de pagos (los refinanciados manuales SÍ tienen plan de pagos)
            require_once "planPagosModelos.php";
            $planPagos = new PlanPagos($this->conexion);
            $planPagos->crearPlanPagos($idNuevoCredito, $nuevoMonto, $intereses, $nuevasCuotas, $frecuenciaPago, $fechaTomaCredito);

            $this->conexion->commit();

            return [
                "resultado" => "ok",
                "mensaje" => "Refinanciación completada exitosamente",
                "id_credito_anterior" => $idCreditoAnterior,
                "id_nuevo_credito" => $idNuevoCredito,
                "monto_entregar" => ($tipoRefinanciacion === 'descontar') ? ($nuevoMonto - $saldoPendiente - $seguro) : ($nuevoMonto - $seguro)
            ];
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("Error al refinanciar crédito: " . $e->getMessage());
            return [
                "resultado" => "error",
                "mensaje" => "Error al refinanciar crédito: " . $e->getMessage()
            ];
        }
    }

    /**
     * Refinancia automáticamente créditos que han llegado a su fecha de finalización
     * Se ejecuta al día siguiente de la fecha_finaliza_credito
     * @return array Resultado con créditos refinanciados
     */
    public function refinanciarCreditosVencidos() {
        try {
            $this->conexion->beginTransaction();
            
            // Obtener fecha de hace 5 días (créditos que vencieron hace 5 días o más)
            $fechaHace5Dias = date('Y-m-d', strtotime('-5 days'));
            
            // Consultar créditos activos que vencieron hace 5 días o más (todos los tipos: común, refinanciado, refinanciado_por_sistema)
            $sql = "SELECT c.*, cl.id_cliente
                    FROM creditos c
                    JOIN clientes cl ON c.id_cliente = cl.id_cliente
                    WHERE c.activo = 1 
                    AND c.saldo_actual > 0
                    AND c.fecha_finaliza_credito <= :fecha_hace_5_dias
                    AND (c.estado_credito = 'activo' OR c.estado_credito IS NULL)";
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":fecha_hace_5_dias", $fechaHace5Dias);
            $stmt->execute();
            $creditosVencidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $creditosRefinanciados = [];
            
            foreach ($creditosVencidos as $credito) {
                $idCreditoAnterior = $credito['id_credito'];
                $idCliente = $credito['id_cliente'];
                $saldoActual = floatval($credito['saldo_actual']);
                $idRuta = $credito['id_ruta'];
                $ordenCobranza = $credito['orden_cobranza'];
                
                // Calcular nuevo monto: saldo actual + 24% de interés
                $tasaInteres = 24;
                $intereses = $saldoActual * ($tasaInteres / 100);
                $nuevoMonto = $saldoActual + $intereses;
                
                // Calcular seguro según el nuevo monto
                $seguro = $this->calcularSeguro($nuevoMonto, 30); // 30 días para refinanciación automática
                
                // Saldo actual del nuevo crédito: nuevo monto + seguro
                $saldoActualNuevo = $nuevoMonto + $seguro;
                
                // Fechas: hoy es la fecha de toma, fecha finaliza = 1 mes desde hoy (fecha de corte)
                $fechaTomaCredito = date('Y-m-d');
                $horaTomaCredito = date('H:i:s');
                $fechaFinalizaCredito = date('Y-m-d', strtotime('+1 month')); // 1 mes = fecha de corte
                
                // 1. Registrar pago del crédito anterior (liquidación)
                $fechaPago = date('Y-m-d');
                $horaPago = date('H:i:s');
                $sqlPago = "INSERT INTO pagos (id_cliente, id_credito, fecha_pago, hora_pago, monto_pagado, descuento, id_usuario, id_ruta, id_caja) 
                           VALUES (:id_cliente, :id_credito, :fecha_pago, :hora_pago, :monto_pagado, 0, :id_usuario, :id_ruta, NULL)";
                $stmtPago = $this->conexion->prepare($sqlPago);
                $stmtPago->bindParam(":id_cliente", $idCliente, PDO::PARAM_INT);
                $stmtPago->bindParam(":id_credito", $idCreditoAnterior, PDO::PARAM_INT);
                $stmtPago->bindParam(":fecha_pago", $fechaPago);
                $stmtPago->bindParam(":hora_pago", $horaPago);
                $stmtPago->bindParam(":monto_pagado", $saldoActual);
                $idUsuarioSistema = 1; // Usuario sistema para refinanciaciones automáticas
                $stmtPago->bindParam(":id_usuario", $idUsuarioSistema, PDO::PARAM_INT);
                $stmtPago->bindParam(":id_ruta", $idRuta, PDO::PARAM_INT);
                $stmtPago->execute();
                
                // 2. Actualizar crédito anterior (liquidar)
                $sqlActualizar = "UPDATE creditos SET saldo_actual = 0, activo = 0 WHERE id_credito = :id_credito";
                $stmtActualizar = $this->conexion->prepare($sqlActualizar);
                $stmtActualizar->bindParam(":id_credito", $idCreditoAnterior, PDO::PARAM_INT);
                $stmtActualizar->execute();
                
                // 3. Crear nuevo crédito refinanciado automáticamente
                // NO generar plan de pagos para refinanciaciones automáticas
                // Usar 'diario' como frecuencia_pago (el frontend mostrará "RECLAMO" para refinanciados por sistema)
                $sqlNuevoCredito = "INSERT INTO creditos (
                    id_cliente, fecha_toma_credito, monto_credito, cuotas, tasa_interes, 
                    frecuencia_pago, seguro, saldo_actual, activo, id_usuario, 
                    id_ruta, id_caja, fecha_finaliza_credito, hora_toma_credito, tipo_credito, orden_cobranza
                ) VALUES (
                    :id_cliente, :fecha_toma_credito, :monto_credito, :cuotas, :tasa_interes, 
                    'diario', :seguro, :saldo_actual, 1, :id_usuario, 
                    :id_ruta, NULL, :fecha_finaliza_credito, :hora_toma_credito, 'refinanciado_por_sistema', :orden_cobranza
                )";
                $stmtNuevoCredito = $this->conexion->prepare($sqlNuevoCredito);
                $stmtNuevoCredito->bindParam(":id_cliente", $idCliente, PDO::PARAM_INT);
                $stmtNuevoCredito->bindParam(":fecha_toma_credito", $fechaTomaCredito);
                $stmtNuevoCredito->bindParam(":monto_credito", $nuevoMonto);
                $cuotas = 30; // 30 días para refinanciación automática
                $stmtNuevoCredito->bindParam(":cuotas", $cuotas, PDO::PARAM_INT);
                $stmtNuevoCredito->bindParam(":tasa_interes", $tasaInteres);
                $stmtNuevoCredito->bindParam(":seguro", $seguro);
                $stmtNuevoCredito->bindParam(":saldo_actual", $saldoActualNuevo);
                $stmtNuevoCredito->bindParam(":id_usuario", $idUsuarioSistema, PDO::PARAM_INT);
                $stmtNuevoCredito->bindParam(":id_ruta", $idRuta, PDO::PARAM_INT);
                $stmtNuevoCredito->bindParam(":fecha_finaliza_credito", $fechaFinalizaCredito);
                $stmtNuevoCredito->bindParam(":hora_toma_credito", $horaTomaCredito);
                $stmtNuevoCredito->bindParam(":orden_cobranza", $ordenCobranza, PDO::PARAM_INT);
                $stmtNuevoCredito->execute();
                
                $idNuevoCredito = $this->conexion->lastInsertId();
                
                $creditosRefinanciados[] = [
                    'id_credito_anterior' => $idCreditoAnterior,
                    'id_nuevo_credito' => $idNuevoCredito,
                    'id_cliente' => $idCliente
                ];
            }
            
            $this->conexion->commit();
            
            return [
                "resultado" => "ok",
                "mensaje" => "Refinanciación automática completada",
                "creditos_refinanciados" => count($creditosRefinanciados),
                "detalle" => $creditosRefinanciados
            ];
        } catch (Exception $e) {
            $this->conexion->rollBack();
            error_log("Error al refinanciar créditos vencidos automáticamente: " . $e->getMessage());
            return [
                "resultado" => "error",
                "mensaje" => "Error al refinanciar créditos vencidos: " . $e->getMessage()
            ];
        }
    }

    /**
     * Obtiene el siguiente orden de cobranza para una ruta
     * @param int|null $idRuta ID de la ruta (null para caja general)
     * @return int Siguiente orden disponible
     */
    private function obtenerSiguienteOrdenCobranza($idRuta) {
        if ($idRuta === null) {
            // Para caja general, obtener el máximo orden sin ruta
            $sql = "SELECT COALESCE(MAX(orden_cobranza), 0) + 1 AS siguiente_orden 
                    FROM creditos 
                    WHERE activo = 1 AND saldo_actual > 0 AND id_ruta IS NULL";
        } else {
            // Para una ruta específica, obtener el máximo orden de esa ruta
            $sql = "SELECT COALESCE(MAX(orden_cobranza), 0) + 1 AS siguiente_orden 
                    FROM creditos 
                    WHERE activo = 1 AND saldo_actual > 0 AND id_ruta = :id_ruta";
        }
        
        $stmt = $this->conexion->prepare($sql);
        if ($idRuta !== null) {
            $stmt->bindParam(":id_ruta", $idRuta, PDO::PARAM_INT);
        }
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)($resultado['siguiente_orden'] ?? 1);
    }

    /**
     * Reordena los créditos cuando uno se completa (saldo = 0)
     * Los créditos posteriores al completado avanzan una posición
     * @param int|null $idRuta ID de la ruta
     * @param int $ordenCompletado Orden del crédito que se completó
     */
    public function reordenarCobranza($idRuta, $ordenCompletado) {
        try {
            if ($idRuta === null) {
                // Reordenar créditos sin ruta
                $sql = "UPDATE creditos 
                        SET orden_cobranza = orden_cobranza - 1 
                        WHERE activo = 1 
                        AND saldo_actual > 0 
                        AND id_ruta IS NULL 
                        AND orden_cobranza > :orden_completado";
            } else {
                // Reordenar créditos de una ruta específica
                $sql = "UPDATE creditos 
                        SET orden_cobranza = orden_cobranza - 1 
                        WHERE activo = 1 
                        AND saldo_actual > 0 
                        AND id_ruta = :id_ruta 
                        AND orden_cobranza > :orden_completado";
            }
            
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(":orden_completado", $ordenCompletado, PDO::PARAM_INT);
            if ($idRuta !== null) {
                $stmt->bindParam(":id_ruta", $idRuta, PDO::PARAM_INT);
            }
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error al reordenar cobranza: " . $e->getMessage());
        }
    }

    /**
     * Actualiza el orden de cobranza de los créditos
     * @param int|null $idRuta ID de la ruta
     * @param array $orden Array con los IDs de créditos en orden
     * @return array Resultado de la operación
     */
    public function actualizarOrdenCobranza($idRuta, $orden) {
        try {
            $this->conexion->beginTransaction();
            
            // Resetear todos los órdenes a 0 para esta ruta
            if ($idRuta === null) {
                $sqlReset = "UPDATE creditos SET orden_cobranza = 0 WHERE id_ruta IS NULL AND activo = 1 AND saldo_actual > 0";
                $stmtReset = $this->conexion->prepare($sqlReset);
            } else {
                $sqlReset = "UPDATE creditos SET orden_cobranza = 0 WHERE id_ruta = :id_ruta AND activo = 1 AND saldo_actual > 0";
                $stmtReset = $this->conexion->prepare($sqlReset);
                $stmtReset->bindParam(":id_ruta", $idRuta, PDO::PARAM_INT);
            }
            $stmtReset->execute();
            
            // Asignar nuevos órdenes
            foreach ($orden as $index => $idCredito) {
                $ordenReal = $index + 1;
                if ($idRuta === null) {
                    $sql = "UPDATE creditos 
                            SET orden_cobranza = :orden 
                            WHERE id_credito = :id_credito 
                            AND id_ruta IS NULL 
                            AND activo = 1 
                            AND saldo_actual > 0";
                } else {
                    $sql = "UPDATE creditos 
                            SET orden_cobranza = :orden 
                            WHERE id_credito = :id_credito 
                            AND id_ruta = :id_ruta 
                            AND activo = 1 
                            AND saldo_actual > 0";
                }
                $stmt = $this->conexion->prepare($sql);
                $stmt->bindParam(":orden", $ordenReal, PDO::PARAM_INT);
                $stmt->bindParam(":id_credito", $idCredito, PDO::PARAM_INT);
                if ($idRuta !== null) {
                    $stmt->bindParam(":id_ruta", $idRuta, PDO::PARAM_INT);
                }
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
