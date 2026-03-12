<?php
// ============================================================
// ARCHIVO: admin/visitor_save.php
// Descripción: Procesador POST para actualizar datos de visitante.
// ============================================================

$depth = 1;
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../visitors/list.php");
    exit();
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    header("Location: ../visitors/list.php?error=invalid_request");
    exit();
}

$visitor_id = (int)($_POST['visitor_id'] ?? 0);
if ($visitor_id <= 0) {
    header("Location: ../visitors/list.php");
    exit();
}

// ── Recoger inputs ────────────────────────────────────────────
$full_name = trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$id_number = trim(filter_input(INPUT_POST, 'id_number', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$email     = trim(filter_input(INPUT_POST, 'email',     FILTER_SANITIZE_EMAIL) ?? '');
$phone     = trim(filter_input(INPUT_POST, 'phone',     FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$company   = trim(filter_input(INPUT_POST, 'company',   FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

// ── Validaciones ──────────────────────────────────────────────
$errors = [];

if (empty($full_name)) {
    $errors[] = 'El nombre completo es obligatorio.';
} elseif (mb_strlen($full_name) > 150) {
    $errors[] = 'El nombre no puede superar 150 caracteres.';
}

if (empty($id_number)) {
    $errors[] = 'El número de identificación es obligatorio.';
} elseif (!preg_match('/^[a-zA-Z0-9\-\.]{3,30}$/', $id_number)) {
    $errors[] = 'El número de identificación contiene caracteres no permitidos (3–30 chars, letras/números/guión/punto).';
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'El correo electrónico no tiene un formato válido.';
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data']   = compact('full_name', 'id_number', 'email', 'phone', 'company');
    header("Location: visitor_edit.php?id=$visitor_id");
    exit();
}

try {
    $db = getDB();

    // Datos anteriores para el log
    $old_stmt = $db->prepare("SELECT full_name, id_number, email, phone, company FROM visitors WHERE id = ?");
    $old_stmt->execute([$visitor_id]);
    $old = $old_stmt->fetch();

    if (!$old) {
        header("Location: ../visitors/list.php?error=not_found");
        exit();
    }

    // Verificar unicidad del id_number (excluyendo el propio registro)
    $dup = $db->prepare("SELECT COUNT(*) FROM visitors WHERE id_number = ? AND id != ?");
    $dup->execute([$id_number, $visitor_id]);
    if ($dup->fetchColumn() > 0) {
        $_SESSION['form_errors'] = ['El número de identificación ya está registrado en otro visitante.'];
        $_SESSION['form_data']   = compact('full_name', 'id_number', 'email', 'phone', 'company');
        header("Location: visitor_edit.php?id=$visitor_id");
        exit();
    }

    $stmt = $db->prepare("
        UPDATE visitors
        SET full_name = ?, id_number = ?, email = ?, phone = ?, company = ?
        WHERE id = ?
    ");
    $stmt->execute([$full_name, $id_number, $email ?: null, $phone ?: null, $company ?: null, $visitor_id]);

    // Construir descripción del cambio
    $changes = [];
    if ($old['full_name'] !== $full_name)               $changes[] = "nombre: '{$old['full_name']}' → '$full_name'";
    if ($old['id_number'] !== $id_number)               $changes[] = "identificación: '{$old['id_number']}' → '$id_number'";
    if (($old['email'] ?? '')   !== $email)             $changes[] = "email actualizado";
    if (($old['phone'] ?? '')   !== $phone)             $changes[] = "teléfono actualizado";
    if (($old['company'] ?? '') !== $company)           $changes[] = "empresa actualizada";

    $desc = "Actualizó visitante ID $visitor_id ('{$old['full_name']}'). Cambios: "
          . (empty($changes) ? 'ninguno' : implode(', ', $changes)) . '.';

    logAudit($db, 'UPDATE_VISITOR', 'visitor', $visitor_id, $desc);

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header("Location: ../visitors/detail.php?id=$visitor_id&success=updated");

} catch (PDOException $e) {
    error_log("Error en visitor_save.php: " . $e->getMessage());
    $_SESSION['form_errors'] = ['Error al guardar. Intenta de nuevo.'];
    header("Location: visitor_edit.php?id=$visitor_id");
}
exit();
