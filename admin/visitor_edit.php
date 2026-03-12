<?php
// ============================================================
// ARCHIVO: admin/visitor_edit.php
// Descripción: Formulario de edición de datos de un visitante.
//
// Solo el administrador puede modificar datos de visitantes.
// Se edita el registro de la tabla `visitors` (datos personales),
// no la visita individual.
// ============================================================

$depth = 1;
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

requireRole('admin');

$visitor_id = (int)(filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? 0);

if ($visitor_id <= 0) {
    header("Location: ../visitors/list.php");
    exit();
}

try {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT id, full_name, id_number, email, phone, company
        FROM visitors
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$visitor_id]);
    $visitor = $stmt->fetch();

    if (!$visitor) {
        header("Location: ../visitors/list.php?error=not_found");
        exit();
    }

    // Contar visitas asociadas (informativo)
    $cnt = $db->prepare("SELECT COUNT(*) FROM visits WHERE visitor_id = ?");
    $cnt->execute([$visitor_id]);
    $visit_count = $cnt->fetchColumn();

} catch (PDOException $e) {
    error_log("Error en visitor_edit.php: " . $e->getMessage());
    header("Location: ../visitors/list.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$form_errors = $_SESSION['form_errors'] ?? [];
$form_data   = $_SESSION['form_data']   ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);

$page_title = 'Editar Visitante';
$base_path  = '../';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-12 col-lg-7">

<nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="../visitors/list.php">Visitantes</a></li>
        <li class="breadcrumb-item"><a href="../visitors/detail.php?id=<?= $visitor_id ?>">Detalle</a></li>
        <li class="breadcrumb-item active">Editar</li>
    </ol>
</nav>

<!-- Advertencia si tiene visitas activas -->
<?php if ($visit_count > 0): ?>
<div class="alert alert-info d-flex align-items-center mb-3">
    <i class="bi bi-info-circle-fill me-2"></i>
    Este visitante tiene <strong><?= $visit_count ?></strong> visita(s) registrada(s).
    Los cambios afectarán solo los datos personales del visitante.
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm border-top border-5 border-primary">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-person-lines-fill text-primary"></i> Editar Datos del Visitante
        </h5>
        <small class="text-muted">ID: <?= (int)$visitor['id'] ?></small>
    </div>

    <div class="card-body p-4">

        <?php if (!empty($form_errors)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Errores encontrados:</strong>
            <ul class="mb-0 mt-1">
                <?php foreach ($form_errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" action="visitor_save.php" novalidate>
            <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="visitor_id"  value="<?= (int)$visitor['id'] ?>">

            <div class="row g-3">

                <!-- Nombre completo -->
                <div class="col-12">
                    <label class="form-label fw-semibold" for="full_name">
                        <i class="bi bi-person"></i> Nombre Completo <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="full_name" name="full_name"
                           class="form-control"
                           value="<?= htmlspecialchars($form_data['full_name'] ?? $visitor['full_name']) ?>"
                           maxlength="150" required>
                </div>

                <!-- Número de identificación -->
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="id_number">
                        <i class="bi bi-card-text"></i> N° de Identificación <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="id_number" name="id_number"
                           class="form-control font-monospace"
                           value="<?= htmlspecialchars($form_data['id_number'] ?? $visitor['id_number']) ?>"
                           maxlength="30" required>
                    <div class="form-text">DNI, Cédula o Pasaporte. Debe ser único en el sistema.</div>
                </div>

                <!-- Email -->
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="email">
                        <i class="bi bi-envelope"></i> Correo Electrónico
                    </label>
                    <input type="email" id="email" name="email"
                           class="form-control"
                           value="<?= htmlspecialchars($form_data['email'] ?? $visitor['email'] ?? '') ?>"
                           maxlength="100">
                </div>

                <!-- Teléfono -->
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="phone">
                        <i class="bi bi-telephone"></i> Teléfono
                    </label>
                    <input type="text" id="phone" name="phone"
                           class="form-control"
                           value="<?= htmlspecialchars($form_data['phone'] ?? $visitor['phone'] ?? '') ?>"
                           maxlength="20">
                </div>

                <!-- Empresa -->
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="company">
                        <i class="bi bi-building"></i> Empresa
                    </label>
                    <input type="text" id="company" name="company"
                           class="form-control"
                           value="<?= htmlspecialchars($form_data['company'] ?? $visitor['company'] ?? '') ?>"
                           maxlength="100">
                </div>

            </div>

            <div class="d-flex gap-2 justify-content-end mt-4 pt-3 border-top">
                <a href="../visitors/detail.php?id=<?= (int)$visitor['id'] ?>"
                   class="btn btn-outline-secondary px-4">
                    <i class="bi bi-x"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-save"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
