<?php
// ============================================================
// ARCHIVO: includes/header.php
// Descripción: Cabecera HTML reutilizable para todas las páginas.
// ACTUALIZACIÓN: Menú de Administración con gestión de usuarios
//                y bitácora de auditoría.
// ============================================================

$base_path  = $base_path  ?? '';
$page_title = $page_title ?? 'Gestión de Visitantes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — Sistema de Visitantes</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
          rel="stylesheet">
    <link href="<?= $base_path ?>assets/css/custom.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- ── BARRA DE NAVEGACIÓN ─────────────────────────────────── -->
<?php if (isset($_SESSION['user_id'])): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container-fluid">

        <a class="navbar-brand fw-bold" href="<?= $base_path ?>dashboard.php">
            <i class="bi bi-building-check"></i> VisitorControl
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>

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

                <?php if (($_SESSION['user_role'] ?? '') !== 'guard'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_path ?>visitors/register.php">
                        <i class="bi bi-person-plus"></i> Registrar Visita
                    </a>
                </li>
                <?php endif; ?>

                <!-- ── Menú Admin (solo visible para administradores) ── -->
                <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-shield-lock-fill"></i> Administración
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <h6 class="dropdown-header">
                                <i class="bi bi-gear"></i> Panel Admin
                            </h6>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= $base_path ?>admin/users.php">
                                <i class="bi bi-people-fill text-primary"></i>
                                Gestión de Usuarios
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= $base_path ?>admin/audit_log.php">
                                <i class="bi bi-journal-text text-warning"></i>
                                Bitácora de Auditoría
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?= $base_path ?>admin/user_form.php">
                                <i class="bi bi-person-plus-fill text-success"></i>
                                Nuevo Usuario
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

            </ul>

            <!-- Info del usuario + logout -->
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                        <?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuario') ?>
                        <?php
                        $role_badge_color = match($_SESSION['user_role'] ?? '') {
                            'admin'        => 'bg-danger',
                            'receptionist' => 'bg-secondary',
                            'guard'        => 'bg-warning text-dark',
                            default        => 'bg-secondary'
                        };
                        $role_label = match($_SESSION['user_role'] ?? '') {
                            'admin'        => 'Admin',
                            'receptionist' => 'Recep.',
                            'guard'        => 'Vigilante',
                            default        => htmlspecialchars($_SESSION['user_role'] ?? '')
                        };
                        ?>
                        <span class="badge <?= $role_badge_color ?> ms-1">
                            <?= $role_label ?>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <span class="dropdown-item-text text-muted small">
                                <?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>
                            </span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
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
