<?php 
include 'includes/header.php';

// Verificar si el usuario ya está logueado
if (isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}
?>

<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card shadow-lg" style="width: 100%; max-width: 400px;">
        <div class="card-header bg-primary text-white text-center">
            <h4 class="mb-0"><i class="bi bi-person-circle"></i> Iniciar Sesión</h4>
        </div>
        <div class="card-body">
            <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-danger text-center">
                    <i class="bi bi-exclamation-triangle"></i>
                    <?php
                    $error = htmlspecialchars($_GET['error']);
                    if ($error === 'incorrecto') {
                        echo "Usuario o contraseña incorrectos.";
                    } elseif ($error === 'inactivo') {
                        echo "Tu cuenta está inactiva. Contacta al administrador.";
                    } elseif ($error === 'requerido') {
                        echo "Por favor complete todos los campos.";
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <form action="auth.php" method="POST" autocomplete="off">
                <div class="mb-3">
                    <label for="usuario" class="form-label">Usuario</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" name="usuario" id="usuario" required autofocus 
                               value="<?php echo isset($_GET['usuario']) ? htmlspecialchars($_GET['usuario']) : ''; ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="clave" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" name="clave" id="clave" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Recordar mi usuario</label>
                </div>
                
                <button type="submit" class="btn btn-primary w-100" name="login">
                    <i class="bi bi-box-arrow-in-right"></i> Ingresar
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    // Toggle para mostrar/ocultar contraseña
    document.getElementById('togglePassword').addEventListener('click', function () {
        const passwordField = document.getElementById('clave');
        const icon = this.querySelector('i');
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            passwordField.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    });

    // Recordar usuario con localStorage
    document.addEventListener('DOMContentLoaded', function() {
        const rememberCheckbox = document.getElementById('remember');
        const usuarioField = document.getElementById('usuario');
        
        // Cargar usuario guardado si existe
        if(localStorage.getItem('rememberUser') === 'true') {
            rememberCheckbox.checked = true;
            const savedUser = localStorage.getItem('savedUser');
            if(savedUser) {
                usuarioField.value = savedUser;
            }
        }
        
        // Guardar usuario si el checkbox está marcado
        document.querySelector('form').addEventListener('submit', function() {
            if(rememberCheckbox.checked) {
                localStorage.setItem('rememberUser', 'true');
                localStorage.setItem('savedUser', usuarioField.value);
            } else {
                localStorage.removeItem('rememberUser');
                localStorage.removeItem('savedUser');
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>

