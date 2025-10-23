<?php
include 'includes/header.php';
include 'includes/conexion.php';

// Establecer la zona horaria
date_default_timezone_set('America/Bogota');

// Configurar el idioma local para fechas en español
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es');

session_start();
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'], ['admin', 'cobrador'])) {
    header("Location: login.php");
    exit();
}

// Obtener el ID del crédito desde la URL
$id_credito = $_GET['id'] ?? null;
if (!$id_credito) {
    header("Location: creditos.php");
    exit();
}

// Obtener datos del crédito y del cliente
$sql_credito = "SELECT c.nombres, c.apellidos, cr.* 
                FROM creditos cr 
                JOIN clientes c ON cr.id_cliente = c.id_cliente 
                WHERE cr.id_credito = ?";
$stmt_credito = $conexion->prepare($sql_credito);
$stmt_credito->bind_param("i", $id_credito);
$stmt_credito->execute();
$credito = $stmt_credito->get_result()->fetch_assoc();

// Obtener el plan de pagos desde la tabla PlanPagos
$sql_plan_pagos = "SELECT * FROM planpagos WHERE id_credito = ? ORDER BY numero_cuota ASC";
$stmt_plan_pagos = $conexion->prepare($sql_plan_pagos);
$stmt_plan_pagos->bind_param("i", $id_credito);
$stmt_plan_pagos->execute();
$plan_pagos = $stmt_plan_pagos->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de Cuotas</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Buttons CSS -->
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center mb-4">Estado de Cuotas</h2>
        
        <!-- Mostrar información del crédito y del cliente -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Información del Crédito</h5>
                <p class="card-text"><strong>Cliente:</strong> <?= $credito['nombres'] ?> <?= $credito['apellidos'] ?></p>
                <p class="card-text"><strong>Número de Crédito:</strong> <?= $credito['id_credito'] ?></p>
                <p class="card-text"><strong>Monto Total:</strong> $<?= number_format($credito['saldo_actual'], 2) ?></p>
                <p class="card-text"><strong>Fecha de Inicio:</strong> <?= date('d/m/Y', strtotime($credito['fecha_toma_credito'])) ?></p>
                <p class="card-text"><strong>Fecha de Fin:</strong> <?= date('d/m/Y', strtotime($credito['fecha_finaliza_credito'])) ?></p>
                <p class="card-text"><strong>Plazo:</strong> <?= $credito['cuotas'] ?> días</p>
            </div>
        </div>

        <!-- Tabla de Plan de Pagos -->
        <div class="card">
            <div class="card-body">
                <table id="tablaPlanPagos" class="table table-striped">
                    <thead>
                        <tr>
                            <th># Cuota</th>
                            <th>Fecha de Pago</th>
                            <th>Monto Cuota</th>
                            <th>Monto Restante</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($plan_pagos)): ?>
                            <?php foreach ($plan_pagos as $cuota): ?>
                                <tr>
                                    <td><?= $cuota['numero_cuota'] ?></td>
                                    <td><?= date('d/m/Y', strtotime($cuota['fecha_pago'])) ?></td>
                                    <td>$<?= number_format($cuota['monto_cuota'], 2) ?></td>
                                    <td>$<?= number_format($cuota['monto_restante'], 2) ?></td>
                                    <td>
                                        <?php if ($cuota['estado'] === 'pagada'): ?>
                                            <span class="badge bg-success">Pagada</span>
                                        <?php elseif ($cuota['estado'] === 'vencida'): ?>
                                            <span class="badge bg-danger">Vencida</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No hay cuotas registradas para este crédito.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Botones de exportación -->
        <div class="mt-4">
            <a href="creditos.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left-circle"></i> Volver a Creditos
            </a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <!-- Buttons JS -->
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <!-- PDFMake -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <!-- JSZip -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <!-- Excel export -->
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

    <script>
        $(document).ready(function() {
            // Inicializar DataTable
            $('#tablaPlanPagos').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'pdf',
                        text: '<i class="bi bi-file-earmark-pdf"></i> Exportar a PDF',
                        className: 'btn btn-danger',
                        title: 'Estado de Cuotas - <?= $credito['nombres'] ?> <?= $credito['apellidos'] ?>',
                        messageTop: 'Información del Crédito:\n' +
                                   'Cliente: <?= $credito['nombres'] ?> <?= $credito['apellidos'] ?>\n' +
                                   'Número de Crédito: <?= $credito['id_credito'] ?>\n' +
                                   'Monto Total: $<?= number_format($credito['saldo_actual'], 2) ?>\n' +
                                   'Fecha de Inicio: <?= date('d/m/Y', strtotime($credito['fecha_toma_credito'])) ?>\n' +
                                   'Fecha de Fin: <?= date('d/m/Y', strtotime($credito['fecha_finaliza_credito'])) ?>\n' +
                                   'Plazo: <?= $credito['cuotas'] ?> días',
                        customize: function (doc) {
                            doc.content[1].margin = [0, 10, 0, 10]; // Ajustar márgenes
                        }
                    },
                    {
                        extend: 'excel',
                        text: '<i class="bi bi-file-earmark-excel"></i> Exportar a Excel',
                        className: 'btn btn-success',
                        title: 'Estado de Cuotas - <?= $credito['nombres'] ?> <?= $credito['apellidos'] ?>',
                        messageTop: 'Información del Crédito:\n' +
                                   'Cliente: <?= $credito['nombres'] ?> <?= $credito['apellidos'] ?>\n' +
                                   'Número de Crédito: <?= $credito['id_credito'] ?>\n' +
                                   'Monto Total: $<?= number_format($credito['saldo_actual'], 2) ?>\n' +
                                   'Fecha de Inicio: <?= date('d/m/Y', strtotime($credito['fecha_toma_credito'])) ?>\n' +
                                   'Fecha de Fin: <?= date('d/m/Y', strtotime($credito['fecha_finaliza_credito'])) ?>\n' +
                                   'Plazo: <?= $credito['cuotas'] ?> días'
                    }
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                }
            });
        });
    </script>
</body>
</html>

<?php include 'includes/footer.php'; ?>