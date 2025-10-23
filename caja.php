<?php
include 'includes/header.php';
include 'includes/conexion1.php';

// Establecer la zona horaria
date_default_timezone_set('America/Bogota');

// Configurar el idioma local para fechas en español
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es');

// Inicializar variables
$totales = [
    'creditos_cobrados' => 0,
    'clientes_cobrados' => 0,
    'prestamos_realizados' => 0,
    'creditos_nuevos' => 0,
    'clientes_nuevos' => 0,
    'seguros_cobrados' => 0,
    'gastos_ruta' => 0,
    'adelantos_ingresos' => 0,
    'adelantos_egresos' => 0,
    'descuentos_creditos' => 0,
    'cierre_actual' => 0, // Cierre del día actual (sin caja anterior)
    'caja_anterior' => 0,
    'cierre_caja' => 0, // Total que se guardará (caja anterior + cierre actual)
    'usuario_caja_anterior' => '' // Nombre del usuario que registró la caja anterior
];

// Inicializar arrays para almacenar los registros detallados
$registros_adelantos_ingresos = [];
$registros_adelantos_egresos = [];

// Obtener listas de usuarios y rutas para los filtros
try {
    $usuarios = $conn->query("SELECT id_usuario, nombre_completo FROM usuarios")->fetchAll(PDO::FETCH_ASSOC);
    $rutas = $conn->query("SELECT id_ruta, nombre_ruta FROM rutas")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al obtener datos de usuarios o rutas: " . $e->getMessage());
}

// Procesar el guardado del cierre de caja
if (isset($_POST['guardar_cierre'])) {
    $fecha = $_POST['fecha_cierre'];
    $monto = $_POST['monto_cierre'];
    $id_usuario = $_POST['id_usuario_cierre'];
    $observaciones = $_POST['observaciones'] ?? '';
    
    try {
        $stmt = $conn->prepare("INSERT INTO reportes_diarios (fecha, cierre_caja, id_usuario, observaciones) 
                               VALUES (:fecha, :cierre_caja, :id_usuario, :observaciones)");
        $stmt->execute([
            'fecha' => $fecha,
            'cierre_caja' => $monto,
            'id_usuario' => $id_usuario,
            'observaciones' => $observaciones
        ]);
        
        echo "<div class='alert alert-success'>Cierre de caja guardado correctamente</div>";
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Error al guardar el cierre de caja: " . $e->getMessage() . "</div>";
    }
}

// Obtener fecha actual del servidor
$fechaActual = date('Y-m-d');
$fechaAnterior = date('Y-m-d', strtotime('-1 day'));

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['guardar_cierre'])) {
    $fechaInicio = $_POST['fecha_inicio'] ?? $fechaActual;
    $fechaFin = $_POST['fecha_fin'] ?? $fechaActual;
    $fechaCajaAnterior = $_POST['fecha_caja_anterior'] ?? $fechaAnterior;
    $idUsuario = $_POST['id_usuario'] ?? '';
    $idRuta = $_POST['id_ruta'] ?? '';

    // Validar fechas
    if (empty($fechaInicio) || empty($fechaFin)) {
        echo "<div class='alert alert-danger'>Las fechas son obligatorias.</div>";
    } elseif (strtotime($fechaInicio) > strtotime($fechaFin)) {
        echo "<div class='alert alert-danger'>La fecha de inicio no puede ser mayor que la fecha de fin.</div>";
    } else {
        try {
            // Obtener caja del día anterior usando la fecha seleccionada por el usuario
            $stmt = $conn->prepare("SELECT r.cierre_caja, u.nombre_completo 
                                  FROM reportes_diarios r
                                  LEFT JOIN usuarios u ON r.id_usuario = u.id_usuario
                                  WHERE r.fecha = :fecha 
                                  AND (:id_usuario IS NULL OR r.id_usuario = :id_usuario)
                                  ORDER BY r.id DESC LIMIT 1");
            $stmt->execute(['fecha' => $fechaCajaAnterior, 'id_usuario' => $idUsuario ?: null]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $totales['caja_anterior'] = $resultado['cierre_caja'] ?? 0;
            $totales['usuario_caja_anterior'] = $resultado['nombre_completo'] ?? 'No registrado';

            // Parámetros comunes para las consultas
            $params = [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'id_usuario' => $idUsuario ?: null,
                'id_ruta' => $idRuta ?: null
            ];

            // Consultas para obtener los totales
            $consultas = [
                'creditos_cobrados' => "SELECT SUM(p.monto_pagado) AS total 
                                        FROM pagos p 
                                        JOIN creditos cr ON p.id_credito = cr.id_credito 
                                        WHERE p.fecha_pago BETWEEN :fecha_inicio AND :fecha_fin 
                                        AND (:id_usuario IS NULL OR cr.id_usuario = :id_usuario) 
                                        AND (:id_ruta IS NULL OR cr.id_ruta = :id_ruta)",
                'clientes_cobrados' => "SELECT COUNT(DISTINCT cr.id_cliente) AS total 
                                         FROM pagos p 
                                         JOIN creditos cr ON p.id_credito = cr.id_credito 
                                         WHERE p.fecha_pago BETWEEN :fecha_inicio AND :fecha_fin 
                                         AND (:id_usuario IS NULL OR cr.id_usuario = :id_usuario) 
                                         AND (:id_ruta IS NULL OR cr.id_ruta = :id_ruta)",
                'prestamos_realizados' => "SELECT SUM(monto_credito) AS total 
                                           FROM creditos 
                                           WHERE fecha_toma_credito BETWEEN :fecha_inicio AND :fecha_fin 
                                           AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                                           AND (:id_ruta IS NULL OR id_ruta = :id_ruta)",
                'creditos_nuevos' => "SELECT COUNT(*) AS total 
                                      FROM creditos 
                                      WHERE fecha_toma_credito BETWEEN :fecha_inicio AND :fecha_fin 
                                      AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                                      AND (:id_ruta IS NULL OR id_ruta = :id_ruta)",
                'clientes_nuevos' => "SELECT COUNT(*) AS total 
                                     FROM clientes 
                                     WHERE fecha_registro BETWEEN :fecha_inicio AND :fecha_fin 
                                     AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                                     AND (:id_ruta IS NULL OR id_ruta = :id_ruta)",
                'seguros_cobrados' => "SELECT SUM(seguro) AS total 
                                       FROM creditos 
                                       WHERE fecha_toma_credito BETWEEN :fecha_inicio AND :fecha_fin 
                                       AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                                       AND (:id_ruta IS NULL OR id_ruta = :id_ruta)",
                'gastos_ruta' => "SELECT SUM(monto) AS total 
                                  FROM gastos 
                                  WHERE fecha_gasto BETWEEN :fecha_inicio AND :fecha_fin 
                                  AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                                  AND (:id_ruta IS NULL OR id_ruta = :id_ruta)",
                'adelantos_ingresos' => "SELECT SUM(monto) AS total 
                                         FROM adelantos 
                                         WHERE fecha_adelanto BETWEEN :fecha_inicio AND :fecha_fin 
                                         AND tipo = 'Ingreso' 
                                         AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                                         AND (:id_ruta IS NULL OR id_ruta = :id_ruta)",
                'adelantos_egresos' => "SELECT SUM(monto) AS total 
                                        FROM adelantos 
                                        WHERE fecha_adelanto BETWEEN :fecha_inicio AND :fecha_fin 
                                        AND tipo = 'Egreso' 
                                        AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                                        AND (:id_ruta IS NULL OR id_ruta = :id_ruta)",
                'descuentos_creditos' => "SELECT SUM(descuento) AS total 
                                          FROM pagos 
                                          WHERE fecha_pago BETWEEN :fecha_inicio AND :fecha_fin 
                                          AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                                          AND (:id_ruta IS NULL OR id_ruta = :id_ruta)",
                'creditos_cancelados' => "SELECT COUNT(*) AS total 
                                         FROM creditos 
                                         WHERE saldo_actual = 0 
                                         AND fecha_toma_credito BETWEEN :fecha_inicio AND :fecha_fin 
                                         AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                                         AND (:id_ruta IS NULL OR id_ruta = :id_ruta)"
            ];

            // Ejecutar las consultas y almacenar los resultados
            foreach ($consultas as $key => $query) {
                $stmt = $conn->prepare($query);
                $stmt->execute($params);
                $totales[$key] = $stmt->fetchColumn() ?? 0;
            }

            // Obtener registros detallados de adelantos (ingresos)
            $stmt = $conn->prepare("SELECT * FROM adelantos 
                                  WHERE fecha_adelanto BETWEEN :fecha_inicio AND :fecha_fin 
                                  AND tipo = 'Ingreso' 
                                  AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                                  AND (:id_ruta IS NULL OR id_ruta = :id_ruta)
                                  ORDER BY fecha_adelanto DESC LIMIT 5");
            $stmt->execute($params);
            $registros_adelantos_ingresos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener registros detallados de adelantos (egresos)
            $stmt = $conn->prepare("SELECT * FROM adelantos 
                                  WHERE fecha_adelanto BETWEEN :fecha_inicio AND :fecha_fin 
                                  AND tipo = 'Egreso' 
                                  AND (:id_usuario IS NULL OR id_usuario = :id_usuario) 
                                  AND (:id_ruta IS NULL OR id_ruta = :id_ruta)
                                  ORDER BY fecha_adelanto DESC LIMIT 5");
            $stmt->execute($params);
            $registros_adelantos_egresos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular el cierre del día actual (sin caja anterior)
            $totales['cierre_actual'] = ($totales['creditos_cobrados'] + ($totales['seguros_cobrados'] * 0.7)) 
                                     - $totales['gastos_ruta'] 
                                     + $totales['adelantos_ingresos'] 
                                     - $totales['adelantos_egresos'] 
                                     - $totales['prestamos_realizados']
                                     - $totales['descuentos_creditos'];

            // Calcular el cierre total (caja anterior + cierre actual)
            $totales['cierre_caja'] = $totales['caja_anterior'] + $totales['cierre_actual'];

        } catch (PDOException $e) {
            echo "<div class='alert alert-danger'>Error en las consultas: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Colores personalizados para las tarjetas */
        .card-primary {
            background-color: #e3f2fd;
            border-color: #90caf9;
        }
        .card-secondary {
            background-color: #f3e5f5;
            border-color: #ce93d8;
        }
        .card-success {
            background-color: #e8f5e9;
            border-color: #a5d6a7;
        }
        .card-info {
            background-color: #e0f7fa;
            border-color: #80deea;
        }
        .card-warning {
            background-color: #fff3e0;
            border-color: #ffcc80;
        }
        .card-danger {
            background-color: #ffebee;
            border-color: #ef9a9a;
        }
        .card-dark {
            background-color: #eceff1;
            border-color: #b0bec5;
        }
        .card-highlight {
            background-color: #fff3b0;
            border-color: #ffd54f;
            border-width: 2px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .card-descuentos {
            background-color: #ffccbc;
            border-color: #ffab91;
        }
        .card-caja-anterior {
            background-color: #d7ccc8;
            border-color: #a1887f;
        }
        /* Estilos para los registros */
        .registro-item {
            font-size: 0.8rem;
            padding: 0.25rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        .registro-item:last-child {
            border-bottom: none;
        }
        .registros-container {
            max-height: 150px;
            overflow-y: auto;
            margin-top: 10px;
            background-color: rgba(255,255,255,0.5);
            border-radius: 5px;
            padding: 5px;
        }
        .registro-monto {
            font-weight: bold;
        }
        .registro-fecha {
            color: #6c757d;
            font-size: 0.75rem;
        }
        .registro-desc {
            color: #495057;
            font-size: 0.7rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .modal-content {
            border-radius: 15px;
        }
        .cierre-desglose {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .cierre-actual {
            font-size: 1.2rem;
            color:rgb(255, 0, 0);
        }
        .usuario-caja-anterior {
            font-size: 0.8rem;
            color: #495057;
            font-weight: bold;
        }

    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center">Reportes de Créditos</h1>
        <form method="POST" action="">
            <div class="row mb-3">
                <div class="col">
                    <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                           value="<?= htmlspecialchars($_POST['fecha_inicio'] ?? $fechaActual) ?>">
                </div>
                <div class="col">
                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                           value="<?= htmlspecialchars($_POST['fecha_fin'] ?? $fechaActual) ?>">
                </div>
                <div class="col">
                    <label for="fecha_caja_anterior" class="form-label">Caja Anterior (Fecha)</label>
                    <input type="date" class="form-control" id="fecha_caja_anterior" name="fecha_caja_anterior" 
                           value="<?= htmlspecialchars($_POST['fecha_caja_anterior'] ?? $fechaAnterior) ?>">
                </div>
            </div>
            <div class="row mb-3">
                <div class="col">
                    <label for="id_usuario" class="form-label">Usuario</label>
                    <select class="form-select" id="id_usuario" name="id_usuario">
                        <option value="">Todos los usuarios</option>
                        <?php foreach ($usuarios as $usuario): ?>
                            <option value="<?= $usuario['id_usuario'] ?>" 
                                <?= (isset($_POST['id_usuario'])) && $_POST['id_usuario'] == $usuario['id_usuario'] ? 'selected' : '' ?>>
                                <?= $usuario['nombre_completo'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Generar Reportes</button>
        </form>
        <br>
        <!-- Botón para volver a reportes -->
        <a href="reportes.php" class="btn btn-secondary mb-3">
            <i class="bi bi-arrow-left-circle"></i> Volver a Reportes
        </a>
        <!-- Resultados -->
        <div class="mt-5">
            <h2 class="mb-4"><i class="bi bi-graph-up"></i> Resultados</h2>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <!-- Tarjeta de Caja Anterior -->
                <div class="col">
                    <div class="card h-100 card-caja-anterior">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-arrow-left-circle"></i> Caja Anterior</h5>
                            <p class="card-text fs-4"><?= number_format($totales['caja_anterior'], 2) ?></p>
                            <small class="text-muted">Cierre de caja del día <?= date('d/m/Y', strtotime($fechaCajaAnterior ?? $fechaAnterior)) ?></small>
                            <div class="usuario-caja-anterior">Cobrador: <?= $totales['usuario_caja_anterior'] ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Tarjetas de resultados -->
                <?php
                $tarjetas = [
                    'creditos_cobrados' => ['icon' => 'bi-cash-coin', 'title' => 'Créditos Cobrados', 'color' => 'primary'],
                    'clientes_cobrados' => ['icon' => 'bi-people-fill', 'title' => 'Clientes Cobrados', 'color' => 'secondary'],
                    'prestamos_realizados' => ['icon' => 'bi-bank', 'title' => 'Préstamos Realizados', 'color' => 'success'],
                    'creditos_nuevos' => ['icon' => 'bi-file-earmark-plus', 'title' => 'Créditos Nuevos', 'color' => 'info'],
                    'clientes_nuevos' => ['icon' => 'bi-person-plus', 'title' => 'Clientes Nuevos', 'color' => 'warning'],
                    'seguros_cobrados' => ['icon' => 'bi-shield-check', 'title' => 'Seguros Cobrados', 'color' => 'dark'],
                    'gastos_ruta' => ['icon' => 'bi-geo-alt', 'title' => 'Gastos por Ruta', 'color' => 'primary'],
                    'adelantos_ingresos' => ['icon' => 'bi-wallet', 'title' => 'Adelantos (Ingresos)', 'color' => 'secondary', 'show_registros' => true],
                    'adelantos_egresos' => ['icon' => 'bi-wallet2', 'title' => 'Retiros (Egresos)', 'color' => 'danger', 'show_registros' => true],
                    'descuentos_creditos' => ['icon' => 'bi-percent', 'title' => 'Descuentos de Créditos', 'color' => 'descuentos'],
                    'cierre_caja' => ['icon' => 'bi-cash-stack', 'title' => 'Cierre de Caja', 'color' => 'highlight', 'highlight' => true]
                ];

                foreach ($tarjetas as $key => $tarjeta) {
                    $valor = number_format($totales[$key], 2);
                    $color = $tarjeta['color'];
                    $highlight = $tarjeta['highlight'] ?? false;
                    $bgClass = $highlight ? "card-highlight" : "card-$color";
                    $showRegistros = $tarjeta['show_registros'] ?? false;
                    
                    echo "<div class='col'>
                            <div class='card h-100 $bgClass'>
                                <div class='card-body'>
                                    <h5 class='card-title'><i class='bi {$tarjeta['icon']}'></i> {$tarjeta['title']}</h5>
                                    <p class='card-text fs-4'>$valor</p>";
                    
                    if ($key === 'seguros_cobrados') {
                        echo "<small class='text-muted'>30% para el usuario: " . number_format($totales[$key] * 0.3, 2) . "</small>";
                    }
                    
                    // Mostrar desglose del cierre de caja
                    if ($key === 'cierre_caja') {
                        echo "<div class='cierre-desglose'>
                                <div>Caja anterior: " . number_format($totales['caja_anterior'], 2) . "</div>
                                <div class='cierre-actual'>Cierre del día: " . number_format($totales['cierre_actual'], 2) . "</div>
                              </div>";
                    }
                    
                    // Mostrar registros de adelantos si corresponde
                    if ($showRegistros) {
                        $registros = ($key === 'adelantos_ingresos') ? $registros_adelantos_ingresos : $registros_adelantos_egresos;
                        
                        if (!empty($registros)) {
                            echo "<div class='registros-container'>";
                            foreach ($registros as $registro) {
                                $fecha = date('d/m/Y', strtotime($registro['fecha_adelanto']));
                                $monto = number_format($registro['monto'], 2);
                                $desc = htmlspecialchars($registro['descripcion'] ?? 'Sin descripción', ENT_QUOTES, 'UTF-8');
                                
                                echo "<div class='registro-item'>
                                        <div class='d-flex justify-content-between'>
                                            <span class='registro-monto'>$monto</span>
                                            <span class='registro-fecha'>$fecha</span>
                                        </div>
                                        <div class='registro-desc'>$desc</div>
                                      </div>";
                            }
                            echo "</div>";
                        } else {
                            echo "<div class='registros-container text-muted'>No hay registros</div>";
                        }
                    }
                    
                    // Botón para guardar cierre de caja solo en la tarjeta correspondiente
                    if ($key === 'cierre_caja') {
                        echo '<button class="btn btn-sm btn-success mt-2" data-bs-toggle="modal" data-bs-target="#modalCierreCaja">
                                <i class="bi bi-save"></i> Guardar Cierre
                              </button>';
                    }
                    
                    echo "</div></div></div>";
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Modal para guardar cierre de caja -->
    <div class="modal fade" id="modalCierreCaja" tabindex="-1" aria-labelledby="modalCierreCajaLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalCierreCajaLabel">Guardar Cierre de Caja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="guardar_cierre" value="1">
                        <div class="mb-3">
                            <label for="fecha_cierre" class="form-label">Fecha</label>
                            <input type="date" class="form-control" id="fecha_cierre" name="fecha_cierre" 
                                   value="<?= htmlspecialchars($_POST['fecha_inicio'] ?? $fechaActual) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="monto_cierre" class="form-label">Monto</label>
                            <input type="number" step="0.01" class="form-control" id="monto_cierre" 
                                   name="monto_cierre" value="<?= $totales['cierre_caja'] ?>" required>
                            <small class="text-muted">Total: Caja anterior (<?= number_format($totales['caja_anterior'], 2) ?>) + Cierre del día (<?= number_format($totales['cierre_actual'], 2) ?>)</small>
                        </div>
                        <div class="mb-3">
                            <label for="id_usuario_cierre" class="form-label">Usuario</label>
                            <select class="form-select" id="id_usuario_cierre" name="id_usuario_cierre" required>
                                <option value="">Seleccione un usuario</option>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?= $usuario['id_usuario'] ?>" 
                                        <?= (isset($_POST['id_usuario'])) && $_POST['id_usuario'] == $usuario['id_usuario'] ? 'selected' : '' ?>>
                                        <?= $usuario['nombre_completo'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="observaciones" class="form-label">Observaciones</label>
                            <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include 'includes/footer.php'; ?>