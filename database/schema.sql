-- ============================================================
-- SISTEMA DE GESTIÓN DE VISITANTES
-- Archivo: database/schema.sql
-- Descripción: Script para crear toda la estructura de la BD.
--              Ejecutar este archivo UNA SOLA VEZ para inicializar.
-- ============================================================

-- Crear la base de datos si no existe
CREATE DATABASE IF NOT EXISTS visitor_management
    CHARACTER SET utf8mb4          -- Soporte completo de caracteres (emojis, acentos)
    COLLATE utf8mb4_unicode_ci;    -- Comparación insensible a mayúsculas/acentos

USE visitor_management;

-- ============================================================
-- TABLA: users
-- Almacena los usuarios del sistema (recepcionistas y admins)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,         -- Nombre de usuario único
    email       VARCHAR(100) NOT NULL UNIQUE,         -- Email único para login
    password    VARCHAR(255) NOT NULL,                -- Hash bcrypt (NUNCA texto plano)
    role        ENUM('admin','receptionist')          -- Roles disponibles
                NOT NULL DEFAULT 'receptionist',
    active      TINYINT(1) NOT NULL DEFAULT 1,        -- 1=activo, 0=desactivado
    created_at  DATETIME NOT NULL DEFAULT NOW(),      -- Fecha de creación
    last_login  DATETIME NULL                         -- Último acceso (puede ser NULL)
);

-- ============================================================
-- TABLA: visitors
-- Almacena los datos personales de cada visitante.
-- Un visitante puede venir múltiples veces (1 registro, N visitas)
-- ============================================================
CREATE TABLE IF NOT EXISTS visitors (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(150) NOT NULL,                -- Nombre completo
    id_number   VARCHAR(30)  NOT NULL UNIQUE,         -- DNI, Cédula, Pasaporte
    email       VARCHAR(100) NULL,                    -- Email (opcional)
    phone       VARCHAR(20)  NULL,                    -- Teléfono (opcional)
    company     VARCHAR(100) NULL,                    -- Empresa de procedencia
    created_at  DATETIME NOT NULL DEFAULT NOW()
);

-- ============================================================
-- TABLA: visits
-- Registro de cada visita individual.
-- Relaciona un visitante con quién visita, motivo y tiempos.
-- ============================================================
CREATE TABLE IF NOT EXISTS visits (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    visitor_id      INT NOT NULL,                     -- FK → visitors.id
    host_name       VARCHAR(150) NOT NULL,            -- Nombre del empleado visitado
    host_department VARCHAR(100) NULL,                -- Departamento del empleado
    reason          TEXT NOT NULL,                    -- Motivo de la visita
    entry_time      DATETIME NOT NULL DEFAULT NOW(),  -- Hora de entrada
    exit_time       DATETIME NULL,                    -- Hora de salida (NULL = aún dentro)
    status          ENUM('active','completed','cancelled')
                    NOT NULL DEFAULT 'active',        -- Estado actual de la visita
    registered_by   INT NOT NULL,                     -- FK → users.id (quién registró)
    notes           TEXT NULL,                        -- Observaciones adicionales
    
    -- Claves foráneas para mantener integridad referencial
    CONSTRAINT fk_visit_visitor  FOREIGN KEY (visitor_id)    REFERENCES visitors(id),
    CONSTRAINT fk_visit_user     FOREIGN KEY (registered_by) REFERENCES users(id)
);

-- ============================================================
-- TABLA: devices
-- Dispositivos personales del visitante registrados en la visita.
-- Una visita puede tener múltiples dispositivos.
-- ============================================================
CREATE TABLE IF NOT EXISTS devices (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    visit_id    INT NOT NULL,                         -- FK → visits.id
    visitor_id  INT NOT NULL,                         -- FK → visitors.id (redundante pero útil para queries)
    device_type ENUM('laptop','smartphone','tablet','other')
                NOT NULL DEFAULT 'laptop',
    device_name VARCHAR(100) NULL,                    -- Ej: "MacBook Pro de Juan"
    mac_address VARCHAR(17)  NOT NULL,                -- Formato: AA:BB:CC:DD:EE:FF
    registered_at DATETIME NOT NULL DEFAULT NOW(),
    
    CONSTRAINT fk_device_visit   FOREIGN KEY (visit_id)   REFERENCES visits(id),
    CONSTRAINT fk_device_visitor FOREIGN KEY (visitor_id) REFERENCES visitors(id)
);

-- ============================================================
-- ÍNDICES para mejorar velocidad en búsquedas frecuentes
-- ============================================================
CREATE INDEX idx_visits_status      ON visits(status);
CREATE INDEX idx_visits_entry_time  ON visits(entry_time);
CREATE INDEX idx_devices_mac        ON devices(mac_address);
CREATE INDEX idx_visitors_id_number ON visitors(id_number);

-- ============================================================
-- DATOS INICIALES: Usuario administrador por defecto
-- 
-- Credenciales de prueba:
--   Email:    admin@sistema.local
--   Password: Admin1234!
--
-- El hash fue generado con: password_hash('Admin1234!', PASSWORD_BCRYPT, ['cost'=>12])
-- IMPORTANTE: Cambiar la contraseña en el primer login real
-- ============================================================
INSERT INTO users (username, email, password, role) VALUES (
    'admin',
    'admin@sistema.local',
    '$2b$12$ZnsiK.QIvLJ6NhjPQ2uKG.vjbV37C8nSawWYoWW1UnJyyuOkI5peW', -- password: Admin1234!
    'admin'
);

-- Usuario recepcionista de prueba
-- Password: Recep1234!
INSERT INTO users (username, email, password, role) VALUES (
    'recepcion',
    'recepcion@sistema.local',
    '$2b$12$udezlGQB4TUcmjuAmfZBvOghfIyGnaoXgxbEyIYvc63arye8Wl2.i', -- password: Recep1234!
    'receptionist'
);
