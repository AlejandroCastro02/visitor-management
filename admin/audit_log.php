<?php
// ============================================================
// ARCHIVO: admin/audit_log.php
// Descripción: Bitácora de auditoría del sistema.
//
// Solo accesible para administradores.
// Muestra el registro completo de todas las acciones con:
//   - Filtros por: usuario, tipo de acción, entidad, fecha
//   - Paginación (25 registros por página)
//   - Vista detallada de cada evento
// ============================================================

$depth = 1;
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

requireRole('admin');

// ── Parámetros de búsqueda ────────────────────────────────────
$search      = trim(filter_input(INPUT_GET, 'q',           FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$filter_action  = trim(filter_input(INPUT_GET, 'action',   FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$filter_entity  = trim(filter_input(INPUT_GET, 'entity',   FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$filter_user    = trim(filter_input(INPUT_GET, 'user',     FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$date_from   = trim(filter_input(INPUT_GET, 'date_from',   FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$date_to     = trim(filter_input(INPUT_GET, 'date_to',     FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$page        = max(1, (int)(filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1));

$per_page = 25;
$offset   = ($page - 1) * $per_page;

try {
    $db = getDB();

    // ── Construir WHERE dinámicamente ────────────────────────
    $conditions = [];
    $params     = [];

    if (!empty($search)) {
        $conditions[] = "(al.description LIKE ? OR al.user_name LIKE ?)";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
    }

    $valid_actions = ['CREATE_USER','UPDATE_USER','DELETE_USER','TOGGLE_USER',
                      'CREATE_VISITOR','UPDATE_VISITOR','DELETE_VISITOR',
                      'CREATE_VISIT','EXIT_VISIT','CANCEL_VISIT'];
    if (!empty($filter_action) && in_array($filter_action, $valid_actions)) {
        $conditions[] = "al.action = ?";
        $params[] = $filter_action;
    }

    $valid_entities = ['user','visitor','visit','device','system'];
    if (!empty($filter_entity) && in_array($filter_entity, $valid_entities)) {
        $conditions[] = "al.entity_type = ?";
        $params[] = $filter_entity;
    }

    if (!empty($filter_user) && is_numeric($filter_user)) {
        $conditions[] = "al.user_id = ?";
        $params[] = (int)$filter_user;
    }

    if (!empty($date_from) && strtotime($date_from)) {
        $conditions[] = "DATE(al.created_at) >= ?";
        $params[] = $date_from;
    }

    if (!empty($date_to) && strtotime($date_to)) {
        $conditions[] = "DATE(al.created_at) <= ?";
        $params[] = $date_to;
    }

    $where = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // ── Contar total ─────────────────────────────────────────
    $count_stmt = $db->prepare("SELECT COUNT(*) FROM audit_log al $where");
    $count_stmt->execute($params);
    $total_records = (int)$count_stmt->fetchColumn();
    $total_pages   = max(1, (int)ceil($total_records / $per_page));

    // ── Query principal ───────────────────────────────────────
    $stmt = $db->prepare("
        SELECT
            al.id,
            al.user_id,
            al.user_name,
            al.user_role,
            al.action,
            al.entity_type,
            al.entity_id,
            al.description,
            al.ip_address,
            al.created_at
        FROM audit_log al
        $where
        ORDER BY al.created_at DESC
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // ── Lista de usuarios para el filtro ─────────────────────
    $users_stmt = $db->query("SELECT DISTINCT user_id, user_name FROM audit_log WHERE user_id IS NOT NULL ORDER BY user_name");
    $audit_users = $users_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error en audit_log.php: " . $e->getMessage());
    $logs = [];
    $total_records = 0;
    $total_pages   = 1;
    $audit_users   = [];
}

$page_title = 'Bitácora de Auditoría';
$base_path  = '../';
require_once __DIR__ . '/../includes/header.php';

// Construir query string para paginación (conservar filtros)
$qs_parts = [];
if ($search)        $qs_parts[] = 'q='        . urlencode($search);
if ($filter_action) $qs_parts[] = 'action='   . urlencode($filter_action);
if ($filter_entity) $qs_parts[] = 'entity='   . urlencode($filter_entity);
if ($filter_user)   $qs_parts[] = 'user='     . urlencode($filter_user);
if ($date_from)     $qs_parts[] = 'date_from='. urlencode($date_from);
if ($date_to)       $qs_parts[] = 'date_to='  . urlencode($date_to);
$base_qs = !empty($qs_parts) ? implode('&', $qs_parts) . '&' : '';
?>

<!-- ── ENCABEZADO ────────────────────────────────────────── -->
<div class="row mb-4 align-items-center">
    <div class="col">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Bitácora de Auditoría</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-0">
            <i class="bi bi-journal-text text-primary"></i> Bitácora de Auditoría
        </h2>
        <p class="text-muted mt-1">
            Registro completo de todas las acciones del sistema.
            Total: <strong><?= number_format($total_records) ?></strong> evento(s).
        </p>
    </div>
</div>

<!-- ── PANEL DE FILTROS ──────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-funnel"></i> Filtros de Búsqueda
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <!-- Búsqueda libre -->
            <div class="col-12 col-md-4">
                <label class="form-label small fw-semibold">Búsqueda libre</label>
                <input type="text" name="q" class="form-control"
                       placeholder="Descripción o usuario..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>

            <!-- Tipo de acción -->
            <div class="col-12 col-md-2">
                <label class="form-label small fw-semibold">Acción</label>
                <select name="action" class="form-select">
                    <option value="">Todas</option>
                    <optgroup label="Usuarios">
                        <option value="CREATE_USER"  <?= $filter_action==='CREATE_USER'  ? 'selected':'' ?>>Creó usuario</option>
                        <option value="UPDATE_USER"  <?= $filter_action==='UPDATE_USER'  ? 'selected':'' ?>>Actualizó usuario</option>
                        <option value="DELETE_USER"  <?= $filter_action==='DELETE_USER'  ? 'selected':'' ?>>Eliminó usuario</option>
                        <option value="TOGGLE_USER"  <?= $filter_action==='TOGGLE_USER'  ? 'selected':'' ?>>Cambió estado</option>
                    </optgroup>
                    <optgroup label="Visitantes">
                        <option value="CREATE_VISITOR" <?= $filter_action==='CREATE_VISITOR' ? 'selected':'' ?>>Registró visitante</option>
                        <option value="UPDATE_VISITOR" <?= $filter_action==='UPDATE_VISITOR' ? 'selected':'' ?>>Actualizó visitante</option>
                        <option value="DELETE_VISITOR" <?= $filter_action==='DELETE_VISITOR' ? 'selected':'' ?>>Eliminó visitante</option>
                    </optgroup>
                    <optgroup label="Visitas">
                        <option value="CREATE_VISIT" <?= $filter_action==='CREATE_VISIT' ? 'selected':'' ?>>Registró visita</option>
                        <option value="EXIT_VISIT"   <?= $filter_action==='EXIT_VISIT'   ? 'selected':'' ?>>Registró salida</option>
                    </optgroup>
                </select>
            </div>

            <!-- Entidad -->
            <div class="col-12 col-md-2">
                <label class="form-label small fw-semibold">Entidad</label>
                <select name="entity" class="form-select">
                    <option value="">Todas</option>
                    <option value="user"    <?= $filter_entity==='user'    ? 'selected':'' ?>>Usuario</option>
                    <option value="visitor" <?= $filter_entity==='visitor' ? 'selected':'' ?>>Visitante</option>
                    <option value="visit"   <?= $filter_entity==='visit'   ? 'selected':'' ?>>Visita</option>
                </select>
            </div>

            <!-- Usuario que ejecutó -->
            <div class="col-12 col-md-2">
                <label class="form-label small fw-semibold">Ejecutado por</label>
                <select name="user" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($audit_users as $au): ?>
                    <option value="<?= (int)$au['user_id'] ?>"
                            <?= (string)$filter_user === (string)$au['user_id'] ? 'selected':'' ?>>
                        <?= htmlspecialchars($au['user_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Rango de fechas -->
            <div class="col-12 col-md-2">
                <label class="form-label small fw-semibold">Desde</label>
                <input type="date" name="date_from" class="form-control"
                       value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small fw-semibold">Hasta</label>
                <input type="date" name="date_to" class="form-control"
                       value="<?= htmlspecialchars($date_to) ?>">
            </div>

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Buscar
                </button>
                <a href="audit_log.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Limpiar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ── TABLA DEL LOG ─────────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
                No se encontraron eventos con los filtros aplicados.
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-3">Timestamp</th>
                        <th>Ejecutado por</th>
                        <th>Acción</th>
                        <th>Entidad</th>
                        <th>ID Afectado</th>
                        <th>Descripción</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log):
                    $action_info = getActionLabel($log['action']);
                    $entity_label = getEntityLabel($log['entity_type']);
                ?>
                <tr>
                    <!-- Timestamp -->
                    <td class="ps-3 text-nowrap">
                        <span class="font-monospace text-muted">
                            <?= date('d/m/Y', strtotime($log['created_at'])) ?>
                        </span>
                        <br>
                        <strong class="font-monospace">
                            <?= date('H:i:s', strtotime($log['created_at'])) ?>
                        </strong>
                    </td>

                    <!-- Quién -->
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-secondary bg-opacity-10
                                        d-flex align-items-center justify-content-center"
                                 style="width:28px;height:28px;font-size:.75rem">
                                <?= strtoupper(mb_substr($log['user_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <strong><?= htmlspecialchars($log['user_name']) ?></strong>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($log['user_role']) ?></small>
                            </div>
                        </div>
                    </td>

                    <!-- Qué acción -->
                    <td>
                        <span class="badge bg-<?= $action_info['color'] ?>">
                            <?= $action_info['label'] ?>
                        </span>
                    </td>

                    <!-- Entidad -->
                    <td>
                        <span class="badge bg-light text-dark border">
                            <?= htmlspecialchars($entity_label) ?>
                        </span>
                    </td>

                    <!-- ID Afectado -->
                    <td class="text-center">
                        <?php if ($log['entity_id']): ?>
                            <code class="small">#<?= (int)$log['entity_id'] ?></code>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Descripción -->
                    <td style="max-width:320px">
                        <span title="<?= htmlspecialchars($log['description']) ?>">
                            <?= htmlspecialchars(mb_strimwidth($log['description'], 0, 80, '...')) ?>
                        </span>
                    </td>

                    <!-- IP -->
                    <td>
                        <code class="small text-muted"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></code>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ── PAGINACIÓN ──────────────────────────────────── -->
        <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-3 py-3 border-top">
            <small class="text-muted">
                Mostrando <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_records) ?>
                de <?= number_format($total_records) ?> eventos
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <!-- Anterior -->
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= $base_qs ?>page=<?= $page - 1 ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <!-- Páginas -->
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page   = min($total_pages, $page + 2);
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= $base_qs ?>page=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <!-- Siguiente -->
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= $base_qs ?>page=<?= $page + 1 ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
