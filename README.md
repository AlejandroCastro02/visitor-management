# 🏢 Sistema de Gestión de Visitantes

Prototipo funcional para el registro de visitantes en Oficinas Centrales.

---

## 🚀 Historial de Versiones

| Versión | Descripción |
|---------|-------------|
| **v2.0** | Panel de administración completo, módulo de auditoría y sistema de roles con permisos diferenciados |
| v1.0 | Registro de visitas, gestión básica de visitantes y validación de dispositivos por MAC |

---

## 📋 Tabla de Contenidos

1. [Requisitos](#requisitos)
2. [Instalación](#instalación)
3. [Instalación desde v1.0 (Migración)](#instalación-desde-v10-migración)
4. [Credenciales de Prueba](#credenciales-de-prueba)
5. [Novedades v2.0](#novedades-v20)
6. [Roles y Permisos](#roles-y-permisos)
7. [Arquitectura del Sistema](#arquitectura-del-sistema)
8. [Arquitectura de Seguridad](#arquitectura-de-seguridad)
9. [Propuesta de Segmentación de Red VLAN](#propuesta-de-segmentación-de-red-vlan)
10. [Estructura de Archivos](#estructura-de-archivos)

---

## Requisitos

- PHP 8.0 o superior
- MySQL 5.7 o superior (o MariaDB 10.3+)
- Servidor web: Apache (XAMPP/WAMP) o Nginx
- Extensiones PHP: `pdo`, `pdo_mysql`, `openssl`

---

## Instalación

### Instalación nueva (desde cero)

#### Paso 1: Clonar o descomprimir el proyecto

Colocar la carpeta `visitor-management` dentro de tu servidor web:
- XAMPP: `C:/xampp/htdocs/visitor-management`
- WAMP: `C:/wamp64/www/visitor-management`
- Linux: `/var/www/html/visitor-management`

#### Paso 2: Crear la base de datos

Abrir phpMyAdmin o MySQL CLI y ejecutar:

```sql
SOURCE /ruta/al/proyecto/database/schema.sql
```

O copiar y pegar el contenido del archivo `database/schema.sql` directamente en phpMyAdmin.

#### Paso 3: Ejecutar la migración v2.0

Ejecutar también el archivo de migración para agregar la tabla de auditoría y el nuevo rol:

```sql
SOURCE /ruta/al/proyecto/database/migration_audit.sql
```

#### Paso 4: Configurar la conexión

Editar el archivo `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'visitor_management');
define('DB_USER', 'tu_usuario');   // Cambiar
define('DB_PASS', 'tu_password');  // Cambiar
```

#### Paso 5: Iniciar el servidor

Iniciar Apache y MySQL desde XAMPP/WAMP y abrir en el navegador:

```
http://localhost/visitor-management/
```

---

## Instalación desde v1.0 (Migración)

Si ya tienes v1.0 instalada y en uso, **no ejecutes `schema.sql`** ya que borraría tus datos. Solo sigue estos pasos:

**Paso 1:** Reemplazar todos los archivos PHP del proyecto con los de v2.0.

**Paso 2:** Ejecutar únicamente la migración sobre la BD existente:

```sql
SOURCE /ruta/al/proyecto/database/migration_audit.sql
```

Esto agrega el rol `guard` a la tabla `users` y crea la tabla `audit_log` sin tocar ningún dato existente.

**Paso 3:** Verificar el acceso iniciando sesión con el usuario administrador y comprobando que aparece el menú **Administración** en la barra de navegación.

---

## Credenciales de Prueba

| Rol | Email | Contraseña |
|-----|-------|------------|
| Administrador | admin@sistema.local | Admin1234! |
| Recepcionista | recepcion@sistema.local | Recep1234! |

> ⚠️ Cambiar estas credenciales antes de un despliegue real. Usar `setup.php` para regenerar los hashes en el servidor de destino y eliminarlo después.

---

## Novedades v2.0

### 🛡️ Panel de Administración de Usuarios

Accesible desde el menú **Administración → Gestión de Usuarios** (solo rol `admin`).

Permite el ciclo CRUD completo sobre las cuentas del sistema:

- **Crear** usuarios con validación de contraseña segura (mínimo 8 caracteres, mayúscula, minúscula y número).
- **Consultar** la lista de usuarios con filtro por nombre, email y rol.
- **Editar** datos del usuario incluyendo cambio de contraseña opcional.
- **Activar / Desactivar** cuentas sin eliminar el historial de actividad asociado.
- **Eliminar** usuarios con protección ante registros vinculados en la BD.

Reglas de negocio aplicadas en el servidor (no eludibles desde el navegador):

- Un administrador no puede desactivar ni degradar su propia cuenta.
- No se puede eliminar ni desactivar al único administrador activo del sistema.
- Si un usuario tiene visitas registradas, la integridad referencial de la BD impide su eliminación, indicando al operador que debe desactivarlo en su lugar.

### 👥 Gestión de Visitantes desde el Panel Admin

El administrador puede editar y eliminar registros de visitantes directamente desde la vista de detalle:

- **Editar** corrige datos personales (nombre, identificación, email, teléfono, empresa) con validación de unicidad del número de identificación.
- **Eliminar** borra al visitante junto con todas sus visitas y dispositivos en una única transacción atómica, garantizando que no queden registros huérfanos.

### 📋 Módulo de Auditoría (Bitácora de Eventos)

Accesible desde **Administración → Bitácora de Auditoría**. Registra de forma automática cada acción relevante del sistema con tres datos clave:

- **Quién:** nombre y rol del usuario que ejecutó la acción, capturado en el momento del evento.
- **Qué:** código de acción estandarizado (`CREATE_USER`, `DELETE_VISITOR`, `EXIT_VISIT`, etc.) con descripción legible de los campos modificados.
- **Cuándo:** timestamp exacto con fecha y hora.

Adicionalmente registra la dirección IP del cliente y el User-Agent para contexto forense.

La bitácora es de solo lectura para todos los roles, incluyendo el administrador, garantizando su integridad. Si un usuario es eliminado del sistema, sus eventos en el log se conservan con `user_id = NULL` gracias a la restricción `ON DELETE SET NULL`.

La vista ofrece seis filtros combinables: búsqueda de texto libre, tipo de acción, entidad afectada, usuario ejecutor, fecha desde y fecha hasta.

### 🔐 Sistema de Roles con Permisos Diferenciados

Se incorpora el rol **Vigilante** (`guard`) con acceso restringido. La siguiente tabla resume los permisos por rol:

| Acción | Administrador | Recepcionista | Vigilante |
|--------|:---:|:---:|:---:|
| Ver dashboard y métricas | ✅ | ✅ | ✅ |
| Ver lista de visitantes | ✅ | ✅ | ✅ |
| Ver detalle de visita | ✅ | ✅ | ✅ |
| Registrar nueva visita | ✅ | ✅ | ❌ |
| Registrar salida de visitante | ✅ | ✅ | ✅ |
| Editar datos de visitante | ✅ | ❌ | ❌ |
| Eliminar visitante | ✅ | ❌ | ❌ |
| Gestión de usuarios del sistema | ✅ | ❌ | ❌ |
| Consultar bitácora de auditoría | ✅ | ❌ | ❌ |

Los permisos se verifican en el servidor en cada petición. Intentar acceder a una URL restringida devuelve HTTP 403 independientemente de cómo esté configurado el navegador.

---

## Roles y Permisos

El sistema cuenta con tres roles definidos en la base de datos:

**`admin` — Administrador**
Acceso total al sistema. Gestiona usuarios, visualiza la bitácora completa y puede editar o eliminar cualquier registro. Debe ser asignado con precaución.

**`receptionist` — Recepcionista**
Rol operativo principal. Registra nuevas visitas y dispositivos, gestiona entradas y salidas. No tiene acceso al panel de administración ni a la bitácora.

**`guard` — Vigilante**
Rol de consulta y control de salidas. Puede ver el listado completo de visitas activas y registrar salidas, pero no puede crear nuevos registros ni acceder a configuraciones del sistema.

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
│  │ auth/       │  │ visitors/    │  │ admin/         │  │
│  │ login.php   │  │ register.php │  │ users.php      │  │
│  │ logout.php  │  │ list.php     │  │ user_form.php  │  │
│  │ auth_check  │  │ detail.php   │  │ audit_log.php  │  │
│  └─────────────┘  │ exit.php     │  │ visitor_edit   │  │
│                   └──────────────┘  └────────────────┘  │
│  ┌──────────────────────────────────────────────────┐   │
│  │ includes/audit.php  ← Helper centralizado de log │   │
│  └──────────────────────────────────────────────────┘   │
└──────────────────────┬──────────────────────────────────┘
                       │ PDO (Prepared Statements)
┌──────────────────────▼──────────────────────────────────┐
│                  BASE DE DATOS MySQL                      │
│                                                           │
│   users ──── visits ──── visitors                         │
│                 │                                         │
│              devices                                      │
│                                                           │
│   audit_log  (registra todas las acciones del sistema)    │
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
               Redirect    Verificar rol
               +expired=1  (requireAnyRole)
                               │
                      ┌────────┴────────┐
                   Sin permiso       Con permiso
                      │                  │
                      ▼                  ▼
                  HTTP 403          Mostrar página
                  Acceso            + logAudit()
                  denegado          si hay cambios
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

### 6. Control de Acceso por Rol (RBAC)

Implementado en `auth/auth_check.php` mediante dos funciones:

```php
// Bloquea con HTTP 403 si el rol no está en la lista permitida:
requireAnyRole(['admin', 'receptionist']);

// Devuelve true/false para mostrar u ocultar elementos en la vista:
canRegisterVisit();
```

La verificación ocurre en el servidor en cada petición. No es posible eludirla manipulando el HTML o la URL desde el navegador.

### 7. Validación de MAC Address

La dirección MAC se valida en tres capas:

| Capa | Método | Propósito |
|------|--------|-----------|
| Frontend (JS) | Regex + autoformato | UX: feedback en tiempo real |
| Backend (PHP) | `preg_match()` | Seguridad: validación real |
| Base de datos | `CHECK constraint` | Integridad: último escudo |

### 8. Expiración de Sesión por Inactividad

```php
$session_lifetime = 30 * 60; // 30 minutos
if (time() - $_SESSION['last_activity'] > $session_lifetime) {
    session_destroy();
    header("Location: login.php?expired=1");
}
$_SESSION['last_activity'] = time(); // Renovar en cada petición
```

### 9. Transacciones de Base de Datos

Las operaciones que afectan múltiples tablas (registro de visita + dispositivos, eliminación de visitante) usan transacciones SQL para garantizar consistencia:

```php
$db->beginTransaction();
// ... INSERT / DELETE en múltiples tablas ...
$db->commit();   // Solo si todo salió bien
// Si algo falla:
$db->rollBack(); // No quedan datos incompletos ni huérfanos
```

### 10. Integridad del Log de Auditoría

El log está diseñado para ser resistente a la eliminación de usuarios:

```sql
CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE SET NULL
```

Si un usuario es eliminado del sistema, todos sus eventos históricos se conservan con `user_id = NULL`. El nombre y el rol quedan grabados en columnas independientes (`user_name`, `user_role`) en el momento del evento, por lo que la trazabilidad no se pierde.

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
├── index.php                       ← Punto de entrada
├── login.php                       ← Página de login
├── dashboard.php                   ← Dashboard principal
│
├── auth/
│   ├── auth_check.php              ← Middleware de sesión y roles
│   ├── login.php                   ← Procesador del login
│   └── logout.php                  ← Cierre de sesión
│
├── visitors/
│   ├── register.php                ← Registro de visita (admin + recepcionista)
│   ├── list.php                    ← Lista con búsqueda y filtros
│   ├── detail.php                  ← Detalle de visita + acciones admin
│   └── exit.php                    ← Registrar salida (todos los roles)
│
├── admin/                          ← 🆕 Panel de administración (solo admin)
│   ├── users.php                   ← Lista y gestión de usuarios
│   ├── user_form.php               ← Formulario crear/editar usuario
│   ├── user_save.php               ← Procesador POST crear/editar
│   ├── user_toggle.php             ← Activar/desactivar cuenta
│   ├── user_delete.php             ← Eliminar usuario
│   ├── visitor_edit.php            ← Editar datos de visitante
│   ├── visitor_save.php            ← Procesador POST editar visitante
│   ├── visitor_delete.php          ← Eliminar visitante (con transacción)
│   └── audit_log.php               ← Bitácora de auditoría
│
├── config/
│   └── database.php                ← Conexión PDO a MySQL
│
├── includes/
│   ├── header.php                  ← Navbar + Bootstrap (con menú admin)
│   ├── footer.php                  ← Scripts + cierre HTML
│   └── audit.php                   ← 🆕 Helper centralizado de auditoría
│
├── assets/
│   ├── css/custom.css              ← Estilos personalizados
│   └── js/mac-validator.js         ← Validador MAC en JS
│
├── database/
│   ├── schema.sql                  ← Script de creación de BD (instalación nueva)
│   └── migration_audit.sql         ← 🆕 Migración v2.0 (actualización desde v1.0)
│
└── README.md                       ← Este archivo
```
