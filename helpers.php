<?php
// Obtener ruta actual del usuario
function getUserRuta($pdo, $usuario_id) {
    $stmt = $pdo->prepare("SELECT r.* FROM rutas r JOIN usuarios u ON r.id_ruta = u.id_ruta WHERE u.id_usuario = ?");
    $stmt->execute([$usuario_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Validar si el usuario puede modificar un cliente
function canEditCliente($pdo, $cliente_id, $usuario_id) {
    $stmt = $pdo->prepare("SELECT id_ruta FROM clientes WHERE id_cliente = ?");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch();
    
    $userRuta = getUserRuta($pdo, $usuario_id);
    
    return $userRuta['id_ruta'] == $cliente['id_ruta'] || currentUser($pdo)['rol'] === 'admin';
}
?>