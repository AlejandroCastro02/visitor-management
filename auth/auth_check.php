<?php
// ============================================================
// ARCHIVO: auth/auth_check.php
// Descripción: "Middleware" de autenticación.
//
// Este archivo se incluye al inicio de CADA página protegida.
// Su trabajo es: verificar que hay una sesión activa y válida.
// Si no la hay, redirige al login y detiene la ejecución.
//
// Uso en cualquier página protegida:
//   require_once __DIR__ . '/../auth/auth_check.php';
// ============================================================

// session_start() debe llamarse ANTES de acceder a $_SESSION
// Los parámetros hacen la cookie de sesión más segura:
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        // httponly: true → JavaScript NO puede leer esta cookie
        // Protege contra ataques XSS que roban sesiones
        'cookie_httponly' => true,

        // secure: true → La cookie SOLO se envía por HTTPS
        // En desarrollo local (http) cambiar a false temporalmente
        'cookie_secure'   => false, // ← Cambiar a TRUE en producción con HTTPS

        // samesite: Strict → La cookie no se envía en peticiones
        // que vienen de otros sitios (protege contra CSRF básico)
        'cookie_samesite' => 'Strict',

        // Tiempo de vida de la cookie de sesión (en segundos)
        // 0 = expira al cerrar el navegador
        'cookie_lifetime' => 0,
    ]);
}

// ── Verificar si el usuario tiene sesión activa ───────────────
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Guardar la URL que intentaba visitar para redirigir después del login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    // Redirigir al login
    header("Location: " . getLoginUrl());
    exit(); // IMPORTANTE: exit() detiene la ejecución del resto del archivo
}

// ── Verificar que la sesión no haya expirado ─────────────────
// Expiración manual: si la última actividad fue hace más de 30 minutos
$session_lifetime = 30 * 60; // 30 minutos en segundos

if (isset($_SESSION['last_activity'])) {
    $inactive_time = time() - $_SESSION['last_activity'];
    
    if ($inactive_time > $session_lifetime) {
        // La sesión expiró por inactividad
        session_unset();   // Limpiar variables de sesión
        session_destroy(); // Destruir la sesión
        
        // Redirigir con mensaje de aviso
        header("Location: " . getLoginUrl() . "?expired=1");
        exit();
    }
}

// Actualizar timestamp de última actividad
$_SESSION['last_activity'] = time();

// ── Función auxiliar ─────────────────────────────────────────
/**
 * Calcula la URL correcta al login.php sin importar desde qué carpeta
 * se incluya este archivo. Usa la variable global $depth si existe.
 */
function getLoginUrl(): string {
    // Si la página que incluye este archivo definió $depth, lo usamos
    global $depth;
    $prefix = isset($depth) ? str_repeat('../', $depth) : '';
    return $prefix . 'login.php';
}

/**
 * requireRole()
 * Verifica que el usuario tenga el rol requerido.
 * Uso: requireRole('admin') en páginas solo para admins.
 *
 * @param string $role  Rol requerido ('admin', 'receptionist' o 'guard')
 */
function requireRole(string $role): void {
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $role) {
        header("HTTP/1.1 403 Forbidden");
        die("<h2>Acceso denegado.</h2><p>No tienes permisos para esta sección.</p>");
    }
}

/**
 * requireAnyRole()
 * Verifica que el usuario tenga AL MENOS uno de los roles indicados.
 * Uso: requireAnyRole(['admin', 'receptionist']) bloquea a vigilantes.
 *
 * @param array $roles  Lista de roles permitidos
 */
function requireAnyRole(array $roles): void {
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $roles, true)) {
        header("HTTP/1.1 403 Forbidden");
        die("
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>
            <div class='container text-center mt-5'>
                <i class='bi bi-shield-exclamation' style='font-size:4rem;color:#dc3545'></i>
                <h2 class='mt-3'>Acceso Denegado</h2>
                <p class='text-muted'>Tu rol no tiene permisos para realizar esta acción.</p>
                <a href='../dashboard.php' class='btn btn-primary mt-2'>Volver al Dashboard</a>
            </div>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css' rel='stylesheet'>
        ");
        exit();
    }
}

/**
 * canRegisterVisit()
 * Devuelve true si el usuario actual puede registrar nuevas visitas.
 * Útil para mostrar/ocultar botones en vistas sin lanzar error.
 */
function canRegisterVisit(): bool {
    return in_array($_SESSION['user_role'] ?? '', ['admin', 'receptionist'], true);
}
