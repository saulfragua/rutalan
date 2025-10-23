<?php
session_start(); // Iniciar la sesión
include 'includes/conexion.php'; // Incluir la conexión a la base de datos

// Proceso de Login
if (isset($_POST['login'])) {
    $usuario = $conexion->real_escape_string($_POST['usuario']); // Escapar el nombre de usuario
    $clave = $_POST['clave']; // Obtener la contraseña

    // Consulta SQL para buscar el usuario
    $sql = "SELECT * FROM usuarios WHERE nombre_usuario = ?";
    $stmt = $conexion->prepare($sql);

    // Verificar si prepare() tuvo éxito
    if (!$stmt) {
        die("Error en prepare: " . $conexion->error); // Manejo de errores
    }

    // Vincular parámetros y ejecutar
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();

    // Verificar si se encontró el usuario
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        // Verificar la contraseña
        if (password_verify($clave, $user['clave'])) {
            // Verificar el estado del usuario
            if ($user['estado'] === 'activo') {
                // Iniciar sesión
                $_SESSION['id_usuario'] = $user['id_usuario'];
                $_SESSION['nombre'] = $user['nombre_completo'];
                $_SESSION['rol'] = $user['rol'];
                header("Location: dashboard.php"); // Redirigir al panel de control
                exit();
            } else {
                // Usuario inactivo: redirigir con mensaje de error
                header("Location: login.php?error=inactivo");
                exit();
            }
        } else {
            // Contraseña incorrecta: redirigir con mensaje de error
            header("Location: login.php?error=incorrecto");
            exit();
        }
    } else {
        // Usuario no encontrado: redirigir con mensaje de error
        header("Location: login.php?error=incorrecto");
        exit();
    }
}

// Proceso de Registro
if (isset($_POST['registro'])) {
    $nombre = $conexion->real_escape_string($_POST['nombre']); // Escapar el nombre
    $usuario = $conexion->real_escape_string($_POST['usuario']); // Escapar el nombre de usuario
    $rol = $conexion->real_escape_string($_POST['rol']); // Escapar el rol
    $clave = $_POST['clave']; // Obtener la contraseña
    $confirmar_clave = $_POST['confirmar_clave']; // Obtener la confirmación de la contraseña

    // Validar que las contraseñas coincidan
    if ($clave !== $confirmar_clave) {
        header("Location: registro.php?error=clave");
        exit();
    }

    // Verificar si el usuario ya existe
    $sql_check = "SELECT * FROM usuarios WHERE nombre_usuario = ?";
    $stmt_check = $conexion->prepare($sql_check);

    // Verificar si prepare() tuvo éxito
    if (!$stmt_check) {
        die("Error en prepare: " . $conexion->error); // Manejo de errores
    }

    $stmt_check->bind_param("s", $usuario);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        header("Location: registro.php?error=existente");
        exit();
    }

    // Hash de la contraseña
    $clave_hash = password_hash($clave, PASSWORD_BCRYPT);

    // Insertar el nuevo usuario
    $sql_insert = "INSERT INTO usuarios (nombre_completo, nombre_usuario, rol, clave, estado) 
                   VALUES (?, ?, ?, ?, 'activo')"; // Por defecto, el estado es 'activo'
    $stmt_insert = $conexion->prepare($sql_insert);

    // Verificar si prepare() tuvo éxito
    if (!$stmt_insert) {
        die("Error en prepare: " . $conexion->error); // Manejo de errores
    }

    $stmt_insert->bind_param("ssss", $nombre, $usuario, $rol, $clave_hash);

    // Ejecutar la inserción
    if ($stmt_insert->execute()) {
        header("Location: registro.php?exito=1");
    } else {
        header("Location: registro.php?error=general");
    }
    exit();
}
?>