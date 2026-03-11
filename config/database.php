<?php
// ============================================================
// ARCHIVO: config/database.php
// Descripción: Configuración de la conexión a MySQL usando PDO.
//
// PDO (PHP Data Objects) es la forma SEGURA de conectarse a MySQL.
// Ventajas sobre mysqli_*:
//   ✅ Prepared statements nativos (evita SQL Injection)
//   ✅ Compatible con otros motores de BD si se migra
//   ✅ Manejo de errores con excepciones
// ============================================================

// ── Parámetros de conexión ────────────────────────────────────
// En un proyecto real estos irían en un archivo .env
// Aquí los dejamos aquí para facilidad de prueba
define('DB_HOST', 'localhost');   // Servidor MySQL (casi siempre localhost en local)
define('DB_NAME', 'visitor_management'); // Nombre de la base de datos
define('DB_USER', 'root');        // ⚠️ Cambiar por usuario real en producción
define('DB_PASS', '');            // ⚠️ Cambiar por contraseña real en producción
define('DB_CHARSET', 'utf8mb4'); // Soporte para acentos y caracteres especiales

/**
 * getDB()
 * Función que devuelve una conexión PDO lista para usar.
 * Usa el patrón Singleton: crea la conexión una sola vez
 * y reutiliza la misma en toda la petición.
 *
 * @return PDO  Instancia de la conexión
 */
function getDB(): PDO {
    // Variable estática: se crea solo la primera vez que se llama
    static $pdo = null;

    if ($pdo === null) {
        // DSN = Data Source Name, le dice a PDO a qué conectarse
        $dsn = "mysql:host=" . DB_HOST
             . ";dbname=" . DB_NAME
             . ";charset=" . DB_CHARSET;

        // Opciones de configuración de PDO
        $options = [
            // Lanzar excepciones en errores (en vez de fallar silenciosamente)
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            
            // Devolver los resultados como arrays asociativos por defecto
            // Ej: $row['full_name'] en vez de $row[0]
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            
            // Usar prepared statements nativos del servidor MySQL
            // Esto previene SQL Injection a nivel de driver
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // En producción: loguear el error real, mostrar mensaje genérico
            // Nunca mostrar el error real al usuario (expone estructura de BD)
            error_log("Error de conexión a BD: " . $e->getMessage());
            die(json_encode(['error' => 'Error de conexión a la base de datos']));
        }
    }

    return $pdo;
}
