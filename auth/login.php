<?php
// ============================================================
// ARCHIVO: auth/login.php
// Descripción: Procesa el formulario de login (solo POST).
//
// Flujo:
//  1. Recibe email + password del formulario
//  2. Valida que los campos no estén vacíos
//  3. Busca el usuario en la BD por email
//  4. Verifica la contraseña con password_verify() (bcrypt)
//  5. Si todo está bien: crea la sesión y redirige al dashboard
//  6. Si algo falla: redirige al login con mensaje de error
// ============================================================

// Iniciar sesión con parámetros seguros (igual que en auth_check.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => false, // ← true en producción HTTPS
        'cookie_samesite' => 'Strict',
    ]);
}

// Solo aceptar peticiones POST (si alguien entra directo por URL, redirigir)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../login.php");
    exit();
}

// ── Importar la conexión a la BD ──────────────────────────────
require_once __DIR__ . '/../config/database.php';

// ── 1. Recoger y sanitizar inputs ────────────────────────────
// filter_input limpia la entrada: elimina espacios, filtra el tipo
$email    = trim(filter_input(INPUT_POST, 'email',    FILTER_SANITIZE_EMAIL));
$password = trim(filter_input(INPUT_POST, 'password', FILTER_DEFAULT));

// ── Verificar token CSRF ─────────────────────────────────────
// El token fue generado en login.php y guardado en sesión.
// hash_equals compara los tokens de forma segura (tiempo constante,
// evita timing attacks que adivinan el token carácter a carácter)
if (!isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    
    header("Location: ../login.php?error=invalid_request");
    exit();
}

// ── 2. Validar que los campos no estén vacíos ────────────────
if (empty($email) || empty($password)) {
    header("Location: ../login.php?error=empty_fields");
    exit();
}

// ── 3. Buscar usuario en la BD ───────────────────────────────
try {
    $db = getDB();
    
    // Prepared statement: el ? es un placeholder seguro.
    // PDO reemplaza el ? con el valor real SIN riesgo de SQL Injection.
    // Jamás concatenes variables directamente en el SQL:
    //   MAL:  "SELECT * FROM users WHERE email = '$email'"
    //   BIEN: "SELECT * FROM users WHERE email = ?"
    $stmt = $db->prepare("
        SELECT id, username, email, password, role, active
        FROM users
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(); // Devuelve array asociativo o false si no existe

} catch (PDOException $e) {
    error_log("Error en login: " . $e->getMessage());
    header("Location: ../login.php?error=server_error");
    exit();
}

// ── 4. Verificar contraseña ──────────────────────────────────
// IMPORTANTE: El mensaje de error es GENÉRICO, no distingue entre
// "usuario no existe" y "contraseña incorrecta".
// Esto evita el ataque de "user enumeration" donde alguien
// prueba emails para saber cuáles están registrados.

if (!$user || !password_verify($password, $user['password'])) {
    // Añadir pequeño delay para dificultar ataques de fuerza bruta
    // (hace que probar muchas contraseñas sea más lento)
    sleep(1);
    
    header("Location: ../login.php?error=invalid_credentials");
    exit();
}

// ── Verificar que la cuenta esté activa ──────────────────────
if (!$user['active']) {
    header("Location: ../login.php?error=account_disabled");
    exit();
}

// ── 5. Todo correcto: crear la sesión ───────────────────────
// Regenerar el ID de sesión para prevenir Session Fixation:
// Si alguien plantó un Session ID antes del login, ahora ya no sirve
session_regenerate_id(true);

// Guardar datos del usuario en la sesión
$_SESSION['user_id']       = $user['id'];
$_SESSION['user_name']     = $user['username'];
$_SESSION['user_email']    = $user['email'];
$_SESSION['user_role']     = $user['role'];
$_SESSION['last_activity'] = time();

// Invalidar el token CSRF usado (generar uno nuevo para la próxima vez)
unset($_SESSION['csrf_token']);

// ── Actualizar last_login en la BD ───────────────────────────
try {
    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
} catch (PDOException $e) {
    // No es crítico si falla, no interrumpir el login
    error_log("Error actualizando last_login: " . $e->getMessage());
}

// ── 6. Redirigir al dashboard ────────────────────────────────
// Si había una URL guardada antes del login, redirigir ahí
$redirect = $_SESSION['redirect_after_login'] ?? '../dashboard.php';
unset($_SESSION['redirect_after_login']);

header("Location: $redirect");
exit();
