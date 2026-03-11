<?php
// ============================================================
// ARCHIVO: visitors/register.php
// Descripción: Formulario para registrar una nueva visita.
//
// Este archivo hace DOS cosas según el método HTTP:
//  GET  → Muestra el formulario vacío
//  POST → Procesa el formulario y guarda en la BD
//
// Flujo de registro:
//  1. Verificar si el visitante ya existe por ID/Cédula
//  2. Si no existe, crearlo en la tabla `visitors`
//  3. Crear la visita en la tabla `visits`
//  4. Si hay dispositivos, guardarlos en la tabla `devices`
//  5. Redirigir al detalle de la visita recién creada
// ============================================================

$depth = 1; // Estamos 1 nivel adentro de la raíz

require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../config/database.php';

// Arreglo para acumular errores de validación
$errors = [];
// Arreglo para repoblar el form si hay errores (UX: no perder lo que escribió)
$old = [];

// ── Procesar formulario (solo POST) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Verificar CSRF ────────────────────────────────────────
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = "Solicitud inválida. Recarga la página e intenta de nuevo.";
    }

    // ── Recoger y sanitizar datos del visitante ───────────────
    $old = [
        // FILTER_SANITIZE_SPECIAL_CHARS convierte caracteres HTML especiales a entidades
        // Esto previene XSS si el dato se muestra luego en pantalla
        'full_name'       => trim(filter_input(INPUT_POST, 'full_name',       FILTER_SANITIZE_SPECIAL_CHARS)),
        'id_number'       => trim(filter_input(INPUT_POST, 'id_number',        FILTER_SANITIZE_SPECIAL_CHARS)),
        'email'           => trim(filter_input(INPUT_POST, 'email',            FILTER_SANITIZE_EMAIL)),
        'phone'           => trim(filter_input(INPUT_POST, 'phone',            FILTER_SANITIZE_SPECIAL_CHARS)),
        'company'         => trim(filter_input(INPUT_POST, 'company',          FILTER_SANITIZE_SPECIAL_CHARS)),
        'host_name'       => trim(filter_input(INPUT_POST, 'host_name',        FILTER_SANITIZE_SPECIAL_CHARS)),
        'host_department' => trim(filter_input(INPUT_POST, 'host_department',  FILTER_SANITIZE_SPECIAL_CHARS)),
        'reason'          => trim(filter_input(INPUT_POST, 'reason',           FILTER_SANITIZE_SPECIAL_CHARS)),
        'notes'           => trim(filter_input(INPUT_POST, 'notes',            FILTER_SANITIZE_SPECIAL_CHARS)),
        // Dispositivos: vienen como arrays (múltiples dispositivos por visita)
        'device_types'    => $_POST['device_type']  ?? [],
        'device_names'    => $_POST['device_name']  ?? [],
        'mac_addresses'   => $_POST['mac_address']  ?? [],
    ];

    // ── Validaciones del lado del servidor ───────────────────
    // IMPORTANTE: Las validaciones JS del frontend son solo UX.
    // El backend SIEMPRE valida de nuevo (alguien puede saltarse el JS)

    if (empty($old['full_name']))  $errors[] = "El nombre completo es obligatorio.";
    if (empty($old['id_number']))  $errors[] = "El número de identificación es obligatorio.";
    if (empty($old['host_name']))  $errors[] = "El nombre del anfitrión es obligatorio.";
    if (empty($old['reason']))     $errors[] = "El motivo de la visita es obligatorio.";

    // Validar email si fue proporcionado
    if (!empty($old['email']) && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "El formato del email no es válido.";
    }

    // Validar cada dirección MAC ingresada
    // Patrón regex: 6 grupos de 2 dígitos hexadecimales separados por :
    // Acepta mayúsculas y minúsculas: AA:BB:CC:DD:EE:FF o aa:bb:cc:dd:ee:ff
    $mac_pattern = '/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/';
    foreach ($old['mac_addresses'] as $i => $mac) {
        $mac = trim($mac);
        if (!empty($mac) && !preg_match($mac_pattern, $mac)) {
            $errors[] = "La dirección MAC del dispositivo " . ($i + 1) . " no es válida. Formato: AA:BB:CC:DD:EE:FF";
        }
    }

    // ── Si no hay errores, guardar en BD ──────────────────────
    if (empty($errors)) {
        try {
            $db = getDB();

            // ── Transacción: todas las queries o ninguna ──────
            // Si algo falla a mitad de camino, se hace rollback y
            // no quedan datos inconsistentes en la BD
            $db->beginTransaction();

            // Paso 1: ¿Ya existe el visitante por su ID?
            $stmt = $db->prepare("
                SELECT id FROM visitors WHERE id_number = ? LIMIT 1
            ");
            $stmt->execute([$old['id_number']]);
            $existing_visitor = $stmt->fetch();

            if ($existing_visitor) {
                // El visitante ya existe: usar su ID existente
                $visitor_id = $existing_visitor['id'];

                // Actualizar sus datos por si cambiaron desde la última visita
                $stmt = $db->prepare("
                    UPDATE visitors
                    SET full_name = ?, email = ?, phone = ?, company = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $old['full_name'], $old['email'],
                    $old['phone'],     $old['company'],
                    $visitor_id
                ]);
            } else {
                // El visitante es nuevo: insertarlo
                $stmt = $db->prepare("
                    INSERT INTO visitors (full_name, id_number, email, phone, company)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $old['full_name'], $old['id_number'],
                    $old['email'] ?: null,   // null si string vacío
                    $old['phone'] ?: null,
                    $old['company'] ?: null
                ]);
                // lastInsertId() devuelve el ID del INSERT recién ejecutado
                $visitor_id = $db->lastInsertId();
            }

            // Paso 2: Crear el registro de la visita
            $stmt = $db->prepare("
                INSERT INTO visits
                    (visitor_id, host_name, host_department, reason, registered_by, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $visitor_id,
                $old['host_name'],
                $old['host_department'] ?: null,
                $old['reason'],
                $_SESSION['user_id'],      // ID del recepcionista logueado
                $old['notes'] ?: null
            ]);
            $visit_id = $db->lastInsertId();

            // Paso 3: Guardar dispositivos (si se ingresaron)
            $stmt_device = $db->prepare("
                INSERT INTO devices (visit_id, visitor_id, device_type, device_name, mac_address)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($old['mac_addresses'] as $i => $mac) {
                $mac = trim($mac);
                // Solo insertar si hay una MAC válida en esta fila
                if (!empty($mac) && preg_match($mac_pattern, $mac)) {
                    $stmt_device->execute([
                        $visit_id,
                        $visitor_id,
                        $old['device_types'][$i] ?? 'other',
                        $old['device_names'][$i] ?: null,
                        strtoupper($mac) // Normalizar MAC a mayúsculas: aa:bb → AA:BB
                    ]);
                }
            }

            // Confirmar transacción: todo salió bien
            $db->commit();

            // Regenerar token CSRF para la próxima petición
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // Redirigir al detalle de la visita recién creada
            header("Location: detail.php?id=$visit_id&new=1");
            exit();

        } catch (PDOException $e) {
            // Algo falló: deshacer TODO lo que se hizo en la transacción
            $db->rollback();
            error_log("Error registrando visita: " . $e->getMessage());
            $errors[] = "Error al guardar. Por favor intenta de nuevo.";
        }
    }
}

// ── Generar/mantener token CSRF para el formulario ───────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Registrar Visita';
$base_path  = '../';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── TÍTULO ────────────────────────────────────────────── -->
<div class="row mb-4">
    <div class="col">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="list.php">Visitantes</a></li>
                <li class="breadcrumb-item active">Registrar Visita</li>
            </ol>
        </nav>
        <h2 class="fw-bold"><i class="bi bi-person-plus text-primary"></i> Registrar Nueva Visita</h2>
    </div>
</div>

<!-- ── ERRORES DE VALIDACIÓN ────────────────────────────── -->
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <strong><i class="bi bi-exclamation-triangle"></i> Por favor corrige los siguientes errores:</strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- ── FORMULARIO PRINCIPAL ─────────────────────────────── -->
<form method="POST" action="" id="registerForm" novalidate>

    <!-- Token CSRF oculto -->
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <div class="row g-4">

        <!-- ── COLUMNA IZQUIERDA: Datos del Visitante ──────── -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-person text-primary"></i> Datos del Visitante
                    </h5>
                </div>
                <div class="card-body">

                    <!-- Nombre Completo (obligatorio) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Nombre Completo <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="full_name" class="form-control"
                               placeholder="Ej: Juan Carlos Pérez"
                               value="<?= htmlspecialchars($old['full_name'] ?? '') ?>"
                               required maxlength="150">
                    </div>

                    <!-- Número de Identificación (obligatorio) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Cédula / DNI / Pasaporte <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="id_number" class="form-control"
                               placeholder="Ej: 12345678"
                               value="<?= htmlspecialchars($old['id_number'] ?? '') ?>"
                               required maxlength="30">
                        <div class="form-text">
                            <i class="bi bi-info-circle"></i>
                            Si el visitante ya fue registrado antes, sus datos se actualizarán.
                        </div>
                    </div>

                    <!-- Email (opcional) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control"
                               placeholder="visitante@empresa.com"
                               value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                               maxlength="100">
                    </div>

                    <!-- Teléfono (opcional) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Teléfono</label>
                        <input type="tel" name="phone" class="form-control"
                               placeholder="Ej: +52 55 1234 5678"
                               value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
                               maxlength="20">
                    </div>

                    <!-- Empresa (opcional) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Empresa / Organización</label>
                        <input type="text" name="company" class="form-control"
                               placeholder="Ej: Empresa Proveedora S.A."
                               value="<?= htmlspecialchars($old['company'] ?? '') ?>"
                               maxlength="100">
                    </div>

                </div>
            </div>
        </div>

        <!-- ── COLUMNA DERECHA: Detalles de la Visita ──────── -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-calendar-check text-primary"></i> Detalles de la Visita
                    </h5>
                </div>
                <div class="card-body">

                    <!-- Persona que recibe (obligatorio) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Visita a (Empleado/Anfitrión) <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="host_name" class="form-control"
                               placeholder="Ej: María López"
                               value="<?= htmlspecialchars($old['host_name'] ?? '') ?>"
                               required maxlength="150">
                    </div>

                    <!-- Departamento (opcional) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Departamento</label>
                        <input type="text" name="host_department" class="form-control"
                               placeholder="Ej: Recursos Humanos"
                               value="<?= htmlspecialchars($old['host_department'] ?? '') ?>"
                               maxlength="100">
                    </div>

                    <!-- Motivo de la visita (obligatorio) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Motivo de la Visita <span class="text-danger">*</span>
                        </label>
                        <textarea name="reason" class="form-control" rows="3"
                                  placeholder="Ej: Reunión de negocios, entrega de documentos, etc."
                                  required maxlength="500"><?= htmlspecialchars($old['reason'] ?? '') ?></textarea>
                    </div>

                    <!-- Notas adicionales (opcional) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notas Adicionales</label>
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="Observaciones del recepcionista..."
                                  maxlength="500"><?= htmlspecialchars($old['notes'] ?? '') ?></textarea>
                    </div>

                </div>
            </div>
        </div>

        <!-- ── SECCIÓN: Dispositivos del Visitante ──────────── -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-semibold">
                        <i class="bi bi-laptop text-primary"></i> Dispositivos Personales
                    </h5>
                    <!-- Botón para agregar más dispositivos dinámicamente con JS -->
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addDevice">
                        <i class="bi bi-plus-circle"></i> Agregar Dispositivo
                    </button>
                </div>
                <div class="card-body">

                    <p class="text-muted small mb-3">
                        <i class="bi bi-shield-check text-success"></i>
                        Los dispositivos se registran para control de acceso a la red de visitantes.
                        La dirección MAC tiene el formato: <code>AA:BB:CC:DD:EE:FF</code>
                    </p>

                    <!-- Contenedor de filas de dispositivos -->
                    <div id="devicesContainer">
                        <!-- Fila inicial de dispositivo -->
                        <div class="device-row border rounded p-3 mb-3 bg-light">
                            <div class="row g-3 align-items-end">
                                <div class="col-12 col-md-3">
                                    <label class="form-label fw-semibold small">Tipo de Dispositivo</label>
                                    <select name="device_type[]" class="form-select">
                                        <option value="laptop">💻 Laptop</option>
                                        <option value="smartphone">📱 Smartphone</option>
                                        <option value="tablet">📟 Tablet</option>
                                        <option value="other">🔌 Otro</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label fw-semibold small">Nombre/Descripción</label>
                                    <input type="text" name="device_name[]" class="form-control"
                                           placeholder="Ej: MacBook Pro">
                                </div>
                                <div class="col-12 col-md-5">
                                    <label class="form-label fw-semibold small">
                                        Dirección MAC
                                        <i class="bi bi-question-circle text-muted"
                                           data-bs-toggle="tooltip"
                                           title="Cómo obtener la MAC: Windows: ipconfig /all | Mac: System Preferences > Network | Linux: ip link show"></i>
                                    </label>
                                    <input type="text" name="mac_address[]"
                                           class="form-control mac-input font-monospace"
                                           placeholder="AA:BB:CC:DD:EE:FF"
                                           maxlength="17"
                                           pattern="^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$">
                                    <!-- Feedback de validación MAC (se muestra con JS) -->
                                    <div class="invalid-feedback">
                                        Formato inválido. Use: AA:BB:CC:DD:EE:FF
                                    </div>
                                    <div class="valid-feedback">
                                        Dirección MAC válida ✓
                                    </div>
                                </div>
                                <div class="col-12 col-md-1">
                                    <button type="button" class="btn btn-outline-danger btn-sm w-100 remove-device" disabled>
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ── BOTONES DE ACCIÓN ──────────────────────────── -->
        <div class="col-12 d-flex gap-2 justify-content-end">
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-x-circle"></i> Cancelar
            </a>
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-save"></i> Registrar Visita
            </button>
        </div>

    </div>
</form>

<script>
// ── Inicializar tooltips de Bootstrap ───────────────────────
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
});

// ── Agregar fila de dispositivo dinámicamente ────────────────
// Cuando el usuario hace clic en "Agregar Dispositivo",
// clonar la primera fila del dispositivo y limpiarla
document.getElementById('addDevice').addEventListener('click', function () {
    const container = document.getElementById('devicesContainer');
    // Clonar la primera fila de dispositivo (deep clone: true)
    const firstRow  = container.querySelector('.device-row');
    const newRow    = firstRow.cloneNode(true);

    // Limpiar los valores de la fila clonada
    newRow.querySelectorAll('input').forEach(input => {
        input.value = '';
        input.classList.remove('is-valid', 'is-invalid');
    });
    newRow.querySelectorAll('select').forEach(select => {
        select.selectedIndex = 0;
    });

    // Habilitar el botón de eliminar en la nueva fila
    newRow.querySelector('.remove-device').disabled = false;

    container.appendChild(newRow);
    updateRemoveButtons();
});

// ── Eliminar fila de dispositivo ────────────────────────────
document.getElementById('devicesContainer').addEventListener('click', function (e) {
    if (e.target.closest('.remove-device')) {
        e.target.closest('.device-row').remove();
        updateRemoveButtons();
    }
});

// Mantener deshabilitado el botón eliminar cuando solo hay una fila
function updateRemoveButtons() {
    const rows = document.querySelectorAll('.device-row');
    rows.forEach((row, i) => {
        row.querySelector('.remove-device').disabled = (rows.length === 1);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
