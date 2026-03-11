<?php
// ============================================================
// ARCHIVO: index.php
// Descripción: Punto de entrada del sistema.
//
// Si el usuario tiene sesión activa → ir al Dashboard
// Si no tiene sesión               → ir al Login
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit();
