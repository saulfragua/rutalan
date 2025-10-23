<?php include 'includes/header.php'; ?>

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
        $error = $_GET['error'];
        if ($error === 'incorrecto') {
            echo "Usuario o contraseña incorrectos.";
        } elseif ($error === 'inactivo') {
            echo "Tu cuenta está inactiva. Contacta al administrador.";
        }
        ?>
    </div>
<?php endif; ?>
            
            <form action="auth.php" method="POST">
                <div class="mb-3">
                    <label for="usuario" class="form-label">Usuario</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" name="usuario" required autofocus>
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
                
                <button type="submit" class="btn btn-primary w-100" name="login">
                    <i class="bi bi-box-arrow-in-right"></i> Ingresar
                </button>
            </form>
           <!-- <div class="mt-3 text-center">
                <a href="registro.php"><i class="bi bi-person-plus"></i> ¿No tienes cuenta? Regístrate</a>
            </div> -->
        </div>
    </div>
</div>

<script>
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
</script>

<?php include 'includes/footer.php'; ?>
