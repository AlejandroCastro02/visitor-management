<?php
// ============================================================
// ARCHIVO: admin/user_save.php
// Descripción: Procesador POST para crear y editar usuarios.
//
// Solo acepta POST. Valida, guarda y registra en audit_log.
// Redirige con mensaje de éxito/error al terminar.
// ============================================================

$depth = 1;
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

requireRole('admin');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: users.php");
    exit();
}

// ── Verificar CSRF ────────────────────────────────────────────
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    header("Location: users.php?error=invalid_request");
    exit();
}

$mode    = $_POST['mode']    ?? 'create';       // 'create' o 'edit'
$user_id = (int)($_POST['user_id'] ?? 0);       // ID en edición

// ── Recoger y limpiar inputs ─────────────────────────────────
$username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$email    = trim(filter_input(INPUT_POST, 'email',    FILTER_SANITIZE_EMAIL) ?? '');
$role     = trim(filter_input(INPUT_POST, 'role',     FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$password = $_POST['password']         ?? '';
$confirm  = $_POST['password_confirm'] ?? '';
$active   = isset($_POST['active']) ? 1 : 0;    // Checkbox

// ── Validaciones de servidor ─────────────────────────────────
$errors = [];

// Nombre de usuario
if (empty($username)) {
    $errors[] = 'El nombre de usuario es obligatorio.';
} elseif (!preg_match('/^[a-zA-Z0-9_\-]{3,50}$/', $username)) {
    $errors[] = 'El nombre de usuario solo puede tener letras, números, guión y guión bajo (3–50 chars).';
}

// Email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Ingresa un correo electrónico válido.';
}

// Rol
$valid_roles = ['admin', 'receptionist', 'guard'];
if (!in_array($role, $valid_roles)) {
    $errors[] = 'Rol inválido.';
}

// Contraseña
if ($mode === 'create' && empty($password)) {
    $errors[] = 'La contraseña es obligatoria para nuevos usuarios.';
}
if (!empty($password)) {
    if (strlen($password) < 8) {
        $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'La contraseña debe incluir al menos una letra mayúscula.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'La contraseña debe incluir al menos una letra minúscula.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'La contraseña debe incluir al menos un número.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Las contraseñas no coinciden.';
    }
}

// Si hay errores de validación, volver al formulario
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data']   = compact('username', 'email', 'role', 'active');
    $back = $mode === 'edit' ? "user_form.php?id=$user_id" : "user_form.php";
    header("Location: $back");
    exit();
}

try {
    $db = getDB();

    // ── Verificar unicidad de username y email ────────────────
    // Excluir el propio registro en modo edición
    $exclude_id = $mode === 'edit' ? $user_id : 0;

    $stmt = $db->prepare("
        SELECT COUNT(*) FROM users
        WHERE (username = ? OR email = ?) AND id != ?
    ");
    $stmt->execute([$username, $email, $exclude_id]);
    if ($stmt->fetchColumn() > 0) {
        $_SESSION['form_errors'] = ['El nombre de usuario o el email ya están registrados.'];
        $_SESSION['form_data']   = compact('username', 'email', 'role', 'active');
        $back = $mode === 'edit' ? "user_form.php?id=$user_id" : "user_form.php";
        header("Location: $back");
        exit();
    }

    if ($mode === 'create') {
        // ── CREAR usuario ─────────────────────────────────────
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $db->prepare("
            INSERT INTO users (username, email, password, role, active)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([$username, $email, $hash, $role]);
        $new_id = (int)$db->lastInsertId();

        // Registrar en auditoría
        logAudit($db, 'CREATE_USER', 'user', $new_id,
            "Creó el usuario '$username' ($email) con rol '$role'.");

        // Renovar CSRF
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header("Location: users.php?success=created");

    } else {
        // ── EDITAR usuario ────────────────────────────────────
        // Recuperar datos anteriores para el log
        $old = $db->prepare("SELECT username, email, role, active FROM users WHERE id = ?");
        $old->execute([$user_id]);
        $old_data = $old->fetch();

        if (!$old_data) {
            header("Location: users.php?error=not_found");
            exit();
        }

        // ── Regla: no puede desactivarse a sí mismo ───────────
        if ($user_id === (int)$_SESSION['user_id'] && $active === 0) {
            $_SESSION['form_errors'] = ['No puedes desactivar tu propia cuenta.'];
            $_SESSION['form_data']   = compact('username', 'email', 'role', 'active');
            header("Location: user_form.php?id=$user_id");
            exit();
        }

        // ── Regla: no puede degradar/desactivar al último admin ─
        if ($old_data['role'] === 'admin' && ($role !== 'admin' || $active === 0)) {
            $count_stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1");
            if ((int)$count_stmt->fetchColumn() <= 1) {
                $_SESSION['form_errors'] = ['No puedes modificar al único administrador activo del sistema.'];
                $_SESSION['form_data']   = compact('username', 'email', 'role', 'active');
                header("Location: user_form.php?id=$user_id");
                exit();
            }
        }

        // Construir la query de actualización
        if (!empty($password)) {
            // Con nueva contraseña
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $db->prepare("
                UPDATE users SET username=?, email=?, password=?, role=?, active=?
                WHERE id=?
            ");
            $stmt->execute([$username, $email, $hash, $role, $active, $user_id]);
        } else {
            // Sin cambiar contraseña
            $stmt = $db->prepare("
                UPDATE users SET username=?, email=?, role=?, active=?
                WHERE id=?
            ");
            $stmt->execute([$username, $email, $role, $active, $user_id]);
        }

        // Descripción detallada del cambio para auditoría
        $changes = [];
        if ($old_data['username'] !== $username)   $changes[] = "username: '{$old_data['username']}' → '$username'";
        if ($old_data['email']    !== $email)       $changes[] = "email: '{$old_data['email']}' → '$email'";
        if ($old_data['role']     !== $role)        $changes[] = "rol: '{$old_data['role']}' → '$role'";
        if ((int)$old_data['active'] !== $active)   $changes[] = "activo: " . ($active ? 'sí' : 'no');
        if (!empty($password))                      $changes[] = "contraseña: actualizada";

        $desc = "Actualizó usuario ID $user_id ('$username'). Cambios: "
              . (empty($changes) ? 'ninguno' : implode(', ', $changes)) . '.';

        logAudit($db, 'UPDATE_USER', 'user', $user_id, $desc);

        // Renovar CSRF
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header("Location: users.php?success=updated");
    }

} catch (PDOException $e) {
    error_log("Error en user_save.php: " . $e->getMessage());
    $_SESSION['form_errors'] = ['Error al guardar. Intenta de nuevo.'];
    $back = $mode === 'edit' ? "user_form.php?id=$user_id" : "user_form.php";
    header("Location: $back");
}
exit();
