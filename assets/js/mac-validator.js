/**
 * ARCHIVO: assets/js/mac-validator.js
 * Descripción: Validación en tiempo real de direcciones MAC address.
 *
 * Una dirección MAC tiene el formato: AA:BB:CC:DD:EE:FF
 *  - 6 grupos de 2 dígitos hexadecimales (0-9, A-F)
 *  - Separados por dos puntos (:)
 *
 * IMPORTANTE: Esta validación es del lado del CLIENTE (frontend).
 * Es solo para mejorar la experiencia del usuario (UX).
 * La validación REAL y segura ocurre en el servidor (PHP + BD).
 * Nunca confiar SOLO en validación de JavaScript.
 */

// Expresión regular para validar MAC address
// ^          → inicio del string
// ([0-9A-Fa-f]{2}:){5}  → 5 grupos de "2 hex + :" (ej: "AA:")
// [0-9A-Fa-f]{2}         → último grupo de 2 hex sin ":"
// $          → fin del string
const MAC_REGEX = /^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/;

/**
 * Formatea automáticamente la entrada del usuario mientras escribe.
 * Convierte: "aabbcc" → "AA:BB:CC" (agrega los : automáticamente)
 *
 * @param {HTMLInputElement} input - El campo de texto de la MAC
 */
function autoFormatMAC(input) {
    // Obtener el valor actual
    let value = input.value;

    // Remover todo lo que NO sea un dígito hexadecimal (mantener solo hex)
    let clean = value.replace(/[^0-9A-Fa-f]/g, '');

    // Convertir a mayúsculas (normalización)
    clean = clean.toUpperCase();

    // Limitar a 12 caracteres hex (6 bytes × 2 chars cada uno)
    clean = clean.substring(0, 12);

    // Insertar ":" cada 2 caracteres
    // Ej: "AABBCCDDEEFF" → ["AA","BB","CC","DD","EE","FF"] → "AA:BB:CC:DD:EE:FF"
    let formatted = clean.match(/.{1,2}/g)?.join(':') || clean;

    // Actualizar el valor del input sin mover el cursor al final innecesariamente
    if (input.value !== formatted) {
        input.value = formatted;
    }
}

/**
 * Valida una dirección MAC y muestra feedback visual.
 * Usa las clases is-valid / is-invalid de Bootstrap.
 *
 * @param {HTMLInputElement} input - El campo de texto de la MAC
 */
function validateMAC(input) {
    const value = input.value.trim();

    // Si está vacío, no mostrar ni error ni éxito (campo opcional)
    if (value === '') {
        input.classList.remove('is-valid', 'is-invalid');
        return;
    }

    if (MAC_REGEX.test(value)) {
        // ✅ MAC válida: mostrar borde verde
        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
    } else {
        // ❌ MAC inválida: mostrar borde rojo
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
    }
}

/**
 * Inicializar los listeners en todos los campos MAC actuales y futuros.
 * Usamos delegación de eventos en el document para capturar
 * inputs que se agreguen dinámicamente con JavaScript.
 */
document.addEventListener('DOMContentLoaded', function () {

    // ── Event delegation: escuchar eventos en el document ────
    // En vez de agregar listeners a cada campo individualmente,
    // los escuchamos en el documento y filtramos por la clase .mac-input
    // Esto funciona incluso para inputs agregados dinámicamente (nuevas filas)

    // Formato en tiempo real mientras escribe
    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('mac-input')) {
            autoFormatMAC(e.target);
            validateMAC(e.target);
        }
    });

    // Validación al perder el foco (cuando el usuario sale del campo)
    document.addEventListener('blur', function (e) {
        if (e.target.classList.contains('mac-input')) {
            validateMAC(e.target);
        }
    }, true); // true = captura en fase de captura (necesario para blur)

    // Validar los campos que ya existan al cargar la página
    // (importante si el form tiene errores y se repoblan los valores)
    document.querySelectorAll('.mac-input').forEach(function (input) {
        if (input.value) {
            validateMAC(input);
        }
    });

});

/**
 * Validar el formulario completo antes de enviarlo.
 * Se llama desde el evento submit del formulario.
 *
 * @returns {boolean} true si todo está bien, false si hay errores
 */
function validateFormBeforeSubmit() {
    let isValid = true;

    document.querySelectorAll('.mac-input').forEach(function (input) {
        const value = input.value.trim();

        // Solo validar si tiene algo escrito
        if (value !== '' && !MAC_REGEX.test(value)) {
            isValid = false;
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
        }
    });

    if (!isValid) {
        // Hacer scroll hasta el primer error
        const firstError = document.querySelector('.mac-input.is-invalid');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus();
        }
    }

    return isValid;
}

// Interceptar el envío del formulario para validar MACs
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registerForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (!validateFormBeforeSubmit()) {
                e.preventDefault(); // Detener el envío si hay errores
            }
        });
    }
});
