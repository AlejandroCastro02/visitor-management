<?php
// ============================================================
// ARCHIVO: visitors/detail.php
// Descripción: Detalle completo de una visita específica.
//
// Muestra:
//  - Datos del visitante
//  - Detalles de la visita (motivo, anfitrión, tiempos)
//  - Lista de dispositivos registrados (con MACs)
//  - Botón para marcar salida si la visita está activa
// ============================================================

$depth = 1;
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

// Obtener el ID de la visita del parámetro GET
// (int) castea a entero: si alguien pasa "1 OR 1=1", queda como 1
$visit_id = (int)filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Si no hay ID válido, redirigir a la lista
if ($visit_id <= 0) {
    header("Location: list.php");
    exit();
}

// ¿Viene de un registro nuevo? Para mostrar mensaje de éxito
$is_new = isset($_GET['new']);

try {
    $db = getDB();

    // ── Query: Detalle completo de la visita ─────────────────
    // Múltiples JOINs para traer datos de varias tablas en una query
    $stmt = $db->prepare("
        SELECT
            v.id             as visit_id,
            v.host_name,
            v.host_department,
            v.reason,
            v.entry_time,
            v.exit_time,
            v.status,
            v.notes,
            vis.id           as visitor_id,
            vis.full_name,
            vis.id_number,
            vis.email,
            vis.phone,
            vis.company,
            vis.created_at   as visitor_since,
            u.username       as registered_by
        FROM visits v
        JOIN visitors vis ON v.visitor_id = vis.id
        JOIN users u ON v.registered_by = u.id
        WHERE v.id = ?
        LIMIT 1
    ");
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch();

    // Si no existe la visita, redirigir
    if (!$visit) {
        header("Location: list.php");
        exit();
    }

    // ── Query: Dispositivos de esta visita ───────────────────
    $stmt = $db->prepare("
        SELECT id, device_type, device_name, mac_address, registered_at
        FROM devices
        WHERE visit_id = ?
        ORDER BY registered_at ASC
    ");
    $stmt->execute([$visit_id]);
    $devices = $stmt->fetchAll();

    // ── Query: Historial de visitas del mismo visitante ───────
    $stmt = $db->prepare("
        SELECT id, entry_time, exit_time, host_name, status, reason
        FROM visits
        WHERE visitor_id = ? AND id != ?
        ORDER BY entry_time DESC
        LIMIT 5
    ");
    $stmt->execute([$visit['visitor_id'], $visit_id]);
    $history = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error en detail.php: " . $e->getMessage());
    header("Location: list.php");
    exit();
}

// Calcular duración de la visita
$entry = new DateTime($visit['entry_time']);
$exit  = $visit['exit_time'] ? new DateTime($visit['exit_time']) : new DateTime();
$duration = $entry->diff($exit);

$page_title = 'Detalle de Visita';
$base_path  = '../';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── BREADCRUMB Y TÍTULO ───────────────────────────────── -->
<div class="row mb-4">
    <div class="col">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="list.php">Visitantes</a></li>
                <li class="breadcrumb-item active">Detalle #<?= $visit['visit_id'] ?></li>
            </ol>
        </nav>
        <h2 class="fw-bold">
            <i class="bi bi-person-badge text-primary"></i>
            <?= htmlspecialchars($visit['full_name']) ?>
        </h2>
    </div>
    <div class="col-auto d-flex align-items-center gap-2">
        <?php if ($visit['status'] === 'active'): ?>
        <a href="exit.php?id=<?= (int)$visit['visit_id'] ?>"
           class="btn btn-warning">
            <i class="bi bi-door-closed"></i> Registrar Salida
        </a>
        <?php endif; ?>
        <a href="list.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
</div>

<!-- ── ALERTA DE NUEVO REGISTRO ─────────────────────────── -->
<?php if ($is_new): ?>
<div class="alert alert-success alert-dismissible d-flex align-items-center mb-4">
    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
    <div>
        <strong>¡Visita registrada exitosamente!</strong>
        La visita de <strong><?= htmlspecialchars($visit['full_name']) ?></strong>
        ha sido registrada. Hora de entrada: <?= date('H:i', strtotime($visit['entry_time'])) ?>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">

    <!-- ── COLUMNA IZQUIERDA ─────────────────────────────── -->
    <div class="col-12 col-lg-4">

        <!-- Tarjeta: Datos del Visitante -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-person-circle text-primary"></i> Datos del Visitante
                </h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted small">Nombre</dt>
                    <dd class="col-7 small"><?= htmlspecialchars($visit['full_name']) ?></dd>

                    <dt class="col-5 text-muted small">ID/Cédula</dt>
                    <dd class="col-7 small font-monospace"><?= htmlspecialchars($visit['id_number']) ?></dd>

                    <dt class="col-5 text-muted small">Email</dt>
                    <dd class="col-7 small">
                        <?= $visit['email']
                            ? htmlspecialchars($visit['email'])
                            : '<span class="text-muted">—</span>'
                        ?>
                    </dd>

                    <dt class="col-5 text-muted small">Teléfono</dt>
                    <dd class="col-7 small"><?= htmlspecialchars($visit['phone'] ?? '—') ?></dd>

                    <dt class="col-5 text-muted small">Empresa</dt>
                    <dd class="col-7 small"><?= htmlspecialchars($visit['company'] ?? '—') ?></dd>

                    <dt class="col-5 text-muted small">1er Registro</dt>
                    <dd class="col-7 small"><?= date('d/m/Y', strtotime($visit['visitor_since'])) ?></dd>
                </dl>
            </div>
        </div>

        <!-- Tarjeta: Historial de visitas previas -->
        <?php if (!empty($history)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-clock-history text-muted"></i> Visitas Anteriores
                </h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($history as $h): ?>
                    <li class="list-group-item py-2">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">
                                <?= date('d/m/Y', strtotime($h['entry_time'])) ?>
                            </small>
                            <?php
                            echo match($h['status']) {
                                'active'    => '<span class="badge bg-success">Activa</span>',
                                'completed' => '<span class="badge bg-secondary">Completada</span>',
                                'cancelled' => '<span class="badge bg-danger">Cancelada</span>',
                                default     => ''
                            };
                            ?>
                        </div>
                        <small><?= htmlspecialchars($h['host_name']) ?></small>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- ── COLUMNA DERECHA ───────────────────────────────── -->
    <div class="col-12 col-lg-8">

        <!-- Tarjeta: Detalles de la Visita -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-calendar-check text-primary"></i> Detalles de la Visita
                </h6>
                <!-- Badge de estado con color -->
                <?php
                echo match($visit['status']) {
                    'active'    => '<span class="badge bg-success fs-6">Activa</span>',
                    'completed' => '<span class="badge bg-secondary fs-6">Completada</span>',
                    'cancelled' => '<span class="badge bg-danger fs-6">Cancelada</span>',
                    default     => ''
                };
                ?>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <p class="text-muted small mb-1">Visita a</p>
                        <p class="fw-semibold"><?= htmlspecialchars($visit['host_name']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p class="text-muted small mb-1">Departamento</p>
                        <p><?= htmlspecialchars($visit['host_department'] ?? '—') ?></p>
                    </div>
                    <div class="col-md-6">
                        <p class="text-muted small mb-1">Hora de Entrada</p>
                        <p class="fw-semibold text-success">
                            <i class="bi bi-door-open"></i>
                            <?= date('d/m/Y H:i', strtotime($visit['entry_time'])) ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p class="text-muted small mb-1">Hora de Salida</p>
                        <p class="fw-semibold <?= $visit['exit_time'] ? 'text-danger' : 'text-muted' ?>">
                            <i class="bi bi-door-closed"></i>
                            <?= $visit['exit_time']
                                ? date('d/m/Y H:i', strtotime($visit['exit_time']))
                                : 'Aún en las instalaciones'
                            ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p class="text-muted small mb-1">Duración</p>
                        <p>
                            <!-- Formatear la diferencia de tiempo -->
                            <?= $duration->h > 0 ? $duration->h . 'h ' : '' ?>
                            <?= $duration->i ?>min
                            <?= $visit['status'] === 'active' ? '(en curso)' : '' ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p class="text-muted small mb-1">Registrado por</p>
                        <p><?= htmlspecialchars($visit['registered_by']) ?></p>
                    </div>
                    <div class="col-12">
                        <p class="text-muted small mb-1">Motivo</p>
                        <p class="border rounded p-2 bg-light"><?= htmlspecialchars($visit['reason']) ?></p>
                    </div>
                    <?php if ($visit['notes']): ?>
                    <div class="col-12">
                        <p class="text-muted small mb-1">Notas</p>
                        <p class="border rounded p-2 bg-light"><?= htmlspecialchars($visit['notes']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tarjeta: Dispositivos Registrados -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-laptop text-primary"></i>
                    Dispositivos Registrados (<?= count($devices) ?>)
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($devices)): ?>
                    <p class="text-muted text-center py-3">
                        <i class="bi bi-laptop d-block fs-2 mb-2"></i>
                        No se registraron dispositivos para esta visita.
                    </p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($devices as $device): ?>
                        <div class="col-12 col-md-6">
                            <div class="border rounded p-3 bg-light h-100">
                                <!-- Ícono según el tipo de dispositivo -->
                                <?php
                                $icon = match($device['device_type']) {
                                    'laptop'     => 'bi-laptop',
                                    'smartphone' => 'bi-phone',
                                    'tablet'     => 'bi-tablet',
                                    default      => 'bi-device-hdd'
                                };
                                $type_label = match($device['device_type']) {
                                    'laptop'     => 'Laptop',
                                    'smartphone' => 'Smartphone',
                                    'tablet'     => 'Tablet',
                                    default      => 'Otro'
                                };
                                ?>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="bi <?= $icon ?> fs-4 text-primary"></i>
                                    <div>
                                        <strong class="small">
                                            <?= htmlspecialchars($device['device_name'] ?? $type_label) ?>
                                        </strong>
                                        <br>
                                        <span class="badge bg-secondary small"><?= $type_label ?></span>
                                    </div>
                                </div>
                                <!-- Dirección MAC con fuente monoespaciada para mejor legibilidad -->
                                <div class="bg-dark text-success font-monospace small rounded p-2 text-center">
                                    <i class="bi bi-router me-1"></i>
                                    <?= htmlspecialchars($device['mac_address']) ?>
                                </div>
                                <p class="text-muted small mb-0 mt-2">
                                    Registrado: <?= date('H:i', strtotime($device['registered_at'])) ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
