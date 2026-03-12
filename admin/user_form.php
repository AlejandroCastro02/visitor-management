<?php
// ============================================================
// ARCHIVO: admin/user_form.php
// Descripción: Formulario para Crear y Editar usuarios.
//
// GET  sin ?id  → Modo CREAR: formulario vacío
// GET  con ?id  → Modo EDITAR: formulario pre-poblado
// POST          → Procesa el guardado (redirige a user_save.php)
// ============================================================

$depth = 1;
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

requireRole('admin');

$edit_id = (int)(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? 0);
$is_edit = $edit_id > 0;
$user    = null;

if ($is_edit) {
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, username, email, role, active FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$edit_id]);
        $user = $stmt->fetch();

        if (!$user) {
            header("Location: users.php?error=not_found");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error en user_form.php: " . $e->getMessage());
        header("Location: users.php?error=not_found");
        exit();
    }
}

// Generar CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Errores de validación del intento previo (pasados por sesión)
$form_errors = $_SESSION['form_errors'] ?? [];
$form_data   = $_SESSION['form_data']   ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);

$page_title = $is_edit ? 'Editar Usuario' : 'Nuevo Usuario';
$base_path  = '../';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-12 col-lg-7">

<!-- ── ENCABEZADO ────────────────────────────────────────── -->
<nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="users.php">Gestión de Usuarios</a></li>
        <li class="breadcrumb-item active"><?= $is_edit ? 'Editar' : 'Nuevo' ?> Usuario</li>
    </ol>
</nav>

<div class="card border-0 shadow-sm border-top border-5 <?= $is_edit ? 'border-primary' : 'border-success' ?>">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold">
            <i class="bi <?= $is_edit ? 'bi-pencil-square text-primary' : 'bi-person-plus-fill text-success' ?>"></i>
            <?= $is_edit ? 'Editar Usuario' : 'Crear Nuevo Usuario' ?>
        </h5>
        <?php if ($is_edit): ?>
            <small class="text-muted">ID: <?= (int)$user['id'] ?> &mdash; <?= htmlspecialchars($user['username']) ?></small>
        <?php endif; ?>
    </div>

    <div class="card-body p-4">

        <!-- Errores de validación -->
        <?php if (!empty($form_errors)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Revisa los siguientes errores:</strong>
            <ul class="mb-0 mt-1">
                <?php foreach ($form_errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" action="user_save.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="user_id"    value="<?= $is_edit ? (int)$user['id'] : '' ?>">
            <input type="hidden" name="mode"       value="<?= $is_edit ? 'edit' : 'create' ?>">

            <div class="row g-3">

                <!-- Nombre de usuario -->
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="username">
                        <i class="bi bi-person"></i> Nombre de Usuario <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="username" name="username"
                           class="form-control <?= isset($form_errors['username']) ? 'is-invalid' : '' ?>"
                           value="<?= htmlspecialchars($form_data['username'] ?? $user['username'] ?? '') ?>"
                           maxlength="50" required
                           placeholder="ej: jlopez">
                    <div class="form-text">Solo letras, números, guión y guión bajo.</div>
                </div>

                <!-- Email -->
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="email">
                        <i class="bi bi-envelope"></i> Correo Electrónico <span class="text-danger">*</span>
                    </label>
                    <input type="email" id="email" name="email"
                           class="form-control"
                           value="<?= htmlspecialchars($form_data['email'] ?? $user['email'] ?? '') ?>"
                           maxlength="100" required
                           placeholder="usuario@empresa.com">
                </div>

                <!-- Rol -->
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="role">
                        <i class="bi bi-shield"></i> Rol del Sistema <span class="text-danger">*</span>
                    </label>
                    <select id="role" name="role" class="form-select" required>
                        <?php
                        $current_role = $form_data['role'] ?? $user['role'] ?? 'receptionist';
                        $roles = [
                            'receptionist' => 'Recepcionista — Registra y gestiona visitas',
                            'guard'        => 'Vigilante — Consulta y registra salidas',
                            'admin'        => 'Administrador — Acceso total al sistema',
                        ];
                        foreach ($roles as $value => $label):
                        ?>
                        <option value="<?= $value ?>" <?= $current_role === $value ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text text-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        El rol Administrador tiene acceso completo. Asignar con precaución.
                    </div>
                </div>

                <!-- Estado (solo en edición) -->
                <?php if ($is_edit): ?>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-toggle-on"></i> Estado de la Cuenta
                    </label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="active" name="active" value="1"
                               <?= ($form_data['active'] ?? $user['active']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="active">Cuenta activa</label>
                    </div>
                    <div class="form-text">Desactivar impide el acceso sin borrar el historial.</div>
                </div>
                <?php endif; ?>

                <!-- Contraseña -->
                <div class="col-12">
                    <hr class="my-1">
                    <label class="form-label fw-semibold" for="password">
                        <i class="bi bi-lock"></i> Contraseña
                        <?= $is_edit ? '' : '<span class="text-danger">*</span>' ?>
                    </label>
                    <?php if ($is_edit): ?>
                        <p class="text-muted small mb-2">
                            <i class="bi bi-info-circle"></i>
                            Deja en blanco para mantener la contraseña actual.
                        </p>
                    <?php endif; ?>
                    <div class="input-group">
                        <input type="password" id="password" name="password"
                               class="form-control"
                               minlength="8"
                               <?= !$is_edit ? 'required' : '' ?>
                               placeholder="<?= $is_edit ? 'Nueva contraseña (opcional)' : 'Mínimo 8 caracteres' ?>"
                               autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary" id="togglePwd">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="form-text">
                        Requerimientos: mínimo 8 caracteres, una mayúscula, una minúscula y un número.
                    </div>
                </div>

                <!-- Confirmar contraseña -->
                <div class="col-12">
                    <label class="form-label fw-semibold" for="password_confirm">
                        <i class="bi bi-lock-fill"></i> Confirmar Contraseña
                        <?= $is_edit ? '' : '<span class="text-danger">*</span>' ?>
                    </label>
                    <input type="password" id="password_confirm" name="password_confirm"
                           class="form-control"
                           placeholder="Repetir contraseña"
                           autocomplete="new-password">
                </div>

            </div><!-- /.row -->

            <!-- Botones -->
            <div class="d-flex gap-2 justify-content-end mt-4 pt-3 border-top">
                <a href="users.php" class="btn btn-outline-secondary px-4">
                    <i class="bi bi-x"></i> Cancelar
                </a>
                <button type="submit" class="btn <?= $is_edit ? 'btn-primary' : 'btn-success' ?> px-4">
                    <i class="bi <?= $is_edit ? 'bi-save' : 'bi-person-plus-fill' ?>"></i>
                    <?= $is_edit ? 'Guardar Cambios' : 'Crear Usuario' ?>
                </button>
            </div>
        </form>
    </div>
</div>
</div><!-- /.col -->
</div><!-- /.row -->

<script>
// Toggle mostrar/ocultar contraseña
document.getElementById('togglePwd').addEventListener('click', function () {
    const pwd  = document.getElementById('password');
    const icon = this.querySelector('i');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        pwd.type = 'password';
        icon.className = 'bi bi-eye';
    }
});

// Validación cliente: contraseñas coinciden
document.querySelector('form').addEventListener('submit', function (e) {
    const pwd     = document.getElementById('password').value;
    const confirm = document.getElementById('password_confirm').value;

    if (pwd && pwd !== confirm) {
        e.preventDefault();
        document.getElementById('password_confirm').classList.add('is-invalid');
        // Insertar mensaje si no existe
        if (!document.getElementById('pwd-mismatch')) {
            const div = document.createElement('div');
            div.id = 'pwd-mismatch';
            div.className = 'invalid-feedback d-block';
            div.textContent = 'Las contraseñas no coinciden.';
            document.getElementById('password_confirm').after(div);
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
