<?php
// ============================================================
// ARCHIVO: visitors/exit.php
// Descripción: Registra la salida de un visitante activo.
// ACTUALIZACIÓN: Integración con audit_log al registrar salida.
// ============================================================

$depth = 1;
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

$visit_id = (int)filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($visit_id <= 0) {
    header("Location: list.php");
    exit();
}

try {
    $db = getDB();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
            header("Location: list.php?error=invalid_request");
            exit();
        }

        // Obtener datos antes de actualizar (para el log)
        $stmt = $db->prepare("
            SELECT v.id, v.entry_time, vis.full_name, vis.id_number
            FROM visits v
            JOIN visitors vis ON v.visitor_id = vis.id
            WHERE v.id = ? AND v.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$visit_id]);
        $visit_data = $stmt->fetch();

        $stmt = $db->prepare("
            UPDATE visits
            SET exit_time = NOW(), status = 'completed'
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$visit_id]);

        if ($stmt->rowCount() > 0) {
            // Registrar salida en auditoría
            if ($visit_data) {
                $entry_fmt = date('H:i', strtotime($visit_data['entry_time']));
                $desc = "Registró salida del visitante '{$visit_data['full_name']}' "
                      . "(ID: {$visit_data['id_number']}). "
                      . "Visita #$visit_id. Entró a las $entry_fmt.";
                logAudit($db, 'EXIT_VISIT', 'visit', $visit_id, $desc);
            }

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header("Location: detail.php?id=$visit_id&exit=1");
        } else {
            header("Location: detail.php?id=$visit_id&error=already_exited");
        }
        exit();
    }

    // GET: Mostrar pantalla de confirmación
    $stmt = $db->prepare("
        SELECT v.id, v.entry_time, v.status,
               vis.full_name, vis.id_number
        FROM visits v
        JOIN visitors vis ON v.visitor_id = vis.id
        WHERE v.id = ?
        LIMIT 1
    ");
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch();

    if (!$visit || $visit['status'] !== 'active') {
        header("Location: detail.php?id=$visit_id");
        exit();
    }

} catch (PDOException $e) {
    error_log("Error en exit.php: " . $e->getMessage());
    header("Location: list.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Registrar Salida';
$base_path  = '../';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-6">
        <div class="card border-0 shadow-sm border-top border-5 border-warning">
            <div class="card-header bg-warning bg-opacity-10 py-3">
                <h5 class="mb-0 fw-semibold text-warning">
                    <i class="bi bi-door-closed"></i> Confirmar Salida
                </h5>
            </div>
            <div class="card-body text-center py-4">
                <i class="bi bi-person-check fs-1 text-warning d-block mb-3"></i>
                <h4><?= htmlspecialchars($visit['full_name']) ?></h4>
                <p class="text-muted"><?= htmlspecialchars($visit['id_number']) ?></p>
                <p class="mb-1">
                    Entró a las: <strong><?= date('H:i', strtotime($visit['entry_time'])) ?></strong>
                </p>
                <p class="text-muted">
                    ¿Confirmas que este visitante está abandonando las instalaciones?
                </p>
                <form method="POST" action="exit.php?id=<?= (int)$visit_id ?>">
                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <a href="detail.php?id=<?= (int)$visit_id ?>"
                           class="btn btn-outline-secondary px-4">
                            <i class="bi bi-x"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-warning px-4">
                            <i class="bi bi-door-closed"></i> Confirmar Salida
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
