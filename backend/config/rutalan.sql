-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 28-01-2026 a las 03:21:55
-- Versión del servidor: 8.0.43
-- Versión de PHP: 8.2.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `rutalan`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `adelantos`
--

CREATE TABLE `adelantos` (
  `id_adelanto` int NOT NULL,
  `id_usuario` int NOT NULL,
  `id_caja` int DEFAULT NULL,
  `id_ruta` int NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_adelanto` date NOT NULL,
  `hora_adelanto` time NOT NULL,
  `medio_entrega` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `tipo` enum('ingreso','egreso') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cajas`
--

CREATE TABLE `cajas` (
  `id_caja` int NOT NULL,
  `id_usuario` int NOT NULL,
  `id_ruta` int DEFAULT NULL,
  `nombre_caja` varchar(50) COLLATE utf8mb4_spanish_ci NOT NULL,
  `saldo_inicial` decimal(10,2) NOT NULL DEFAULT '0.00',
  `saldo_final` decimal(10,2) DEFAULT NULL,
  `fecha_apertura` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_cierre` datetime DEFAULT NULL,
  `estado` enum('ABIERTA','CERRADA') COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'ABIERTA'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `caja_ruta`
--

CREATE TABLE `caja_ruta` (
  `id_caja` int NOT NULL,
  `id_ruta` int NOT NULL,
  `fecha_asignacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `claves_cobrador`
--

CREATE TABLE `claves_cobrador` (
  `id_clave` int NOT NULL,
  `id_usuario` int NOT NULL,
  `clave` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `fecha` date NOT NULL,
  `activa` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id_cliente` int NOT NULL,
  `documento` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `nombres` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `apellidos` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `direccion` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `telefono` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `telefono2` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `id_ruta` int DEFAULT NULL,
  `id_usuario` int DEFAULT NULL,
  `orden_cobranza` int DEFAULT '0',
  `activo` tinyint(1) DEFAULT '1',
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_cancelacion` date DEFAULT NULL,
  `foto_cliente` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `foto_cedula_frontal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `foto_cedula_atras` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `id_fiador` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `creditos`
--

CREATE TABLE `creditos` (
  `id_credito` int NOT NULL,
  `id_cliente` int NOT NULL,
  `fecha_toma_credito` date NOT NULL,
  `hora_toma_credito` time DEFAULT NULL,
  `monto_credito` decimal(10,2) NOT NULL,
  `cuotas` int NOT NULL,
  `tasa_interes` decimal(5,2) NOT NULL,
  `frecuencia_pago` enum('mensual','quincenal','semanal','diario') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `seguro` decimal(10,2) DEFAULT '0.00',
  `saldo_actual` decimal(10,2) NOT NULL,
  `tipo_credito` enum('comun','refinanciado','refinanciado_por_sistema') COLLATE utf8mb4_spanish_ci DEFAULT 'comun',
  `activo` tinyint(1) DEFAULT '1',
  `orden_cobranza` int DEFAULT '0',
  `id_usuario` int DEFAULT NULL,
  `id_ruta` int DEFAULT NULL,
  `id_caja` int DEFAULT NULL,
  `fecha_finaliza_credito` date DEFAULT NULL,
  `estado_credito` enum('activo','cancelado') COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'activo' COMMENT 'Estado del crédito: activo o cancelado manualmente',
  `fecha_cancelacion` date DEFAULT NULL COMMENT 'Fecha en que el crédito fue cancelado manualmente',
  `id_usuario_cancelacion` int DEFAULT NULL COMMENT 'Usuario que canceló el crédito'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fiadores`
--

CREATE TABLE `fiadores` (
  `id_fiador` int NOT NULL,
  `documento` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `nombres` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `apellidos` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `direccion` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `telefono` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `telefono2` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `foto_fiador` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `foto_cedula_frontal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `foto_cedula_atras` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `gastos`
--

CREATE TABLE `gastos` (
  `id_gasto` int NOT NULL,
  `id_ruta` int NOT NULL,
  `id_usuario` int NOT NULL,
  `id_caja` int DEFAULT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_gasto` date NOT NULL,
  `hora_gasto` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `login_history`
--

CREATE TABLE `login_history` (
  `id` int NOT NULL,
  `id_usuario` int NOT NULL,
  `login_time` datetime NOT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_caja`
--

CREATE TABLE `movimientos_caja` (
  `id_movimiento` int NOT NULL,
  `id_caja` int NOT NULL,
  `id_usuario` int NOT NULL,
  `tipo` enum('entrada','salida') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `causal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `metodo_pago` enum('efectivo','transferencia','otro') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'efectivo',
  `observacion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci,
  `fecha_movimiento` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `id_pago` int NOT NULL,
  `id_cliente` int NOT NULL,
  `id_credito` int NOT NULL,
  `fecha_pago` date NOT NULL,
  `hora_pago` time NOT NULL,
  `monto_pagado` decimal(10,2) NOT NULL,
  `monto_excedente` decimal(10,2) DEFAULT '0.00',
  `descuento` decimal(10,2) DEFAULT '0.00',
  `id_plan_pago` int DEFAULT NULL,
  `id_usuario` int NOT NULL,
  `id_caja` int DEFAULT NULL,
  `id_ruta` int DEFAULT NULL,
  `whatsapp_enviado` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Disparadores `pagos`
--
DELIMITER $$
CREATE TRIGGER `after_insert_pago` AFTER INSERT ON `pagos` FOR EACH ROW BEGIN
  UPDATE creditos
  SET saldo_actual = saldo_actual - NEW.monto_pagado
  WHERE id_credito = NEW.id_credito;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `planpagos`
--

CREATE TABLE `planpagos` (
  `id_plan_pago` int NOT NULL,
  `id_credito` int NOT NULL,
  `numero_cuota` int NOT NULL,
  `monto_cuota` decimal(10,2) NOT NULL,
  `monto_restante` decimal(10,2) DEFAULT '0.00',
  `monto_excedente_aplicado` decimal(10,2) DEFAULT '0.00',
  `fecha_pago` date NOT NULL,
  `estado` enum('pendiente','pagada','vencida') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reportes_diarios`
--

CREATE TABLE `reportes_diarios` (
  `id` int NOT NULL,
  `fecha` date NOT NULL,
  `cierre_caja` decimal(10,2) NOT NULL,
  `id_usuario` int NOT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rutas`
--

CREATE TABLE `rutas` (
  `id_ruta` int NOT NULL,
  `nombre_ruta` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `soportes_whatsapp`
--

CREATE TABLE `soportes_whatsapp` (
  `id_soporte` int NOT NULL,
  `tipo` enum('pago','credito','refinanciacion') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `id_referencia` int NOT NULL COMMENT 'ID del pago, crédito o refinanciación',
  `nombre_archivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `ruta_archivo` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `telefono_destino` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `fecha_envio` datetime NOT NULL,
  `estado_envio` enum('pendiente','enviado','error') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT 'pendiente',
  `mensaje_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int NOT NULL,
  `nombre_completo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `nombre_usuario` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `rol` enum('admin','cobrador') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `clave` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_ruta`
--

CREATE TABLE `usuario_ruta` (
  `id_usuario` int NOT NULL,
  `id_ruta` int NOT NULL,
  `fecha_asignacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `adelantos`
--
ALTER TABLE `adelantos`
  ADD PRIMARY KEY (`id_adelanto`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_ruta` (`id_ruta`),
  ADD KEY `fk_adelantos_caja` (`id_caja`);

--
-- Indices de la tabla `cajas`
--
ALTER TABLE `cajas`
  ADD PRIMARY KEY (`id_caja`),
  ADD KEY `fk_cajas_usuario` (`id_usuario`),
  ADD KEY `fk_cajas_ruta` (`id_ruta`);

--
-- Indices de la tabla `caja_ruta`
--
ALTER TABLE `caja_ruta`
  ADD PRIMARY KEY (`id_caja`,`id_ruta`),
  ADD KEY `fk_caja_ruta_ruta` (`id_ruta`);

--
-- Indices de la tabla `claves_cobrador`
--
ALTER TABLE `claves_cobrador`
  ADD PRIMARY KEY (`id_clave`),
  ADD KEY `fk_claves_cobrador_usuario` (`id_usuario`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id_cliente`),
  ADD UNIQUE KEY `documento` (`documento`),
  ADD KEY `id_ruta` (`id_ruta`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `fk_cliente_fiador` (`id_fiador`);

--
-- Indices de la tabla `creditos`
--
ALTER TABLE `creditos`
  ADD PRIMARY KEY (`id_credito`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_ruta` (`id_ruta`),
  ADD KEY `fk_credito_usuario_cancelacion` (`id_usuario_cancelacion`),
  ADD KEY `fk_creditos_caja` (`id_caja`),
  ADD KEY `idx_creditos_orden_cobranza` (`id_ruta`,`orden_cobranza`);

--
-- Indices de la tabla `fiadores`
--
ALTER TABLE `fiadores`
  ADD PRIMARY KEY (`id_fiador`),
  ADD UNIQUE KEY `uk_fiador_documento` (`documento`);

--
-- Indices de la tabla `gastos`
--
ALTER TABLE `gastos`
  ADD PRIMARY KEY (`id_gasto`),
  ADD KEY `id_ruta` (`id_ruta`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `fk_gastos_caja` (`id_caja`);

--
-- Indices de la tabla `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `movimientos_caja`
--
ALTER TABLE `movimientos_caja`
  ADD PRIMARY KEY (`id_movimiento`),
  ADD KEY `fk_movimientos_caja` (`id_caja`),
  ADD KEY `fk_movimientos_usuario` (`id_usuario`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `id_credito` (`id_credito`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_ruta` (`id_ruta`),
  ADD KEY `fk_pagos_caja` (`id_caja`);

--
-- Indices de la tabla `planpagos`
--
ALTER TABLE `planpagos`
  ADD PRIMARY KEY (`id_plan_pago`),
  ADD KEY `id_credito` (`id_credito`);

--
-- Indices de la tabla `reportes_diarios`
--
ALTER TABLE `reportes_diarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `fecha` (`fecha`,`id_usuario`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `rutas`
--
ALTER TABLE `rutas`
  ADD PRIMARY KEY (`id_ruta`),
  ADD UNIQUE KEY `nombre_ruta` (`nombre_ruta`);

--
-- Indices de la tabla `soportes_whatsapp`
--
ALTER TABLE `soportes_whatsapp`
  ADD PRIMARY KEY (`id_soporte`),
  ADD KEY `idx_tipo_referencia` (`tipo`,`id_referencia`),
  ADD KEY `idx_fecha_envio` (`fecha_envio`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `nombre_usuario` (`nombre_usuario`);

--
-- Indices de la tabla `usuario_ruta`
--
ALTER TABLE `usuario_ruta`
  ADD PRIMARY KEY (`id_usuario`,`id_ruta`),
  ADD KEY `fk_usuario_ruta_ruta` (`id_ruta`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `adelantos`
--
ALTER TABLE `adelantos`
  MODIFY `id_adelanto` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cajas`
--
ALTER TABLE `cajas`
  MODIFY `id_caja` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `claves_cobrador`
--
ALTER TABLE `claves_cobrador`
  MODIFY `id_clave` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id_cliente` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `creditos`
--
ALTER TABLE `creditos`
  MODIFY `id_credito` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fiadores`
--
ALTER TABLE `fiadores`
  MODIFY `id_fiador` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `gastos`
--
ALTER TABLE `gastos`
  MODIFY `id_gasto` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `movimientos_caja`
--
ALTER TABLE `movimientos_caja`
  MODIFY `id_movimiento` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id_pago` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `planpagos`
--
ALTER TABLE `planpagos`
  MODIFY `id_plan_pago` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reportes_diarios`
--
ALTER TABLE `reportes_diarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rutas`
--
ALTER TABLE `rutas`
  MODIFY `id_ruta` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `soportes_whatsapp`
--
ALTER TABLE `soportes_whatsapp`
  MODIFY `id_soporte` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `adelantos`
--
ALTER TABLE `adelantos`
  ADD CONSTRAINT `adelantos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `adelantos_ibfk_2` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`),
  ADD CONSTRAINT `fk_adelantos_caja` FOREIGN KEY (`id_caja`) REFERENCES `cajas` (`id_caja`);

--
-- Filtros para la tabla `cajas`
--
ALTER TABLE `cajas`
  ADD CONSTRAINT `fk_cajas_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`),
  ADD CONSTRAINT `fk_cajas_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `caja_ruta`
--
ALTER TABLE `caja_ruta`
  ADD CONSTRAINT `fk_caja_ruta_caja` FOREIGN KEY (`id_caja`) REFERENCES `cajas` (`id_caja`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_caja_ruta_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`) ON DELETE CASCADE;

--
-- Filtros para la tabla `claves_cobrador`
--
ALTER TABLE `claves_cobrador`
  ADD CONSTRAINT `fk_claves_cobrador_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `clientes_ibfk_1` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`) ON DELETE SET NULL,
  ADD CONSTRAINT `clientes_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_cliente_fiador` FOREIGN KEY (`id_fiador`) REFERENCES `fiadores` (`id_fiador`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `creditos`
--
ALTER TABLE `creditos`
  ADD CONSTRAINT `creditos_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`),
  ADD CONSTRAINT `creditos_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `creditos_ibfk_3` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`),
  ADD CONSTRAINT `fk_credito_usuario_cancelacion` FOREIGN KEY (`id_usuario_cancelacion`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_creditos_caja` FOREIGN KEY (`id_caja`) REFERENCES `cajas` (`id_caja`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `gastos`
--
ALTER TABLE `gastos`
  ADD CONSTRAINT `fk_gastos_caja` FOREIGN KEY (`id_caja`) REFERENCES `cajas` (`id_caja`),
  ADD CONSTRAINT `gastos_ibfk_1` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`),
  ADD CONSTRAINT `gastos_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `login_history`
--
ALTER TABLE `login_history`
  ADD CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `movimientos_caja`
--
ALTER TABLE `movimientos_caja`
  ADD CONSTRAINT `fk_movimientos_caja` FOREIGN KEY (`id_caja`) REFERENCES `cajas` (`id_caja`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_movimientos_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `fk_pagos_caja` FOREIGN KEY (`id_caja`) REFERENCES `cajas` (`id_caja`),
  ADD CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`id_credito`) REFERENCES `creditos` (`id_credito`) ON DELETE CASCADE,
  ADD CONSTRAINT `pagos_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `pagos_ibfk_3` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`);

--
-- Filtros para la tabla `planpagos`
--
ALTER TABLE `planpagos`
  ADD CONSTRAINT `planpagos_ibfk_1` FOREIGN KEY (`id_credito`) REFERENCES `creditos` (`id_credito`);

--
-- Filtros para la tabla `reportes_diarios`
--
ALTER TABLE `reportes_diarios`
  ADD CONSTRAINT `reportes_diarios_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `usuario_ruta`
--
ALTER TABLE `usuario_ruta`
  ADD CONSTRAINT `fk_usuario_ruta_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_usuario_ruta_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
