<?php
// ============================================================
// ARCHIVO: includes/footer.php
// Descripción: Pie de página HTML reutilizable.
// Incluir al final de cada página DESPUÉS de todo el contenido.
// ============================================================
?>

</div><!-- Cierre del div.container-fluid del header -->

<!-- ── PIE DE PÁGINA ──────────────────────────────────────── -->
<footer class="footer mt-auto py-3 bg-white border-top">
    <div class="container text-center text-muted small">
        <span>
            <i class="bi bi-shield-lock"></i>
            Sistema de Gestión de Visitantes &copy; <?= date('Y') ?>
            &mdash; Sesión segura activa
        </span>
    </div>
</footer>

<!-- Bootstrap 5 JS Bundle (incluye Popper para dropdowns y tooltips) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
</script>

<!-- Validador de dirección MAC (nuestro script personalizado) -->
<script src="<?= $base_path ?? '' ?>assets/js/mac-validator.js"></script>

</body>
</html>
