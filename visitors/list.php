<?php
// ============================================================
// ARCHIVO: visitors/list.php
// Descripción: Lista todas las visitas con búsqueda y filtros.
//
// Funcionalidades:
//  - Búsqueda por nombre, cédula o motivo (LIKE en SQL)
//  - Filtro por estado (activa/completada/cancelada)
//  - Paginación (10 registros por página)
// ============================================================

$depth = 1;
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

// ── Leer parámetros de búsqueda y filtro (GET) ───────────────
// Sanitizar los inputs de búsqueda para evitar XSS
$search = trim(filter_input(INPUT_GET, 'q',      FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$status = trim(filter_input(INPUT_GET, 'status', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
$page   = max(1, (int)filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);

$per_page = 10; // Registros por página
$offset   = ($page - 1) * $per_page;

try {
    $db = getDB();

    // ── Construir query dinámicamente según filtros ───────────
    // Usamos un array de condiciones y params para agregar WHERE dinámicamente
    $conditions = [];
    $params     = [];

    if (!empty($search)) {
        // LIKE con % en ambos lados = búsqueda en cualquier posición del string
        // Se busca en nombre, cédula y motivo simultáneamente con OR
        $conditions[] = "(vis.full_name LIKE ? OR vis.id_number LIKE ? OR v.reason LIKE ?)";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if (!empty($status) && in_array($status, ['active','completed','cancelled'])) {
        $conditions[] = "v.status = ?";
        $params[] = $status;
    }

    // Construir cláusula WHERE
    $where = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // ── Contar total de resultados para la paginación ─────────
    $count_stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM visits v
        JOIN visitors vis ON v.visitor_id = vis.id
        $where
    ");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages   = ceil($total_records / $per_page);

    // ── Query principal con paginación ───────────────────────
    // LIMIT y OFFSET no pueden ir como parámetros PDO (es limitación de PDO)
    // por eso los casteamos a int para evitar inyección
    $stmt = $db->prepare("
        SELECT
            v.id,
            vis.full_name,
            vis.id_number,
            vis.company,
            v.host_name,
            v.host_department,
            v.reason,
            v.entry_time,
            v.exit_time,
            v.status,
            u.username as registered_by_name,
            COUNT(d.id) as device_count
        FROM visits v
        JOIN visitors vis ON v.visitor_id = vis.id
        JOIN users u ON v.registered_by = u.id
        LEFT JOIN devices d ON d.visit_id = v.id
        $where
        GROUP BY v.id
        ORDER BY v.entry_time DESC
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $visits = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error en list.php: " . $e->getMessage());
    $visits = [];
    $total_records = 0;
    $total_pages = 1;
}

$page_title = 'Lista de Visitantes';
$base_path  = '../';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── TÍTULO Y BOTÓN DE ACCIÓN ──────────────────────────── -->
<div class="row mb-4 align-items-center">
    <div class="col">
        <h2 class="fw-bold">
            <i class="bi bi-people text-primary"></i> Registro de Visitantes
        </h2>
        <p class="text-muted">
            Total de registros: <strong><?= number_format($total_records) ?></strong>
        </p>
    </div>
    <div class="col-auto">
        <a href="register.php" class="btn btn-primary">
            <i class="bi bi-person-plus"></i> Nueva Visita
        </a>
    </div>
</div>

<!-- ── FORMULARIO DE BÚSQUEDA Y FILTROS ─────────────────── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <!-- GET es correcto para búsqueda (los filtros quedan en la URL, se puede compartir) -->
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold small">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control"
                           placeholder="Nombre, cédula o motivo..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label fw-semibold small">Estado</label>
                <select name="status" class="form-select">
                    <option value="">Todos los estados</option>
                    <option value="active"    <?= $status==='active'    ? 'selected' : '' ?>>Activas</option>
                    <option value="completed" <?= $status==='completed' ? 'selected' : '' ?>>Completadas</option>
                    <option value="cancelled" <?= $status==='cancelled' ? 'selected' : '' ?>>Canceladas</option>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <a href="list.php" class="btn btn-outline-secondary" title="Limpiar filtros">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ── TABLA DE RESULTADOS ───────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($visits)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-search fs-1 d-block mb-2"></i>
                No se encontraron registros.
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Visitante</th>
                        <th>Empresa</th>
                        <th>Visita a</th>
                        <th>Entrada</th>
                        <th>Salida</th>
                        <th>Dispositivos</th>
                        <th>Estado</th>
                        <th>Registrado por</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visits as $visit): ?>
                    <tr>
                        <td class="text-muted small"><?= (int)$visit['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($visit['full_name']) ?></strong><br>
                            <small class="text-muted font-monospace"><?= htmlspecialchars($visit['id_number']) ?></small>
                        </td>
                        <td>
                            <small><?= htmlspecialchars($visit['company'] ?? '—') ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($visit['host_name']) ?>
                            <?php if ($visit['host_department']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($visit['host_department']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small><?= date('d/m H:i', strtotime($visit['entry_time'])) ?></small>
                        </td>
                        <td>
                            <small>
                                <?= $visit['exit_time']
                                    ? date('d/m H:i', strtotime($visit['exit_time']))
                                    : '<span class="text-success fw-bold">Dentro</span>'
                                ?>
                            </small>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary">
                                <i class="bi bi-laptop"></i> <?= (int)$visit['device_count'] ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            echo match($visit['status']) {
                                'active'    => '<span class="badge bg-success">Activa</span>',
                                'completed' => '<span class="badge bg-secondary">Completada</span>',
                                'cancelled' => '<span class="badge bg-danger">Cancelada</span>',
                                default     => '<span class="badge bg-light text-dark">' . htmlspecialchars($visit['status']) . '</span>'
                            };
                            ?>
                        </td>
                        <td>
                            <small class="text-muted"><?= htmlspecialchars($visit['registered_by_name']) ?></small>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="detail.php?id=<?= (int)$visit['id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="Ver detalle">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if ($visit['status'] === 'active'): ?>
                                <a href="exit.php?id=<?= (int)$visit['id'] ?>"
                                   class="btn btn-sm btn-outline-warning" title="Registrar salida">
                                    <i class="bi bi-door-closed"></i>
                                </a>
                                <?php endif; ?>
                            </div>
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
                de <?= $total_records ?> registros
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link"
                           href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
