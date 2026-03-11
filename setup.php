<?php
// ============================================================
// ARCHIVO: setup.php
// Descripción: Script de configuración inicial.
//
// EJECUTAR SOLO UNA VEZ para crear los usuarios de prueba
// con hashes generados por el propio PHP del servidor.
//
// Acceder desde el navegador: http://localhost/visitor-management/setup.php
// ELIMINAR o RENOMBRAR este archivo después de usarlo.
// ============================================================

// ── Conexión a la BD ─────────────────────────────────────────
require_once __DIR__ . '/config/database.php';

$messages = [];
$errors   = [];

// ── Generar hashes con el PHP de TU servidor ─────────────────
// Así se garantiza compatibilidad 100%
$users = [
    [
        'username' => 'admin',
        'email'    => 'admin@sistema.local',
        'password' => 'Admin1234!',
        'role'     => 'admin',
    ],
    [
        'username' => 'recepcion',
        'email'    => 'recepcion@sistema.local',
        'password' => 'Recep1234!',
        'role'     => 'receptionist',
    ],
];

try {
    $db = getDB();

    // Limpiar usuarios existentes para evitar duplicados
    $db->exec("DELETE FROM users");
    $messages[] = "✅ Tabla users limpiada.";

    $stmt = $db->prepare("
        INSERT INTO users (username, email, password, role)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($users as $user) {
        // password_hash() genera el hash usando el PHP de ESTE servidor
        // Esto garantiza compatibilidad total con password_verify()
        $hash = password_hash($user['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt->execute([
            $user['username'],
            $user['email'],
            $hash,
            $user['role'],
        ]);

        $messages[] = "✅ Usuario <strong>{$user['username']}</strong> creado."
                    . " Hash generado: <code>" . substr($hash, 0, 30) . "...</code>";
    }

    // ── Verificar que los hashes funcionan ──────────────────
    $messages[] = "<hr><strong>Verificación de hashes:</strong>";

    $stmt = $db->query("SELECT username, email, password FROM users");
    $saved_users = $stmt->fetchAll();

    $test_passwords = [
        'admin@sistema.local'    => 'Admin1234!',
        'recepcion@sistema.local' => 'Recep1234!',
    ];

    foreach ($saved_users as $saved) {
        $test_pass = $test_passwords[$saved['email']];
        $ok = password_verify($test_pass, $saved['password']);
        $icon = $ok ? '✅' : '❌';
        $messages[] = "$icon <strong>{$saved['username']}</strong>: "
                    . "password_verify('{$test_pass}') = " . ($ok ? 'TRUE' : 'FALSE');
    }

} catch (PDOException $e) {
    $errors[] = "❌ Error: " . htmlspecialchars($e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Setup — Visitor Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
<div class="container" style="max-width:700px">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">⚙️ Setup — Gestión de Visitantes</h4>
        </div>
        <div class="card-body">

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $e): ?>
                        <p class="mb-1"><?= $e ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($messages)): ?>
                <div class="alert alert-success">
                    <?php foreach ($messages as $m): ?>
                        <p class="mb-1"><?= $m ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($errors)): ?>
            <div class="alert alert-warning mt-3">
                <strong>⚠️ Importante:</strong> Una vez que el sistema funcione correctamente,
                <strong>elimina o renombra este archivo</strong> <code>setup.php</code>
                para evitar que alguien pueda resetear los usuarios.
            </div>

            <div class="mt-3">
                <h6>Credenciales para usar:</h6>
                <table class="table table-sm table-bordered">
                    <thead class="table-dark">
                        <tr><th>Rol</th><th>Email</th><th>Contraseña</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Admin</td>
                            <td><code>admin@sistema.local</code></td>
                            <td><code>Admin1234!</code></td>
                        </tr>
                        <tr>
                            <td>Recepcionista</td>
                            <td><code>recepcion@sistema.local</code></td>
                            <td><code>Recep1234!</code></td>
                        </tr>
                    </tbody>
                </table>
                <a href="login.php" class="btn btn-primary mt-2">
                    → Ir al Login
                </a>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
