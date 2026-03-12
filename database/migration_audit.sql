-- ============================================================
-- MIGRACIÓN: Módulo de Auditoría + Roles extendidos
-- Archivo: database/migration_audit.sql
-- Descripción: Agrega la tabla audit_log y extiende el ENUM
--              de roles para soportar 'guard' (vigilante).
-- Ejecutar UNA sola vez sobre la BD visitor_management.
-- ============================================================

USE visitor_management;

-- ── 1. Extender el ENUM de roles en users ───────────────────
-- Agregamos 'guard' sin borrar los roles existentes
ALTER TABLE users
    MODIFY COLUMN role ENUM('admin','receptionist','guard')
    NOT NULL DEFAULT 'receptionist';

-- ── 2. Tabla de auditoría ────────────────────────────────────
-- Registra TODA acción relevante del sistema.
-- ON DELETE SET NULL en user_id: si se borra el usuario,
--   el log se conserva (trazabilidad) pero con user_id = NULL.
CREATE TABLE IF NOT EXISTS audit_log (
    id           INT AUTO_INCREMENT PRIMARY KEY,

    -- Quién realizó la acción (snapshot del momento)
    user_id      INT          NULL,           -- FK → users.id (nullable)
    user_name    VARCHAR(100) NOT NULL,        -- Nombre en el momento del evento
    user_role    VARCHAR(50)  NOT NULL,        -- Rol en el momento del evento

    -- Qué acción se realizó
    action       VARCHAR(100) NOT NULL,        -- Código de acción: 'CREATE_USER', etc.
    entity_type  VARCHAR(50)  NOT NULL,        -- Entidad afectada: 'user','visitor','visit'
    entity_id    INT          NULL,            -- ID del registro afectado
    description  TEXT         NOT NULL,        -- Descripción legible del evento

    -- Contexto técnico
    ip_address   VARCHAR(45)  NULL,           -- IPv4 o IPv6 del cliente
    user_agent   VARCHAR(255) NULL,           -- Navegador/cliente HTTP

    -- Cuándo
    created_at   DATETIME     NOT NULL DEFAULT NOW(),

    -- Relación débil: conservar log aunque se borre el usuario
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE SET NULL
);

-- Índices para búsquedas frecuentes en el log
CREATE INDEX idx_audit_action      ON audit_log(action);
CREATE INDEX idx_audit_entity      ON audit_log(entity_type, entity_id);
CREATE INDEX idx_audit_user        ON audit_log(user_id);
CREATE INDEX idx_audit_created_at  ON audit_log(created_at);

-- ── 3. Registro inicial: migración ejecutada ─────────────────
-- El primer evento del log es la propia migración
INSERT INTO audit_log (user_id, user_name, user_role, action, entity_type, entity_id, description, ip_address)
VALUES (NULL, 'Sistema', 'system', 'MIGRATION_RUN', 'system', NULL,
        'Migración inicial: tabla audit_log creada y roles actualizados.', '127.0.0.1');
