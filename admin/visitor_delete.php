<?php
// ============================================================
// ARCHIVO: admin/visitor_delete.php
// Descripción: Eliminación permanente de un visitante y TODAS
//              sus visitas y dispositivos asociados.
//
// ADVERTENCIA: Operación destructiva e irreversible.
// Se usa una transacción para garantizar que o se borra todo
// o no se borra nada (consistencia de la BD).
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

try {
    $db = getDB();

    // Obtener datos del visitante antes de eliminar (para el log)
    $stmt = $db->prepare("SELECT id, full_name, id_number FROM visitors WHERE id = ? LIMIT 1");
    $stmt->execute([$visitor_id]);
    $visitor = $stmt->fetch();

    if (!$visitor) {
        header("Location: ../visitors/list.php?error=not_found");
        exit();
    }

    // Contar registros que se van a eliminar (para el log)
    $cnt_visits = $db->prepare("SELECT COUNT(*) FROM visits WHERE visitor_id = ?");
    $cnt_visits->execute([$visitor_id]);
    $num_visits = $cnt_visits->fetchColumn();

    $cnt_devices = $db->prepare("SELECT COUNT(*) FROM devices WHERE visitor_id = ?");
    $cnt_devices->execute([$visitor_id]);
    $num_devices = $cnt_devices->fetchColumn();

    // ── Transacción: borrar todo o nada ──────────────────────
    $db->beginTransaction();

    // 1. Borrar dispositivos del visitante
    $db->prepare("DELETE FROM devices WHERE visitor_id = ?")->execute([$visitor_id]);

    // 2. Borrar visitas del visitante
    $db->prepare("DELETE FROM visits WHERE visitor_id = ?")->execute([$visitor_id]);

    // 3. Borrar el visitante
    $db->prepare("DELETE FROM visitors WHERE id = ?")->execute([$visitor_id]);

    $db->commit();

    // Registrar en auditoría (después del commit, pero con los datos guardados antes)
    $desc = "Eliminó permanentemente al visitante '{$visitor['full_name']}' "
          . "(ID: {$visitor['id_number']}) junto con $num_visits visita(s) "
          . "y $num_devices dispositivo(s) asociados.";
    logAudit($db, 'DELETE_VISITOR', 'visitor', $visitor_id, $desc);

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header("Location: ../visitors/list.php?success=visitor_deleted");

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error en visitor_delete.php: " . $e->getMessage());
    header("Location: ../visitors/list.php?error=delete_failed");
}
exit();
