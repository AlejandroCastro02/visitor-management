<?php
// ============================================================
// ARCHIVO: admin/users.php
// Descripción: Panel de administración de usuarios del sistema.
//
// Solo accesible para rol 'admin'.
// Muestra la lista de usuarios con opciones de:
//   - Crear nuevo usuario
//   - Editar usuario existente
//   - Activar / Desactivar cuenta
//   - Eliminar usuario (con restricciones de seguridad)
// ============================================================

$depth = 1;
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

// Solo administradores pueden acceder
requireRole('admin');

// ── Mensajes de retroalimentación desde redirects ────────────
$success = filter_input(INPUT_GET, 'success', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$error   = filter_input(INPUT_GET, 'error',   FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

$success_messages = [
    'created'  => 'Usuario creado exitosamente.',
    'updated'  => 'Usuario actualizado exitosamente.',
    'deleted'  => 'Usuario eliminado del sistema.',
    'enabled'  => 'Cuenta de usuario activada.',
    'disabled' => 'Cuenta de usuario desactivada.',
];

$error_messages = [
    'self_delete'    => 'No puedes eliminar tu propia cuenta.',
    'self_deactivate'=> 'No puedes desactivar tu propia cuenta.',
    'last_admin'     => 'No puedes eliminar o desactivar el último administrador.',
    'not_found'      => 'Usuario no encontrado.',
    'delete_failed'  => 'No se pudo eliminar el usuario. Verifica que no tenga registros asociados.',
    'invalid_request'=> 'Solicitud inválida. Intenta de nuevo.',
];

try {
    $db = getDB();

    // ── Búsqueda ─────────────────────────────────────────────
    $search = trim(filter_input(INPUT_GET, 'q', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $role_filter = trim(filter_input(INPUT_GET, 'role', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

    $conditions = [];
    $params     = [];

    if (!empty($search)) {
        $conditions[] = "(username LIKE ? OR email LIKE ?)";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
    }

    $valid_roles = ['admin', 'receptionist', 'guard'];
    if (!empty($role_filter) && in_array($role_filter, $valid_roles)) {
        $conditions[] = "role = ?";
        $params[] = $role_filter;
    }

    $where = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    $stmt = $db->prepare("
        SELECT id, username, email, role, active, created_at, last_login
        FROM users
        $where
        ORDER BY role ASC, username ASC
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error en admin/users.php: " . $e->getMessage());
    $users = [];
}

$page_title = 'Gestión de Usuarios';
$base_path  = '../';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── ENCABEZADO ────────────────────────────────────────── -->
<div class="row mb-4 align-items-center">
    <div class="col">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Gestión de Usuarios</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-0">
            <i class="bi bi-people-fill text-primary"></i> Gestión de Usuarios
        </h2>
        <p class="text-muted mt-1">Administración de cuentas del sistema</p>
    </div>
    <div class="col-auto">
        <a href="user_form.php" class="btn btn-primary">
            <i class="bi bi-person-plus-fill"></i> Nuevo Usuario
        </a>
    </div>
</div>

<!-- ── ALERTAS ───────────────────────────────────────────── -->
<?php if ($success && isset($success_messages[$success])): ?>
<div class="alert alert-success alert-dismissible d-flex align-items-center" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i>
    <?= htmlspecialchars($success_messages[$success]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error && isset($error_messages[$error])): ?>
<div class="alert alert-danger alert-dismissible d-flex align-items-center" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <?= htmlspecialchars($error_messages[$error]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ── FILTROS DE BÚSQUEDA ───────────────────────────────── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label fw-semibold small">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control"
                           placeholder="Nombre de usuario o email..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label fw-semibold small">Rol</label>
                <select name="role" class="form-select">
                    <option value="">Todos los roles</option>
                    <option value="admin"        <?= $role_filter === 'admin'        ? 'selected' : '' ?>>Administrador</option>
                    <option value="receptionist" <?= $role_filter === 'receptionist' ? 'selected' : '' ?>>Recepcionista</option>
                    <option value="guard"        <?= $role_filter === 'guard'        ? 'selected' : '' ?>>Vigilante</option>
                </select>
            </div>
            <div class="col-12 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <a href="users.php" class="btn btn-outline-secondary" title="Limpiar filtros">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ── TABLA DE USUARIOS ─────────────────────────────────── -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <span class="text-muted small">
            Total: <strong><?= count($users) ?></strong> usuario(s)
        </span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($users)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-person-x fs-1 d-block mb-2"></i>
                No se encontraron usuarios.
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Último Acceso</th>
                        <th>Creado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr class="<?= !$u['active'] ? 'table-secondary' : '' ?>">
                    <td class="text-muted small"><?= (int)$u['id'] ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <!-- Avatar inicial -->
                            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center
                                        justify-content-center text-primary fw-bold"
                                 style="width:36px;height:36px;font-size:.85rem">
                                <?= strtoupper(mb_substr($u['username'], 0, 1)) ?>
                            </div>
                            <div>
                                <strong><?= htmlspecialchars($u['username']) ?></strong>
                                <?php if ((int)$u['id'] === (int)$_SESSION['user_id']): ?>
                                    <span class="badge bg-info ms-1">Tú</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <small class="font-monospace"><?= htmlspecialchars($u['email']) ?></small>
                    </td>
                    <td>
                        <?php
                        $role_badges = [
                            'admin'        => ['bg-danger',  'bi-shield-fill',  'Administrador'],
                            'receptionist' => ['bg-primary', 'bi-person-badge', 'Recepcionista'],
                            'guard'        => ['bg-warning text-dark', 'bi-eye-fill', 'Vigilante'],
                        ];
                        [$cls, $icon, $label] = $role_badges[$u['role']] ?? ['bg-secondary', 'bi-person', $u['role']];
                        ?>
                        <span class="badge <?= $cls ?>">
                            <i class="bi <?= $icon ?>"></i> <?= $label ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($u['active']): ?>
                            <span class="badge bg-success"><i class="bi bi-circle-fill me-1" style="font-size:.5rem"></i>Activo</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><i class="bi bi-circle me-1" style="font-size:.5rem"></i>Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <small class="text-muted">
                            <?= $u['last_login']
                                ? date('d/m/Y H:i', strtotime($u['last_login']))
                                : 'Nunca' ?>
                        </small>
                    </td>
                    <td>
                        <small class="text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></small>
                    </td>
                    <td>
                        <div class="d-flex gap-1 justify-content-center">
                            <!-- Editar -->
                            <a href="user_form.php?id=<?= (int)$u['id'] ?>"
                               class="btn btn-sm btn-outline-primary" title="Editar usuario">
                                <i class="bi bi-pencil"></i>
                            </a>

                            <!-- Activar / Desactivar -->
                            <form method="POST" action="user_toggle.php" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <button type="submit"
                                        class="btn btn-sm <?= $u['active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                        title="<?= $u['active'] ? 'Desactivar cuenta' : 'Activar cuenta' ?>"
                                        onclick="return confirm('¿<?= $u['active'] ? 'Desactivar' : 'Activar' ?> este usuario?')">
                                    <i class="bi <?= $u['active'] ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                </button>
                            </form>

                            <!-- Eliminar -->
                            <form method="POST" action="user_delete.php" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <button type="submit"
                                        class="btn btn-sm btn-outline-danger"
                                        title="Eliminar usuario"
                                        onclick="return confirm('¿Eliminar permanentemente a <?= htmlspecialchars(addslashes($u['username'])) ?>?\n\nEsta acción no se puede deshacer.')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Generar CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once __DIR__ . '/../includes/footer.php';
?>
