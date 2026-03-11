<?php
// ============================================================
// ARCHIVO: includes/header.php
// Descripción: Cabecera HTML reutilizable para todas las páginas.
//
// Cómo incluirlo:
//   $page_title = "Mi Página";  // Definir antes de incluir
//   require_once __DIR__ . '/../includes/header.php';
//
// Variables que puede recibir:
//   $page_title   - Título de la página (string)
//   $base_path    - Ruta relativa a la raíz (ej: '../' o '')
// ============================================================

// Si no se definió $base_path, asumimos que estamos en la raíz
$base_path = $base_path ?? '';
$page_title = $page_title ?? 'Gestión de Visitantes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — Sistema de Visitantes</title>

    <!-- Bootstrap 5 CSS via CDN -->
    <!-- htmlspecialchars en $base_path previene XSS si la variable fuera manipulada -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <!-- Bootstrap Icons (iconos vectoriales de Bootstrap) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
          rel="stylesheet">

    <!-- Estilos personalizados del sistema -->
    <link href="<?= $base_path ?>assets/css/custom.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- ── BARRA DE NAVEGACIÓN ─────────────────────────────────── -->
<!-- Solo mostrar la nav si hay sesión activa -->
<?php if (isset($_SESSION['user_id'])): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container-fluid">

        <!-- Logo / Nombre del sistema -->
        <a class="navbar-brand fw-bold" href="<?= $base_path ?>dashboard.php">
            <i class="bi bi-building-check"></i> VisitorControl
        </a>

        <!-- Botón hamburguesa para móvil -->
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Links de navegación -->
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_path ?>dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_path ?>visitors/list.php">
                        <i class="bi bi-people"></i> Visitantes
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_path ?>visitors/register.php">
                        <i class="bi bi-person-plus"></i> Registrar Visita
                    </a>
                </li>

            </ul>

            <!-- Info del usuario logueado + botón de logout -->
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#"
                       data-bs-toggle="dropdown">
                        <!-- htmlspecialchars previene XSS si el nombre tuviera HTML -->
                        <i class="bi bi-person-circle"></i>
                        <?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuario') ?>
                        <span class="badge bg-secondary ms-1">
                            <?= htmlspecialchars($_SESSION['user_role'] ?? '') ?>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item text-danger"
                               href="<?= $base_path ?>auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>

<!-- ── CONTENEDOR PRINCIPAL ───────────────────────────────── -->
<div class="container-fluid py-4">
