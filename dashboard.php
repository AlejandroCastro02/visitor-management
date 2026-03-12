<?php
// ============================================================
// ARCHIVO: dashboard.php
// Descripción: Página principal del sistema (protegida).
//
// Muestra un resumen del día:
//  - Visitas activas (personas que están dentro ahora)
//  - Total de visitas hoy
//  - Dispositivos registrados hoy
//  - Últimas 10 visitas del día
// ============================================================

// Profundidad desde la raíz (0 = estamos en la raíz)
$depth = 0;

// ── PROTECCIÓN: Verificar sesión ─────────────────────────────
// Si no hay sesión activa, este archivo redirige al login
require_once __DIR__ . '/auth/auth_check.php';

// ── Conexión a la BD ─────────────────────────────────────────
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();

    // ── Query 1: Visitas activas ahora mismo ─────────────────
    // DATE(entry_time) = CURDATE() → solo las de hoy
    // status = 'active' → que no hayan salido
    $stmt = $db->query("
        SELECT COUNT(*) as total
        FROM visits
        WHERE status = 'active'
          AND DATE(entry_time) = CURDATE()
    ");
    $active_visits = $stmt->fetch()['total'];

    // ── Query 2: Total de visitas del día ─────────────────────
    $stmt = $db->query("
        SELECT COUNT(*) as total
        FROM visits
        WHERE DATE(entry_time) = CURDATE()
    ");
    $total_today = $stmt->fetch()['total'];

    // ── Query 3: Total de dispositivos registrados hoy ────────
    $stmt = $db->query("
        SELECT COUNT(*) as total
        FROM devices d
        JOIN visits v ON d.visit_id = v.id
        WHERE DATE(v.entry_time) = CURDATE()
    ");
    $total_devices = $stmt->fetch()['total'];

    // ── Query 4: Últimas 10 visitas del día con JOIN ──────────
    // JOIN une las tablas visits + visitors para obtener el nombre
    // ORDER BY entry_time DESC → las más recientes primero
    $stmt = $db->query("
        SELECT
            v.id,
            vis.full_name,
            vis.id_number,
            v.host_name,
            v.host_department,
            v.reason,
            v.entry_time,
            v.exit_time,
            v.status,
            COUNT(d.id) as device_count
        FROM visits v
        JOIN visitors vis ON v.visitor_id = vis.id
        LEFT JOIN devices d ON d.visit_id = v.id
        WHERE DATE(v.entry_time) = CURDATE()
        GROUP BY v.id
        ORDER BY v.entry_time DESC
        LIMIT 10
    ");
    $recent_visits = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error en dashboard: " . $e->getMessage());
    $active_visits = $total_today = $total_devices = 0;
    $recent_visits = [];
}

// ── Variables para el header ─────────────────────────────────
$page_title = 'Dashboard';
$base_path  = '';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ── TÍTULO DE LA PÁGINA ────────────────────────────────── -->
<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold text-dark">
            <i class="bi bi-speedometer2 text-primary"></i> Dashboard
        </h2>
        <p class="text-muted mb-0">
            <!-- date() formatea la fecha actual en español -->
            Resumen del día — <?= date('d/m/Y') ?>
            &nbsp;|&nbsp;
            <!-- Bienvenida personalizada con el nombre del usuario logueado -->
            Bienvenido, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong>
        </p>
    </div>
    <!-- Botón de acción rápida -->
    <div class="col-auto d-flex align-items-center">
        <?php if (canRegisterVisit()): ?>
        <a href="visitors/register.php" class="btn btn-primary">
            <i class="bi bi-person-plus-fill"></i> Nueva Visita
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ── TARJETAS DE MÉTRICAS ───────────────────────────────── -->
<div class="row g-4 mb-4">

    <!-- Tarjeta: Visitas Activas (personas dentro ahora) -->
    <div class="col-12 col-sm-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100 border-start border-5 border-success">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-success bg-opacity-10 rounded-circle p-3">
                    <i class="bi bi-door-open fs-2 text-success"></i>
                </div>
                <div>
                    <!-- Mostrar el número de la query, sanitizado -->
                    <h2 class="mb-0 fw-bold"><?= (int)$active_visits ?></h2>
                    <p class="text-muted mb-0 small">Visitas activas ahora</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjeta: Total de visitas del día -->
    <div class="col-12 col-sm-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100 border-start border-5 border-primary">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                    <i class="bi bi-people fs-2 text-primary"></i>
                </div>
                <div>
                    <h2 class="mb-0 fw-bold"><?= (int)$total_today ?></h2>
                    <p class="text-muted mb-0 small">Visitas registradas hoy</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjeta: Dispositivos registrados -->
    <div class="col-12 col-sm-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100 border-start border-5 border-warning">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                    <i class="bi bi-laptop fs-2 text-warning"></i>
                </div>
                <div>
                    <h2 class="mb-0 fw-bold"><?= (int)$total_devices ?></h2>
                    <p class="text-muted mb-0 small">Dispositivos registrados hoy</p>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ── TABLA DE VISITAS RECIENTES ─────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-clock-history text-primary"></i> Visitas de Hoy
        </h5>
        <a href="visitors/list.php" class="btn btn-sm btn-outline-primary">
            Ver todas <i class="bi bi-arrow-right"></i>
        </a>
    </div>

    <div class="card-body p-0">
        <?php if (empty($recent_visits)): ?>
            <!-- Estado vacío: no hay visitas hoy -->
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                No hay visitas registradas hoy.
                <br>
                <a href="visitors/register.php" class="btn btn-primary mt-3">
                    Registrar primera visita
                </a>
            </div>
        <?php else: ?>
            <!-- Tabla responsiva con las visitas -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Visitante</th>
                            <th>Visita a</th>
                            <th>Motivo</th>
                            <th>Entrada</th>
                            <th>Salida</th>
                            <th>Dispositivos</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Iterar sobre cada visita del resultado SQL -->
                        <?php foreach ($recent_visits as $visit): ?>
                        <tr>
                            <td>
                                <!-- htmlspecialchars en TODOS los datos de la BD para prevenir XSS -->
                                <strong><?= htmlspecialchars($visit['full_name']) ?></strong>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($visit['id_number']) ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($visit['host_name']) ?>
                                <?php if ($visit['host_department']): ?>
                                    <br>
                                    <small class="text-muted"><?= htmlspecialchars($visit['host_department']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <!-- Limitar el texto largo con Bootstrap -->
                                <span title="<?= htmlspecialchars($visit['reason']) ?>">
                                    <?= htmlspecialchars(mb_strimwidth($visit['reason'], 0, 30, '...')) ?>
                                </span>
                            </td>
                            <td>
                                <small><?= date('H:i', strtotime($visit['entry_time'])) ?></small>
                            </td>
                            <td>
                                <small>
                                    <?= $visit['exit_time']
                                        ? date('H:i', strtotime($visit['exit_time']))
                                        : '<span class="text-success">Aún dentro</span>'
                                    ?>
                                </small>
                            </td>
                            <td class="text-center">
                                <!-- Badge con número de dispositivos -->
                                <span class="badge bg-secondary rounded-pill">
                                    <i class="bi bi-laptop"></i>
                                    <?= (int)$visit['device_count'] ?>
                                </span>
                            </td>
                            <td>
                                <!-- Badge de estado con color según el valor -->
                                <?php
                                $badge_class = match($visit['status']) {
                                    'active'    => 'bg-success',
                                    'completed' => 'bg-secondary',
                                    'cancelled' => 'bg-danger',
                                    default     => 'bg-light text-dark'
                                };
                                $badge_text = match($visit['status']) {
                                    'active'    => 'Activa',
                                    'completed' => 'Completada',
                                    'cancelled' => 'Cancelada',
                                    default     => $visit['status']
                                };
                                ?>
                                <span class="badge <?= $badge_class ?>">
                                    <?= $badge_text ?>
                                </span>
                            </td>
                            <td>
                                <!-- Botón de detalle -->
                                <a href="visitors/detail.php?id=<?= (int)$visit['id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="Ver detalle">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <!-- Botón de marcar salida (solo si está activa) -->
                                <?php if ($visit['status'] === 'active'): ?>
                                <a href="visitors/exit.php?id=<?= (int)$visit['id'] ?>"
                                   class="btn btn-sm btn-outline-warning" title="Marcar salida">
                                    <i class="bi bi-door-closed"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
