<?php
include 'includes/header.php';

// Función para hacer requests a la API via PHP (evita CORS)
function makeApiRequest($endpoint, $method = 'GET', $data = null) {
    $apiBaseUrl = "http://localhost:3000";
    $url = $apiBaseUrl . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($data))
            ]);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'data' => json_decode($response, true),
        'code' => $httpCode
    ];
}

// Procesar logout si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    $result = makeApiRequest('/api/logout', 'POST');
    
    if ($result['success']) {
        $logoutMessage = "✅ " . $result['data']['message'];
    } else {
        $logoutMessage = "❌ Error: " . ($result['data']['error'] ?? 'Error desconocido');
    }
}

// Obtener QR
$qrResult = makeApiRequest('/api/qr', 'GET');
$data = $qrResult['data'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escanear QR WhatsApp</title>
      <!-- Solo cargar manifest si existe -->
    <?php if (file_exists('manifest.json')): ?>
    <link rel="manifest" href="manifest.json">
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; }
        .btn { padding: 10px 20px; margin: 10px; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; }
        .btn-danger { background-color: #dc3545; }
        .btn-secondary { background-color: #6c757d; }
    </style>
</head>
<body>
    <h2>📲 Escanea este QR con tu WhatsApp</h2>

    <?php if (isset($logoutMessage)): ?>
        <div style="padding: 10px; background: #d4edda; color: #155724; margin: 10px;">
            <?php echo $logoutMessage; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($data['qr'])): ?>
        <?php if (strpos($data['qr'], 'data:image') === 0): ?>
            <img src="<?php echo $data['qr']; ?>" alt="QR de WhatsApp">
        <?php else: ?>
            <div id="qrcode"></div>
            <script>
                const qrText = "<?php echo $data['qr']; ?>";
                if (qrText) {
                    QRCode.toCanvas(document.getElementById('qrcode'), qrText, { width: 300 });
                }
            </script>
        <?php endif; ?>
        <p>Abre WhatsApp > Dispositivos vinculados > Escanear código QR</p>
    <?php else: ?>
        <p style="color: green; font-weight: bold;">
            <?php echo $data['message'] ?? '✅ WhatsApp ya está conectado'; ?>
        </p>
    <?php endif; ?>

    <!-- Formulario para logout (evita CORS) -->
    <form method="POST" style="display: inline;">
        <button type="submit" name="logout" value="1" class="btn btn-danger" 
                onclick="return confirm('¿Estás seguro de cerrar sesión?')">
            <i class="bi bi-arrow-left-circle"></i> Cerrar Sesión WhatsApp
        </button>
    </form>

    <a href="reportes.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left-circle"></i> Volver a Reportes
    </a>
</body>
</html>
<?php include 'includes/footer.php'; ?>