<?php
// ============================================================
// ARCHIVO: auth/logout.php
// Descripción: Cierra la sesión del usuario de forma segura.
//
// Pasos para un logout seguro:
//  1. Iniciar sesión (para poder manipularla)
//  2. Limpiar todas las variables de sesión
//  3. Eliminar la cookie de sesión del navegador
//  4. Destruir la sesión en el servidor
//  5. Redirigir al login
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Paso 1: Vaciar todas las variables de sesión ─────────────
// session_unset() limpia el array $_SESSION pero mantiene la sesión
$_SESSION = [];

// ── Paso 2: Eliminar la cookie de sesión del navegador ───────
// Aunque destruyamos la sesión en el servidor, el navegador
// todavía tiene la cookie. Hay que invalidarla explícitamente
// enviando la misma cookie con una fecha de expiración en el pasado.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),    // Nombre de la cookie (por defecto: PHPSESSID)
        '',                // Valor vacío
        time() - 42000,   // Expiración en el PASADO (hace ~12 horas)
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// ── Paso 3: Destruir la sesión en el servidor ────────────────
// Elimina el archivo de sesión del servidor definitivamente
session_destroy();

// ── Paso 4: Redirigir al login con mensaje ───────────────────
header("Location: ../login.php?logout=1");
exit();
