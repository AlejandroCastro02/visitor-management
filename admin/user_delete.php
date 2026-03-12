<?php
// ============================================================
// ARCHIVO: admin/user_delete.php
// Descripción: Eliminación permanente de un usuario del sistema.
//
// Reglas de seguridad:
//  - No eliminar la propia cuenta.
//  - No eliminar al último administrador activo.
//  - Solo acepta POST con token CSRF válido.
// ============================================================

$depth = 1;
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: users.php");
    exit();
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    header("Location: users.php?error=invalid_request");
    exit();
}

$target_id = (int)($_POST['user_id'] ?? 0);

// No eliminar la propia cuenta
if ($target_id === (int)$_SESSION['user_id']) {
    header("Location: users.php?error=self_delete");
    exit();
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id, username, email, role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$target_id]);
    $user = $stmt->fetch();

    if (!$user) {
        header("Location: users.php?error=not_found");
        exit();
    }

    // No eliminar al último administrador
    if ($user['role'] === 'admin') {
        $count_stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1");
        if ((int)$count_stmt->fetchColumn() <= 1) {
            header("Location: users.php?error=last_admin");
            exit();
        }
    }

    // Registrar en auditoría ANTES de eliminar
    // (después ya no existirá el user_id en la tabla)
    logAudit($db, 'DELETE_USER', 'user', $target_id,
        "Eliminó el usuario '{$user['username']}' ({$user['email']}) con rol '{$user['role']}'.");

    // Eliminar el usuario
    // NOTA: Si tiene visitas registradas, la FK lo bloqueará.
    // En ese caso, se debería desactivar en vez de eliminar.
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$target_id]);

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header("Location: users.php?success=deleted");

} catch (PDOException $e) {
    // Capturar error de FK (el usuario tiene registros asociados)
    if ($e->getCode() === '23000') {
        header("Location: users.php?error=delete_failed");
    } else {
        error_log("Error en user_delete.php: " . $e->getMessage());
        header("Location: users.php?error=invalid_request");
    }
}
exit();
