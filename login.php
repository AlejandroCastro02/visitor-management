<?php
// ============================================================
// ARCHIVO: login.php
// Descripción: Página de inicio de sesión.
//
// Esta es una página PÚBLICA (no requiere sesión activa).
// Muestra el formulario de login y mensajes de error/éxito.
// El formulario envía los datos a auth/login.php (POST).
// ============================================================

// Iniciar sesión para:
//  1. Verificar si ya está logueado (redirigir al dashboard)
//  2. Generar el token CSRF que protege el formulario
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => false,
        'cookie_samesite' => 'Strict',
    ]);
}

// Si ya tiene sesión activa, redirigir al dashboard directamente
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// ── Generar token CSRF ───────────────────────────────────────
// random_bytes(32) genera 32 bytes criptográficamente aleatorios
// bin2hex los convierte a string hexadecimal (64 caracteres)
// Este token se incrusta en el formulario como campo oculto
// y se valida en auth/login.php antes de procesar el login
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Leer mensajes de error de la URL ────────────────────────
// Los errores vienen como parámetro GET: login.php?error=invalid_credentials
$error_messages = [
    'invalid_credentials' => 'Email o contraseña incorrectos.',
    'empty_fields'        => 'Por favor completa todos los campos.',
    'invalid_request'     => 'Solicitud inválida. Recarga la página.',
    'server_error'        => 'Error del servidor. Intenta de nuevo.',
    'account_disabled'    => 'Tu cuenta ha sido desactivada. Contacta al administrador.',
];

// filter_input filtra el parámetro GET de forma segura
$error_key = filter_input(INPUT_GET, 'error', FILTER_SANITIZE_SPECIAL_CHARS);
$error_msg = $error_key ? ($error_messages[$error_key] ?? 'Error desconocido.') : null;

// Mensaje de logout exitoso
$logout_success = isset($_GET['logout']);
$expired        = isset($_GET['expired']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Sistema de Visitantes</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
          rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-8 col-md-6 col-lg-4">

            <!-- Tarjeta de Login -->
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-header bg-primary text-white text-center py-4 rounded-top-4">
                    <i class="bi bi-building-check fs-1"></i>
                    <h4 class="mt-2 mb-0 fw-bold">VisitorControl</h4>
                    <p class="mb-0 small opacity-75">Sistema de Gestión de Visitantes</p>
                </div>

                <div class="card-body p-4">

                    <!-- ── Alertas de estado ─────────────────────── -->

                    <?php if ($logout_success): ?>
                    <!-- Mensaje de logout exitoso -->
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Sesión cerrada correctamente.
                    </div>
                    <?php endif; ?>

                    <?php if ($expired): ?>
                    <!-- Mensaje de sesión expirada por inactividad -->
                    <div class="alert alert-warning d-flex align-items-center" role="alert">
                        <i class="bi bi-clock-history me-2"></i>
                        Tu sesión expiró por inactividad. Inicia sesión nuevamente.
                    </div>
                    <?php endif; ?>

                    <?php if ($error_msg): ?>
                    <!-- Mensaje de error de login -->
                    <!-- htmlspecialchars evita XSS si el mensaje contuviera HTML -->
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?= htmlspecialchars($error_msg) ?>
                    </div>
                    <?php endif; ?>

                    <!-- ── Formulario de Login ───────────────────── -->
                    <!-- action apunta al procesador PHP -->
                    <!-- method POST: los datos van en el cuerpo, no en la URL -->
                    <form action="auth/login.php" method="POST" novalidate>

                        <!-- Token CSRF oculto: viaja con el formulario -->
                        <!-- Si alguien duplica el form desde otro sitio, no tendrá este token -->
                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                        <!-- Campo Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">
                                <i class="bi bi-envelope"></i> Correo electrónico
                            </label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-control form-control-lg <?= $error_msg ? 'is-invalid' : '' ?>"
                                placeholder="usuario@empresa.com"
                                autocomplete="email"
                                required
                            >
                        </div>

                        <!-- Campo Contraseña -->
                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold">
                                <i class="bi bi-lock"></i> Contraseña
                            </label>
                            <div class="input-group">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control form-control-lg"
                                    placeholder="••••••••"
                                    autocomplete="current-password"
                                    required
                                >
                                <!-- Botón para mostrar/ocultar contraseña -->
                                <button class="btn btn-outline-secondary"
                                        type="button" id="togglePassword"
                                        title="Mostrar/ocultar contraseña">
                                    <i class="bi bi-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Botón Submit -->
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg fw-semibold">
                                <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                            </button>
                        </div>

                    </form>
                </div>

                <!-- Información de credenciales de prueba (SOLO DESARROLLO) -->
                <div class="card-footer text-center text-muted small py-3 bg-light rounded-bottom-4">
                    <strong>Credenciales de prueba:</strong><br>
                    Admin: <code>admin@sistema.local</code> / <code>Admin1234!</code><br>
                    Recepción: <code>recepcion@sistema.local</code> / <code>Recep1234!</code>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
</script>

<script>
// ── Mostrar/ocultar contraseña ────────────────────────────────
// Cambia el type del input entre 'password' y 'text'
document.getElementById('togglePassword').addEventListener('click', function () {
    const passInput = document.getElementById('password');
    const eyeIcon   = document.getElementById('eyeIcon');

    if (passInput.type === 'password') {
        passInput.type = 'text';
        eyeIcon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        passInput.type = 'password';
        eyeIcon.classList.replace('bi-eye-slash', 'bi-eye');
    }
});

// ── Deshabilitar botón al enviar (previene doble submit) ─────
document.querySelector('form').addEventListener('submit', function () {
    const btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verificando...';
});
</script>
</body>
</html>
