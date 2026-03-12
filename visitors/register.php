<?php
// ============================================================
// ARCHIVO: visitors/register.php
// Descripción: Registro de nueva visita con dispositivos.
// ACTUALIZACIÓN: Integración con audit_log al crear visita.
// ============================================================

$depth = 1;
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

// Vigilantes no pueden registrar visitas, solo consultarlas y marcar salidas
requireAnyRole(['admin', 'receptionist']);

$errors   = [];
$success  = false;
$new_visit_id = null;

// ── Procesar POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verificar CSRF
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Solicitud inválida. Recarga la página.';
    } else {

        $db = getDB();

        // ── Recoger datos del visitante ───────────────────────
        $full_name  = trim(filter_input(INPUT_POST, 'full_name',  FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $id_number  = trim(filter_input(INPUT_POST, 'id_number',  FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $visitor_email = trim(filter_input(INPUT_POST, 'visitor_email', FILTER_SANITIZE_EMAIL) ?? '');
        $phone      = trim(filter_input(INPUT_POST, 'phone',      FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $company    = trim(filter_input(INPUT_POST, 'company',    FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

        // ── Recoger datos de la visita ────────────────────────
        $host_name       = trim(filter_input(INPUT_POST, 'host_name',       FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $host_department = trim(filter_input(INPUT_POST, 'host_department',  FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $reason          = trim(filter_input(INPUT_POST, 'reason',           FILTER_DEFAULT) ?? '');
        $notes           = trim(filter_input(INPUT_POST, 'notes',            FILTER_DEFAULT) ?? '');

        // ── Validaciones ──────────────────────────────────────
        if (empty($full_name)) $errors[] = 'El nombre del visitante es obligatorio.';
        if (empty($id_number)) $errors[] = 'El número de identificación es obligatorio.';
        if (empty($host_name)) $errors[] = 'El nombre del empleado a visitar es obligatorio.';
        if (empty($reason))    $errors[] = 'El motivo de la visita es obligatorio.';

        // Validar MACs de dispositivos
        $devices_raw = $_POST['devices'] ?? [];
        $mac_regex   = '/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/';
        $devices_valid = [];

        if (is_array($devices_raw)) {
            foreach ($devices_raw as $i => $device) {
                $mac = trim($device['mac_address'] ?? '');
                $type = $device['device_type'] ?? 'laptop';
                $name = trim($device['device_name'] ?? '');

                if (empty($mac)) continue; // Dispositivo vacío, omitir

                if (!preg_match($mac_regex, strtoupper($mac))) {
                    $errors[] = "Dispositivo " . ($i + 1) . ": MAC inválida ($mac). Formato: AA:BB:CC:DD:EE:FF";
                } elseif (!in_array($type, ['laptop','smartphone','tablet','other'])) {
                    $errors[] = "Dispositivo " . ($i + 1) . ": tipo inválido.";
                } else {
                    $devices_valid[] = [
                        'mac_address' => strtoupper($mac),
                        'device_type' => $type,
                        'device_name' => $name,
                    ];
                }
            }
        }

        // ── Si no hay errores, guardar en BD ──────────────────
        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // 1. Buscar o crear el visitante por id_number
                $stmt = $db->prepare("SELECT id FROM visitors WHERE id_number = ? LIMIT 1");
                $stmt->execute([$id_number]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $visitor_id = $existing['id'];
                    // Actualizar datos en caso de que hayan cambiado
                    $stmt = $db->prepare("
                        UPDATE visitors SET full_name=?, email=?, phone=?, company=?
                        WHERE id=?
                    ");
                    $stmt->execute([
                        $full_name,
                        $visitor_email ?: null,
                        $phone ?: null,
                        $company ?: null,
                        $visitor_id
                    ]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO visitors (full_name, id_number, email, phone, company)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $full_name, $id_number,
                        $visitor_email ?: null,
                        $phone ?: null,
                        $company ?: null,
                    ]);
                    $visitor_id = (int)$db->lastInsertId();
                }

                // 2. Crear la visita
                $stmt = $db->prepare("
                    INSERT INTO visits (visitor_id, host_name, host_department, reason, notes, registered_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $visitor_id, $host_name,
                    $host_department ?: null,
                    $reason,
                    $notes ?: null,
                    $_SESSION['user_id'],
                ]);
                $visit_id = (int)$db->lastInsertId();

                // 3. Registrar dispositivos
                if (!empty($devices_valid)) {
                    $stmt = $db->prepare("
                        INSERT INTO devices (visit_id, visitor_id, device_type, device_name, mac_address)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    foreach ($devices_valid as $d) {
                        $stmt->execute([
                            $visit_id, $visitor_id,
                            $d['device_type'],
                            $d['device_name'] ?: null,
                            $d['mac_address'],
                        ]);
                    }
                }

                $db->commit();

                // ── Registrar en auditoría ────────────────────
                $num_devices = count($devices_valid);
                $desc = "Registró visita de '$full_name' (ID: $id_number) "
                      . "para ver a '$host_name'"
                      . ($host_department ? " ($host_department)" : '') . ". "
                      . "Motivo: $reason. "
                      . "Dispositivos: $num_devices.";

                logAudit($db, 'CREATE_VISIT', 'visit', $visit_id, $desc);

                $success      = true;
                $new_visit_id = $visit_id;

                // Renovar CSRF
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            } catch (PDOException $e) {
                $db->rollBack();
                error_log("Error en register.php: " . $e->getMessage());
                $errors[] = 'Error al guardar. Intenta de nuevo.';
            }
        }
    }
}

// Generar CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Registrar Visita';
$base_path  = '../';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Registrar Visita</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-0">
            <i class="bi bi-person-plus-fill text-primary"></i> Registrar Nueva Visita
        </h2>
    </div>
</div>

<!-- Alerta de éxito -->
<?php if ($success): ?>
<div class="alert alert-success alert-dismissible d-flex align-items-center" role="alert">
    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
    <div>
        <strong>¡Visita registrada exitosamente!</strong>
        <a href="detail.php?id=<?= $new_visit_id ?>" class="alert-link ms-2">
            Ver detalle de la visita →
        </a>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Errores de validación -->
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Revisa los siguientes errores:</strong>
    <ul class="mb-0 mt-1">
        <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="POST" id="registerForm" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div class="row g-4">

        <!-- ── DATOS DEL VISITANTE ──────────────────────────── -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-person-badge text-primary"></i> Datos del Visitante
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">

                        <div class="col-12">
                            <label class="form-label fw-semibold" for="full_name">
                                Nombre Completo <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="full_name" name="full_name"
                                   class="form-control" required maxlength="150"
                                   placeholder="Juan Pérez García"
                                   value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold" for="id_number">
                                Número de Identificación <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="id_number" name="id_number"
                                   class="form-control font-monospace" required maxlength="30"
                                   placeholder="DNI, Cédula o Pasaporte"
                                   value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>">
                            <div class="form-text">Si el visitante ya está registrado, se actualizarán sus datos.</div>
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-semibold" for="visitor_email">Email</label>
                            <input type="email" id="visitor_email" name="visitor_email"
                                   class="form-control" maxlength="100"
                                   placeholder="opcional"
                                   value="<?= htmlspecialchars($_POST['visitor_email'] ?? '') ?>">
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label fw-semibold" for="phone">Teléfono</label>
                            <input type="text" id="phone" name="phone"
                                   class="form-control" maxlength="20"
                                   placeholder="opcional"
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold" for="company">Empresa</label>
                            <input type="text" id="company" name="company"
                                   class="form-control" maxlength="100"
                                   placeholder="Empresa de procedencia (opcional)"
                                   value="<?= htmlspecialchars($_POST['company'] ?? '') ?>">
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- ── DATOS DE LA VISITA ────────────────────────────── -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-calendar-check text-primary"></i> Datos de la Visita
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">

                        <div class="col-12">
                            <label class="form-label fw-semibold" for="host_name">
                                Empleado a Visitar <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="host_name" name="host_name"
                                   class="form-control" required maxlength="150"
                                   placeholder="Nombre del empleado"
                                   value="<?= htmlspecialchars($_POST['host_name'] ?? '') ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold" for="host_department">Departamento</label>
                            <input type="text" id="host_department" name="host_department"
                                   class="form-control" maxlength="100"
                                   placeholder="Departamento (opcional)"
                                   value="<?= htmlspecialchars($_POST['host_department'] ?? '') ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold" for="reason">
                                Motivo de la Visita <span class="text-danger">*</span>
                            </label>
                            <textarea id="reason" name="reason"
                                      class="form-control" rows="3" required
                                      placeholder="Describir el motivo..."><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold" for="notes">Observaciones</label>
                            <textarea id="notes" name="notes"
                                      class="form-control" rows="2"
                                      placeholder="Notas adicionales (opcional)..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- ── DISPOSITIVOS ─────────────────────────────────── -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-laptop text-primary"></i> Dispositivos a Ingresar
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addDevice">
                        <i class="bi bi-plus-circle"></i> Agregar Dispositivo
                    </button>
                </div>
                <div class="card-body">
                    <div id="devicesContainer">
                        <!-- Fila inicial de dispositivo -->
                        <div class="row g-2 align-items-end mb-2 device-entry">
                            <div class="col-12 col-sm-3">
                                <label class="form-label small fw-semibold">Tipo</label>
                                <select name="devices[0][device_type]" class="form-select">
                                    <option value="laptop">💻 Laptop</option>
                                    <option value="smartphone">📱 Smartphone</option>
                                    <option value="tablet">📟 Tablet</option>
                                    <option value="other">📦 Otro</option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-4">
                                <label class="form-label small fw-semibold">Nombre / Descripción</label>
                                <input type="text" name="devices[0][device_name]"
                                       class="form-control" placeholder='Ej: "MacBook Pro de Juan"' maxlength="100">
                            </div>
                            <div class="col-12 col-sm-4">
                                <label class="form-label small fw-semibold">
                                    MAC Address <small class="text-muted">(AA:BB:CC:DD:EE:FF)</small>
                                </label>
                                <input type="text" name="devices[0][mac_address]"
                                       class="form-control mac-input font-monospace"
                                       placeholder="AA:BB:CC:DD:EE:FF" maxlength="17">
                            </div>
                            <div class="col-12 col-sm-1 d-flex justify-content-end">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-device" title="Quitar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted small mb-0 mt-2">
                        <i class="bi bi-info-circle"></i>
                        La MAC address es opcional. Si no se registra dispositivo, deja el campo vacío.
                    </p>
                </div>
            </div>
        </div>

    </div><!-- /.row -->

    <!-- ── BOTONES ─────────────────────────────────────────── -->
    <div class="d-flex gap-2 justify-content-end mt-4">
        <a href="../dashboard.php" class="btn btn-outline-secondary px-4">Cancelar</a>
        <button type="submit" class="btn btn-primary px-5">
            <i class="bi bi-person-check-fill"></i> Registrar Visita
        </button>
    </div>

</form>

<script>
// ── Agregar filas de dispositivos dinámicamente ───────────────
let deviceIndex = 1;

document.getElementById('addDevice').addEventListener('click', function () {
    const container = document.getElementById('devicesContainer');
    const newRow = document.createElement('div');
    newRow.className = 'row g-2 align-items-end mb-2 device-entry';
    newRow.innerHTML = `
        <div class="col-12 col-sm-3">
            <label class="form-label small fw-semibold">Tipo</label>
            <select name="devices[${deviceIndex}][device_type]" class="form-select">
                <option value="laptop">💻 Laptop</option>
                <option value="smartphone">📱 Smartphone</option>
                <option value="tablet">📟 Tablet</option>
                <option value="other">📦 Otro</option>
            </select>
        </div>
        <div class="col-12 col-sm-4">
            <label class="form-label small fw-semibold">Nombre / Descripción</label>
            <input type="text" name="devices[${deviceIndex}][device_name]"
                   class="form-control" placeholder="Ej: MacBook Pro" maxlength="100">
        </div>
        <div class="col-12 col-sm-4">
            <label class="form-label small fw-semibold">
                MAC Address <small class="text-muted">(AA:BB:CC:DD:EE:FF)</small>
            </label>
            <input type="text" name="devices[${deviceIndex}][mac_address]"
                   class="form-control mac-input font-monospace"
                   placeholder="AA:BB:CC:DD:EE:FF" maxlength="17">
        </div>
        <div class="col-12 col-sm-1 d-flex justify-content-end">
            <button type="button" class="btn btn-outline-danger btn-sm remove-device">
                <i class="bi bi-trash"></i>
            </button>
        </div>`;
    container.appendChild(newRow);
    deviceIndex++;
});

// Eliminar fila de dispositivo
document.addEventListener('click', function (e) {
    if (e.target.closest('.remove-device')) {
        const row = e.target.closest('.device-entry');
        if (document.querySelectorAll('.device-entry').length > 1) {
            row.remove();
        } else {
            // Limpiar la última fila en vez de eliminarla
            row.querySelectorAll('input').forEach(i => i.value = '');
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
