<?php
// ============================================================
// ARCHIVO: visitors/detail.php
// Descripción: Detalle completo de una visita.
// ACTUALIZACIÓN: Botones de editar/eliminar para administradores.
// ============================================================

$depth = 1;
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$visit_id = (int)filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($visit_id <= 0) {
    header("Location: list.php");
    exit();
}

// Mensajes de retroalimentación
$success_key = filter_input(INPUT_GET, 'success', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$error_key   = filter_input(INPUT_GET, 'error',   FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

$success_messages = [
    'exit=1'  => '✅ Salida registrada correctamente.',
    'updated' => '✅ Datos del visitante actualizados.',
];
$error_messages = [
    'already_exited' => 'Esta visita ya había sido completada.',
];

try {
    $db = getDB();

    // ── Query principal: datos de la visita + visitante ───────
    $stmt = $db->prepare("
        SELECT
            v.id            AS visit_id,
            v.entry_time,
            v.exit_time,
            v.status,
            v.host_name,
            v.host_department,
            v.reason,
            v.notes,
            v.registered_by,
            vis.id          AS visitor_id,
            vis.full_name,
            vis.id_number,
            vis.email       AS visitor_email,
            vis.phone,
            vis.company,
            vis.created_at  AS visitor_created_at,
            u.username      AS registered_by_name
        FROM visits v
        JOIN visitors vis ON v.visitor_id = vis.id
        JOIN users u ON v.registered_by = u.id
        WHERE v.id = ?
        LIMIT 1
    ");
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch();

    if (!$visit) {
        header("Location: list.php");
        exit();
    }

    // ── Dispositivos de la visita ─────────────────────────────
    $stmt = $db->prepare("
        SELECT id, device_type, device_name, mac_address, registered_at
        FROM devices
        WHERE visit_id = ?
        ORDER BY registered_at ASC
    ");
    $stmt->execute([$visit_id]);
    $devices = $stmt->fetchAll();

    // ── Otras visitas del mismo visitante ─────────────────────
    $stmt = $db->prepare("
        SELECT id, entry_time, exit_time, status, host_name
        FROM visits
        WHERE visitor_id = ? AND id != ?
        ORDER BY entry_time DESC
        LIMIT 5
    ");
    $stmt->execute([$visit['visitor_id'], $visit_id]);
    $other_visits = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error en detail.php: " . $e->getMessage());
    header("Location: list.php");
    exit();
}

// Generar CSRF para el form de salida y eliminación
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$is_admin  = ($_SESSION['user_role'] ?? '') === 'admin';
$page_title = 'Detalle de Visita';
$base_path  = '../';
require_once __DIR__ . '/../includes/header.php';

// Calcular duración
$duration = '';
if ($visit['exit_time']) {
    $secs = strtotime($visit['exit_time']) - strtotime($visit['entry_time']);
    $h    = floor($secs / 3600);
    $m    = floor(($secs % 3600) / 60);
    $duration = $h > 0 ? "{$h}h {$m}min" : "{$m}min";
}
?>

<!-- ── ENCABEZADO ────────────────────────────────────────── -->
<div class="row mb-3 align-items-start">
    <div class="col">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="list.php">Visitantes</a></li>
                <li class="breadcrumb-item active">Detalle Visita #<?= $visit_id ?></li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-0">
            <?= htmlspecialchars($visit['full_name']) ?>
        </h2>
        <p class="text-muted">
            <code class="font-monospace"><?= htmlspecialchars($visit['id_number']) ?></code>
            &mdash; Visita #<?= $visit_id ?>
        </p>
    </div>
    <div class="col-auto d-flex gap-2 flex-wrap">
        <!-- Botón de salida (solo si activa) -->
        <?php if ($visit['status'] === 'active'): ?>
        <a href="exit.php?id=<?= $visit_id ?>" class="btn btn-warning">
            <i class="bi bi-door-closed"></i> Registrar Salida
        </a>
        <?php endif; ?>

        <!-- Acciones de administrador -->
        <?php if ($is_admin): ?>
        <a href="../admin/visitor_edit.php?id=<?= (int)$visit['visitor_id'] ?>"
           class="btn btn-outline-primary" title="Editar datos del visitante">
            <i class="bi bi-pencil"></i> Editar Visitante
        </a>

        <!-- Eliminar visitante (con confirmación) -->
        <form method="POST" action="../admin/visitor_delete.php" class="d-inline">
            <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="visitor_id"  value="<?= (int)$visit['visitor_id'] ?>">
            <button type="submit" class="btn btn-outline-danger"
                    onclick="return confirm('⚠️ ELIMINACIÓN PERMANENTE\n\n¿Eliminar a <?= htmlspecialchars(addslashes($visit['full_name'])) ?> y TODAS sus visitas y dispositivos?\n\nEsta acción NO se puede deshacer.')">
                <i class="bi bi-trash"></i> Eliminar Visitante
            </button>
        </form>
        <?php endif; ?>

        <a href="list.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<!-- ── ALERTAS DE RETROALIMENTACIÓN ──────────────────────── -->
<?php
$exit_success = isset($_GET['exit']) && $_GET['exit'] === '1';
if ($exit_success || $success_key === 'updated'): ?>
<div class="alert alert-success alert-dismissible d-flex align-items-center">
    <i class="bi bi-check-circle-fill me-2"></i>
    <?= $exit_success ? 'Salida registrada correctamente.' : 'Datos del visitante actualizados.' ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error_key && isset($error_messages[$error_key])): ?>
<div class="alert alert-warning alert-dismissible d-flex align-items-center">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <?= htmlspecialchars($error_messages[$error_key]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ── CONTENIDO PRINCIPAL ─────────────────────────────── -->
<div class="row g-4">

    <!-- Columna izquierda: datos de la visita -->
    <div class="col-12 col-lg-8">

        <!-- Tarjeta de estado de la visita -->
        <?php
        [$border_color, $bg_status, $status_label] = match($visit['status']) {
            'active'    => ['border-success', 'bg-success', 'Visita Activa'],
            'completed' => ['border-secondary','bg-secondary','Visita Completada'],
            'cancelled' => ['border-danger',  'bg-danger',  'Visita Cancelada'],
            default     => ['border-light',   'bg-secondary', $visit['status']],
        };
        ?>
        <div class="card border-0 shadow-sm border-top border-5 <?= $border_color ?>">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-clock-history text-primary"></i> Detalle de la Visita
                </h5>
                <span class="badge <?= $bg_status ?> fs-6"><?= $status_label ?></span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Visita a</small>
                        <strong><?= htmlspecialchars($visit['host_name']) ?></strong>
                        <?php if ($visit['host_department']): ?>
                            <br><span class="text-muted small"><?= htmlspecialchars($visit['host_department']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Motivo</small>
                        <span><?= htmlspecialchars($visit['reason']) ?></span>
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">Hora de Entrada</small>
                        <strong><?= date('d/m/Y H:i', strtotime($visit['entry_time'])) ?></strong>
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">Hora de Salida</small>
                        <?php if ($visit['exit_time']): ?>
                            <strong><?= date('d/m/Y H:i', strtotime($visit['exit_time'])) ?></strong>
                        <?php else: ?>
                            <span class="text-success fw-bold">Aún dentro</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">Duración</small>
                        <strong><?= $duration ?: '—' ?></strong>
                    </div>
                    <?php if ($visit['notes']): ?>
                    <div class="col-12">
                        <small class="text-muted d-block">Observaciones</small>
                        <span><?= htmlspecialchars($visit['notes']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <small class="text-muted">
                            Registrado por <strong><?= htmlspecialchars($visit['registered_by_name']) ?></strong>
                            el <?= date('d/m/Y \a \l\a\s H:i', strtotime($visit['entry_time'])) ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dispositivos -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-laptop text-primary"></i>
                    Dispositivos Registrados
                    <span class="badge bg-secondary ms-1"><?= count($devices) ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($devices)): ?>
                    <p class="text-muted text-center py-3 mb-0">
                        <i class="bi bi-laptop-fill d-block fs-2 mb-2 opacity-25"></i>
                        No se registraron dispositivos en esta visita.
                    </p>
                <?php else: ?>
                    <?php foreach ($devices as $device):
                        $type_icon = match($device['device_type']) {
                            'laptop'     => 'bi-laptop',
                            'smartphone' => 'bi-phone',
                            'tablet'     => 'bi-tablet',
                            default      => 'bi-device-hdd',
                        };
                    ?>
                    <div class="d-flex align-items-center gap-3 p-3 border rounded mb-2 device-row">
                        <i class="bi <?= $type_icon ?> fs-3 text-primary"></i>
                        <div class="flex-grow-1">
                            <strong><?= htmlspecialchars($device['device_name'] ?: ucfirst($device['device_type'])) ?></strong>
                            <br>
                            <code class="text-muted font-monospace"><?= htmlspecialchars($device['mac_address']) ?></code>
                        </div>
                        <span class="badge bg-light text-dark border">
                            <?= ucfirst($device['device_type']) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.col-lg-8 -->

    <!-- Columna derecha: datos del visitante e historial -->
    <div class="col-12 col-lg-4">

        <!-- Datos personales del visitante -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-person-badge text-primary"></i> Datos del Visitante
                </h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Nombre</dt>
                    <dd class="col-7"><?= htmlspecialchars($visit['full_name']) ?></dd>

                    <dt class="col-5 text-muted">ID</dt>
                    <dd class="col-7 font-monospace"><?= htmlspecialchars($visit['id_number']) ?></dd>

                    <?php if ($visit['visitor_email']): ?>
                    <dt class="col-5 text-muted">Email</dt>
                    <dd class="col-7"><?= htmlspecialchars($visit['visitor_email']) ?></dd>
                    <?php endif; ?>

                    <?php if ($visit['phone']): ?>
                    <dt class="col-5 text-muted">Teléfono</dt>
                    <dd class="col-7"><?= htmlspecialchars($visit['phone']) ?></dd>
                    <?php endif; ?>

                    <?php if ($visit['company']): ?>
                    <dt class="col-5 text-muted">Empresa</dt>
                    <dd class="col-7"><?= htmlspecialchars($visit['company']) ?></dd>
                    <?php endif; ?>

                    <dt class="col-5 text-muted">1ª visita</dt>
                    <dd class="col-7"><?= date('d/m/Y', strtotime($visit['visitor_created_at'])) ?></dd>
                </dl>
            </div>
        </div>

        <!-- Historial de otras visitas -->
        <?php if (!empty($other_visits)): ?>
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold text-muted">
                    <i class="bi bi-clock-history"></i> Visitas Anteriores
                </h6>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($other_visits as $ov): ?>
                <li class="list-group-item py-2">
                    <a href="detail.php?id=<?= (int)$ov['id'] ?>" class="text-decoration-none">
                        <small class="d-block text-muted">
                            <?= date('d/m/Y H:i', strtotime($ov['entry_time'])) ?>
                        </small>
                        <span><?= htmlspecialchars($ov['host_name']) ?></span>
                        <?php
                        $badge = match($ov['status']) {
                            'active'    => 'bg-success',
                            'completed' => 'bg-secondary',
                            default     => 'bg-danger'
                        };
                        ?>
                        <span class="badge <?= $badge ?> ms-1 float-end">
                            <?= ucfirst($ov['status']) ?>
                        </span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

    </div><!-- /.col-lg-4 -->

</div><!-- /.row -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
