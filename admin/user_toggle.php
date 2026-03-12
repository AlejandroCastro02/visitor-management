<?php
// ============================================================
// ARCHIVO: admin/user_toggle.php
// Descripción: Activa o desactiva una cuenta de usuario.
//
// Reglas de negocio:
//  - Un usuario NO puede desactivar su propia cuenta.
//  - No se puede desactivar al último administrador activo.
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

// Regla: no puede togglarse a sí mismo
if ($target_id === (int)$_SESSION['user_id']) {
    header("Location: users.php?error=self_deactivate");
    exit();
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id, username, email, role, active FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$target_id]);
    $user = $stmt->fetch();

    if (!$user) {
        header("Location: users.php?error=not_found");
        exit();
    }

    $new_active = $user['active'] ? 0 : 1;

    // Regla: no se puede desactivar al último admin activo
    if ($user['role'] === 'admin' && $new_active === 0) {
        $count_stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1");
        if ((int)$count_stmt->fetchColumn() <= 1) {
            header("Location: users.php?error=last_admin");
            exit();
        }
    }

    $stmt = $db->prepare("UPDATE users SET active = ? WHERE id = ?");
    $stmt->execute([$new_active, $target_id]);

    $action_label = $new_active ? 'activada' : 'desactivada';
    logAudit($db, 'TOGGLE_USER', 'user', $target_id,
        "Cuenta del usuario '{$user['username']}' ({$user['email']}) $action_label.");

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header("Location: users.php?success=" . ($new_active ? 'enabled' : 'disabled'));

} catch (PDOException $e) {
    error_log("Error en user_toggle.php: " . $e->getMessage());
    header("Location: users.php?error=invalid_request");
}
exit();
