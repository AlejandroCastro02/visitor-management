<?php
// ============================================================
// ARCHIVO: includes/audit.php
// Descripción: Helper centralizado para registrar eventos
//              en la bitácora de auditoría (tabla audit_log).
//
// Uso en cualquier módulo:
//   require_once __DIR__ . '/../includes/audit.php';
//   logAudit($db, 'CREATE_USER', 'user', $new_id, 'Creó el usuario juan@empresa.com');
//
// Catálogo de acciones recomendadas:
//   Usuarios:   CREATE_USER, UPDATE_USER, DELETE_USER, TOGGLE_USER
//   Visitantes: CREATE_VISITOR, UPDATE_VISITOR, DELETE_VISITOR
//   Visitas:    CREATE_VISIT, EXIT_VISIT, CANCEL_VISIT
//   Sesión:     LOGIN, LOGOUT (opcional, se puede agregar)
// ============================================================

/**
 * logAudit()
 * Registra un evento en la tabla audit_log de forma segura.
 * No lanza excepciones: si falla el log, la operación principal
 * no se interrumpe (el log nunca debe bloquear la funcionalidad).
 *
 * @param PDO    $db          Conexión activa a la BD
 * @param string $action      Código de acción en MAYÚSCULAS (ej: 'DELETE_USER')
 * @param string $entity_type Tipo de entidad afectada: 'user', 'visitor', 'visit', 'device'
 * @param int|null $entity_id ID del registro afectado (null si no aplica)
 * @param string $description Descripción legible del evento para el auditor
 */
function logAudit(
    PDO    $db,
    string $action,
    string $entity_type,
    ?int   $entity_id,
    string $description
): void {
    try {
        // Capturar datos de sesión (quién)
        $user_id   = $_SESSION['user_id']   ?? null;
        $user_name = $_SESSION['user_name'] ?? 'Sistema';
        $user_role = $_SESSION['user_role'] ?? 'system';

        // Capturar contexto HTTP (desde dónde)
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR']   // Si hay proxy/balanceador
           ?? $_SERVER['REMOTE_ADDR']             // IP directa
           ?? '0.0.0.0';

        // Truncar IP en caso de lista (X-Forwarded-For puede ser "IP1, IP2, ...")
        $ip = trim(explode(',', $ip)[0]);
        $ip = substr($ip, 0, 45); // Límite del campo VARCHAR(45)

        // User-Agent: navegador o cliente que hizo la petición
        $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        $stmt = $db->prepare("
            INSERT INTO audit_log
                (user_id, user_name, user_role, action, entity_type, entity_id, description, ip_address, user_agent)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $user_id,
            $user_name,
            $user_role,
            strtoupper($action),   // Normalizar a mayúsculas
            strtolower($entity_type), // Normalizar a minúsculas
            $entity_id,
            $description,
            $ip,
            $user_agent,
        ]);

    } catch (PDOException $e) {
        // Loguear en el error_log del servidor pero NO interrumpir el flujo
        error_log("[AUDIT ERROR] No se pudo registrar evento '$action': " . $e->getMessage());
    }
}

/**
 * getActionLabel()
 * Traduce el código de acción a una etiqueta legible en español.
 * Útil para mostrar en la vista del log de auditoría.
 *
 * @param string $action  Código de acción (ej: 'DELETE_USER')
 * @return array          ['label' => '...', 'color' => 'badge-color']
 */
function getActionLabel(string $action): array {
    return match($action) {
        // Usuarios
        'CREATE_USER'    => ['label' => 'Creó usuario',        'color' => 'success'],
        'UPDATE_USER'    => ['label' => 'Actualizó usuario',   'color' => 'primary'],
        'DELETE_USER'    => ['label' => 'Eliminó usuario',     'color' => 'danger'],
        'TOGGLE_USER'    => ['label' => 'Cambió estado usuario','color' => 'warning'],
        // Visitantes
        'CREATE_VISITOR' => ['label' => 'Registró visitante',  'color' => 'success'],
        'UPDATE_VISITOR' => ['label' => 'Actualizó visitante', 'color' => 'primary'],
        'DELETE_VISITOR' => ['label' => 'Eliminó visitante',   'color' => 'danger'],
        // Visitas
        'CREATE_VISIT'   => ['label' => 'Registró visita',     'color' => 'success'],
        'EXIT_VISIT'     => ['label' => 'Registró salida',     'color' => 'secondary'],
        'CANCEL_VISIT'   => ['label' => 'Canceló visita',      'color' => 'warning'],
        // Sistema
        'MIGRATION_RUN'  => ['label' => 'Migración BD',        'color' => 'dark'],
        // Default
        default          => ['label' => htmlspecialchars($action), 'color' => 'secondary'],
    };
}

/**
 * getEntityLabel()
 * Traduce el tipo de entidad a español.
 *
 * @param string $entity_type
 * @return string
 */
function getEntityLabel(string $entity_type): string {
    return match($entity_type) {
        'user'    => 'Usuario',
        'visitor' => 'Visitante',
        'visit'   => 'Visita',
        'device'  => 'Dispositivo',
        'system'  => 'Sistema',
        default   => ucfirst($entity_type),
    };
}
