# 🏢 Sistema de Gestión de Visitantes

Prototipo funcional para el registro de visitantes en Oficinas Centrales.

---

## 📋 Tabla de Contenidos

1. [Requisitos](#requisitos)
2. [Instalación](#instalación)
3. [Credenciales de Prueba](#credenciales-de-prueba)
4. [Arquitectura del Sistema](#arquitectura-del-sistema)
5. [Arquitectura de Seguridad](#arquitectura-de-seguridad)
6. [Propuesta de Segmentación de Red VLAN](#propuesta-de-segmentación-de-red-vlan)
7. [Estructura de Archivos](#estructura-de-archivos)

---

## Requisitos

- PHP 8.0 o superior
- MySQL 5.7 o superior (o MariaDB 10.3+)
- Servidor web: Apache (XAMPP/WAMP) o Nginx
- Extensiones PHP: `pdo`, `pdo_mysql`, `openssl`

---

## Instalación

### Paso 1: Clonar o descomprimir el proyecto

Colocar la carpeta `visitor-management` dentro de tu servidor web:
- XAMPP: `C:/xampp/htdocs/visitor-management`
- WAMP: `C:/wamp64/www/visitor-management`
- Linux: `/var/www/html/visitor-management`

### Paso 2: Crear la base de datos

Abrir phpMyAdmin o MySQL CLI y ejecutar:

```sql
SOURCE /ruta/al/proyecto/database/schema.sql
```

O copiar y pegar el contenido del archivo `database/schema.sql` directamente en phpMyAdmin.

### Paso 3: Configurar la conexión

Editar el archivo `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'visitor_management');
define('DB_USER', 'tu_usuario');   // Cambiar
define('DB_PASS', 'tu_password');  // Cambiar
```

### Paso 4: Iniciar el servidor

Iniciar Apache y MySQL desde XAMPP/WAMP y abrir en el navegador:

```
http://localhost/visitor-management/
```

---

## Credenciales de Prueba

| Rol | Email | Contraseña |
|-----|-------|------------|
| Administrador | admin@sistema.local | Admin1234! |
| Recepcionista | recepcion@sistema.local | Recep1234! |

> ⚠️ Cambiar estas credenciales antes de un despliegue real.

---

## Arquitectura del Sistema

```
┌─────────────────────────────────────────────────────────┐
│                  NAVEGADOR DEL USUARIO                   │
│         HTML5 + CSS3 + Bootstrap 5 + JavaScript          │
└──────────────────────┬──────────────────────────────────┘
                       │ HTTP/HTTPS
                       │ Cookie de Sesión (HttpOnly)
┌──────────────────────▼──────────────────────────────────┐
│              SERVIDOR WEB (Apache/Nginx)                  │
│                    PHP 8.0+                               │
│                                                           │
│  ┌─────────────┐  ┌──────────────┐  ┌────────────────┐  │
│  │ auth/       │  │ visitors/    │  │ config/        │  │
│  │ login.php   │  │ register.php │  │ database.php   │  │
│  │ logout.php  │  │ list.php     │  │                │  │
│  │ auth_check  │  │ detail.php   │  │                │  │
│  └─────────────┘  │ exit.php     │  └────────────────┘  │
│                   └──────────────┘                       │
└──────────────────────┬──────────────────────────────────┘
                       │ PDO (Prepared Statements)
┌──────────────────────▼──────────────────────────────────┐
│                  BASE DE DATOS MySQL                      │
│                                                           │
│   users ──── visits ──── visitors                         │
│                 │                                         │
│              devices                                      │
└─────────────────────────────────────────────────────────┘
```

### Flujo de una Petición Protegida

```
Usuario → Página protegida
              │
              ▼
         auth_check.php
              │
    ┌─────────┴──────────┐
    │                    │
  Sin sesión          Con sesión
    │                    │
    ▼                    ▼
Redirect             Verificar
login.php            expiración (30 min)
                         │
                    ┌────┴────┐
                  Expiró    Vigente
                    │          │
                    ▼          ▼
               Redirect    Mostrar
               +expired=1  página
```

---

## Arquitectura de Seguridad

### 1. Autenticación — `password_hash()` con bcrypt

Las contraseñas **nunca se almacenan en texto plano**. Se usa `password_hash()` de PHP con el algoritmo `PASSWORD_BCRYPT` y un factor de costo de 12:

```php
// Guardar (una sola vez al crear usuario):
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Verificar en cada login:
$ok = password_verify($input_password, $hash_from_db);
```

**Por qué bcrypt y no MD5/SHA1:**
- MD5/SHA1 son funciones de hash rápidas → un atacante puede probar millones de contraseñas por segundo
- bcrypt es intencionalmente lento (cost=12 = ~2^12 iteraciones) → hace los ataques de fuerza bruta inviables
- bcrypt incluye un `salt` aleatorio automático → dos hashes del mismo password son diferentes

### 2. Gestión de Sesiones — `$_SESSION` con flags seguros

```php
session_start([
    'cookie_httponly' => true,   // JS no puede robar la cookie
    'cookie_secure'   => true,   // Solo HTTPS
    'cookie_samesite' => 'Strict' // Protección CSRF básica
]);
```

- **HttpOnly**: Previene ataques XSS que intentan robar el Session ID via `document.cookie`
- **Secure**: La cookie solo viaja por HTTPS, no puede ser capturada en redes inseguras
- **SameSite Strict**: La cookie no se envía en peticiones cross-site (protege contra CSRF)
- **session_regenerate_id(true)**: Al hacer login exitoso, se genera un nuevo Session ID para prevenir Session Fixation attacks

### 3. Protección CSRF (Cross-Site Request Forgery)

Cada formulario incluye un token CSRF único y aleatorio:

```php
// Generar (al mostrar el formulario):
$_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // 64 chars hex

// Verificar (al procesar el POST):
hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
// hash_equals es resistente a timing attacks (comparación en tiempo constante)
```

### 4. Prevención de SQL Injection — Prepared Statements

Todas las queries usan `PDO` con `Prepared Statements`. Los datos del usuario **NUNCA** se concatenan directamente en el SQL:

```php
// ✅ CORRECTO (inmune a SQL Injection):
$stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);

// ❌ INCORRECTO (vulnerable):
$query = "SELECT * FROM users WHERE email = '$email'";
```

### 5. Prevención de XSS (Cross-Site Scripting)

Todo dato que proviene del usuario o de la base de datos y se muestra en HTML pasa por `htmlspecialchars()`:

```php
echo htmlspecialchars($user_data); 
// Convierte < > " ' & en entidades HTML
// Ej: <script> → &lt;script&gt; (se muestra como texto, no ejecuta)
```

### 6. Validación de MAC Address

La dirección MAC se valida en tres capas:

| Capa | Método | Propósito |
|------|--------|-----------|
| Frontend (JS) | Regex + autoformato | UX: feedback en tiempo real |
| Backend (PHP) | `preg_match()` | Seguridad: validación real |
| Base de datos | `CHECK constraint` | Integridad: último escudo |

### 7. Expiración de Sesión por Inactividad

```php
$session_lifetime = 30 * 60; // 30 minutos
if (time() - $_SESSION['last_activity'] > $session_lifetime) {
    session_destroy();
    header("Location: login.php?expired=1");
}
$_SESSION['last_activity'] = time(); // Renovar en cada petición
```

### 8. Transacciones de Base de Datos

El registro de visita + dispositivos usa una transacción SQL para garantizar consistencia:

```php
$db->beginTransaction();
// ... INSERT visitors ...
// ... INSERT visits ...
// ... INSERT devices ...
$db->commit(); // Solo si todo salió bien
// Si algo falla: $db->rollback() → no quedan datos incompletos
```

---

## Propuesta de Segmentación de Red (VLAN)

### Objetivo

Aislar el tráfico de red de los visitantes del tráfico de la red corporativa interna, garantizando que:
- Los visitantes tengan acceso a Internet
- Los visitantes **no puedan acceder** a servidores internos, bases de datos ni equipos administrativos
- El sistema de gestión de visitantes permanezca en la red corporativa

### Diseño de VLANs

```
                    ┌──────────────────────────┐
                    │    ROUTER / FIREWALL      │
                    │  (pfSense / FortiGate)    │
                    │                           │
                    │  Reglas ACL:              │
                    │  ✅ VLAN10 → Internet     │
                    │  ✅ VLAN20 → Internet     │
                    │  ❌ VLAN20 → VLAN10       │
                    │  ❌ VLAN20 → 10.0.10.0/24 │
                    └────────┬─────────┬────────┘
                             │         │
               ┌─────────────┘         └──────────────┐
               │                                       │
    ┌──────────▼──────────┐             ┌─────────────▼──────────┐
    │      VLAN 10         │             │       VLAN 20          │
    │   RED CORPORATIVA    │             │    RED VISITANTES      │
    │   10.0.10.0/24       │             │    192.168.20.0/24     │
    │                      │             │                        │
    │  • Servidores intern.│             │  • WiFi "Visitantes"   │
    │  • Base de datos     │             │  • Solo acceso Internet│
    │  • PCs administrativ.│             │  • Sin acceso a VLAN10 │
    │  • Sistema visitantes│             │  • BW limitado: 10Mbps │
    │  • Impresoras        │             │  • DNS filtrado        │
    └──────────────────────┘             └────────────────────────┘
```

### Proceso de Autorización de Dispositivo Visitante

```
1. RECEPCIÓN registra la MAC del dispositivo en el sistema
        │
        ▼
2. El SISTEMA guarda la MAC en la tabla `devices` (BD interna)
        │
        ▼
3. Opcionalmente, el sistema notifica al CONTROLADOR WiFi
   (via API REST del Access Point, ej: UniFi Controller)
        │
        ▼
4. El VISITANTE conecta su dispositivo al SSID "Visitantes"
        │
        ▼
5. El ACCESS POINT valida la MAC contra la lista blanca
        │
   ┌────┴────┐
   │ En lista│            │ No está │
   │ blanca  │            │en lista │
        │                       │
        ▼                       ▼
6. DHCP asigna IP         Rechaza conexión
   en 192.168.20.x        o portal cautivo
        │
        ▼
7. Tráfico va por VLAN20 (aislado de VLAN10)
        │
        ▼
8. Al TERMINAR la visita, la MAC se desactiva del sistema
```

### Configuración Recomendada

| Parámetro | VLAN 10 (Corporativa) | VLAN 20 (Visitantes) |
|-----------|----------------------|----------------------|
| Red | 10.0.10.0/24 | 192.168.20.0/24 |
| SSID WiFi | CorpNet (oculto, WPA2-Enterprise) | Visitantes (visible, WPA2-PSK) |
| DHCP Lease | 8 horas | 4 horas |
| Ancho de banda | Sin límite | 10 Mbps bajada / 5 Mbps subida |
| Acceso a Internet | Sí | Sí (con filtrado) |
| Acceso inter-VLAN | Controlado | Bloqueado (DENY ALL hacia VLAN10) |
| DNS | Interno + externo | Solo 8.8.8.8 / 1.1.1.1 |

### Protocolo de Autenticación WiFi Recomendado

Para la red corporativa (VLAN 10): **WPA2-Enterprise con 802.1X**
- Cada empleado se autentica con sus credenciales de dominio (Active Directory / LDAP)
- Certificados digitales por dispositivo

Para la red de visitantes (VLAN 20): **WPA2-PSK + MAC Filtering**
- Contraseña general del WiFi visitantes (renovar periódicamente)
- Filtrado por MAC registrada en el sistema
- Portal cautivo opcional para términos de uso

---

## Estructura de Archivos

```
visitor-management/
├── index.php                   ← Punto de entrada
├── login.php                   ← Página de login
├── dashboard.php               ← Dashboard principal
│
├── auth/
│   ├── auth_check.php          ← Middleware de sesión
│   ├── login.php               ← Procesador del login
│   └── logout.php              ← Cierre de sesión
│
├── visitors/
│   ├── register.php            ← Registro de visita
│   ├── list.php                ← Lista con búsqueda
│   ├── detail.php              ← Detalle de visita
│   └── exit.php                ← Registrar salida
│
├── config/
│   └── database.php            ← Conexión PDO a MySQL
│
├── includes/
│   ├── header.php              ← Navbar + Bootstrap
│   └── footer.php              ← Scripts + cierre HTML
│
├── assets/
│   ├── css/custom.css          ← Estilos personalizados
│   └── js/mac-validator.js     ← Validador MAC en JS
│
├── database/
│   └── schema.sql              ← Script de creación de BD
│
└── README.md                   ← Este archivo
```
