-- ═══════════════════════════════════════════════════════════════════════
-- TuDu · Migración SaaS Multi-Tenant v1.0
-- Ejecutar UNA SOLA VEZ sobre tudu_v3
-- ═══════════════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. EDICIÓN SaaS en organizaciones ────────────────────────────────
ALTER TABLE organizaciones
  ADD COLUMN IF NOT EXISTS `edition`           ENUM('standalone','corp') NOT NULL DEFAULT 'standalone' AFTER `nombre`,
  ADD COLUMN IF NOT EXISTS `plan`              ENUM('starter','pro','agency','enterprise') NOT NULL DEFAULT 'starter' AFTER `edition`,
  ADD COLUMN IF NOT EXISTS `plan_status`       ENUM('active','trialing','past_due','cancelled','inactive') NOT NULL DEFAULT 'trialing' AFTER `plan`,
  ADD COLUMN IF NOT EXISTS `trial_ends_at`     TIMESTAMP NULL AFTER `plan_status`,
  ADD COLUMN IF NOT EXISTS `plan_renews_at`    TIMESTAMP NULL AFTER `trial_ends_at`,
  ADD COLUMN IF NOT EXISTS `stripe_customer_id`      VARCHAR(100) NULL AFTER `plan_renews_at`,
  ADD COLUMN IF NOT EXISTS `stripe_subscription_id`  VARCHAR(100) NULL AFTER `stripe_customer_id`,
  ADD COLUMN IF NOT EXISTS `conekta_order_id`        VARCHAR(100) NULL AFTER `stripe_subscription_id`,
  ADD COLUMN IF NOT EXISTS `members_limit`     SMALLINT UNSIGNED NOT NULL DEFAULT 3 AFTER `conekta_order_id`,
  ADD COLUMN IF NOT EXISTS `projects_limit`    SMALLINT UNSIGNED NOT NULL DEFAULT 5 AFTER `members_limit`,
  ADD COLUMN IF NOT EXISTS `tasks_limit`       INT UNSIGNED NOT NULL DEFAULT 100 AFTER `projects_limit`,
  ADD COLUMN IF NOT EXISTS `storage_limit_mb`  INT UNSIGNED NOT NULL DEFAULT 500 AFTER `tasks_limit`,
  ADD COLUMN IF NOT EXISTS `whatsapp_bot`      TINYINT(1) NOT NULL DEFAULT 0 AFTER `storage_limit_mb`;

-- Índices útiles para queries admin
ALTER TABLE organizaciones
  ADD INDEX IF NOT EXISTS `idx_orgs_plan_status` (`plan_status`),
  ADD INDEX IF NOT EXISTS `idx_orgs_edition` (`edition`);

-- ── 2. Campo whatsapp en usuarios (para notificaciones Twilio) ────────
ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS `whatsapp`          VARCHAR(20) NULL AFTER `telefono`,
  ADD COLUMN IF NOT EXISTS `whatsapp_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `whatsapp`,
  ADD COLUMN IF NOT EXISTS `guest_token`       VARCHAR(64) NULL COMMENT 'Token temporal para invitados sin cuenta' AFTER `whatsapp_verified`;

-- Índice para búsqueda por teléfono/whatsapp (modelo WhatsApp)
ALTER TABLE usuarios
  ADD INDEX IF NOT EXISTS `idx_usuarios_telefono`  (`telefono`),
  ADD INDEX IF NOT EXISTS `idx_usuarios_whatsapp`  (`whatsapp`),
  ADD INDEX IF NOT EXISTS `idx_usuarios_guest_token` (`guest_token`);

-- ── 3. TABLA subscripciones ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS subscripciones (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id         BIGINT UNSIGNED NOT NULL,
    edition                 ENUM('standalone','corp') NOT NULL DEFAULT 'standalone',
    plan                    ENUM('starter','pro','agency','enterprise') NOT NULL,
    status                  ENUM('active','cancelled','past_due','trialing') NOT NULL DEFAULT 'trialing',
    payment_provider        ENUM('stripe','conekta') NULL,

    -- IDs externos
    stripe_subscription_id  VARCHAR(100) NULL UNIQUE,
    stripe_customer_id      VARCHAR(100) NULL,
    conekta_order_id        VARCHAR(100) NULL,

    -- Precio en centavos MXN (39900 = $399.00 MXN)
    amount_mxn              INT UNSIGNED NOT NULL DEFAULT 0,

    -- Snapshot de límites al momento de suscribir
    members_limit           SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    projects_limit          SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    tasks_limit             INT UNSIGNED NOT NULL DEFAULT 100,
    storage_limit_mb        INT UNSIGNED NOT NULL DEFAULT 500,

    -- Fechas
    trial_ends_at           TIMESTAMP NULL,
    current_period_start    TIMESTAMP NULL,
    current_period_end      TIMESTAMP NULL,
    cancelled_at            TIMESTAMP NULL,
    created_at              TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_subs_org
        FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE,

    INDEX idx_subs_org_status       (organizacion_id, status),
    INDEX idx_subs_stripe_sub       (stripe_subscription_id),
    INDEX idx_subs_period_end       (current_period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── 4. TABLA system_settings (API keys, configuración global) ─────────
CREATE TABLE IF NOT EXISTS system_settings (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key`       VARCHAR(100) NOT NULL UNIQUE,
    `value`     TEXT NULL COMMENT 'Valor encriptado con AES o base64',
    label       VARCHAR(150) NULL,
    description VARCHAR(255) NULL,
    updated_by  BIGINT UNSIGNED NULL,
    created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_settings_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── 5. TABLA whatsapp_messages (log de conversaciones Twilio) ─────────
CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tarea_id        BIGINT UNSIGNED NULL,
    usuario_id      BIGINT UNSIGNED NULL,
    organizacion_id BIGINT UNSIGNED NULL,
    direccion       ENUM('outbound','inbound') NOT NULL DEFAULT 'outbound',
    from_number     VARCHAR(20) NOT NULL,
    to_number       VARCHAR(20) NOT NULL,
    body            TEXT NOT NULL,
    twilio_sid      VARCHAR(100) NULL,
    status          ENUM('queued','sent','delivered','failed','received') NOT NULL DEFAULT 'queued',
    created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_wa_tarea    (tarea_id),
    INDEX idx_wa_usuario  (usuario_id),
    INDEX idx_wa_status   (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── 6. TABLA guest_access (acceso temporal sin cuenta) ───────────────
CREATE TABLE IF NOT EXISTS guest_access (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tarea_id    BIGINT UNSIGNED NOT NULL,
    token       VARCHAR(64) NOT NULL UNIQUE,
    nombre      VARCHAR(100) NULL COMMENT 'Nombre que el invitado escribe',
    telefono    VARCHAR(20) NULL,
    email       VARCHAR(255) NULL,
    acciones    SET('view','comment','complete') NOT NULL DEFAULT 'view,comment',
    expires_at  TIMESTAMP NULL,
    last_used   TIMESTAMP NULL,
    created_by  BIGINT UNSIGNED NULL,
    created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_guest_token   (token),
    INDEX idx_guest_tarea   (tarea_id),
    INDEX idx_guest_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── 7. Seed: API Keys vacías en system_settings ───────────────────────
INSERT IGNORE INTO system_settings (`key`, label, description) VALUES
('TWILIO_ACCOUNT_SID',   'Twilio Account SID',    'Cuenta Twilio para envío de WhatsApp · twilio.com'),
('TWILIO_AUTH_TOKEN',    'Twilio Auth Token',      'Token de autenticación Twilio'),
('TWILIO_WHATSAPP_FROM', 'Twilio WhatsApp From',   'Ej: whatsapp:+14155238886 (sandbox) o tu número verificado'),
('STRIPE_SECRET_KEY',    'Stripe Secret Key',      'Pagos con tarjeta · dashboard.stripe.com'),
('STRIPE_WEBHOOK_SECRET','Stripe Webhook Secret',  'Para validar webhooks de Stripe'),
('STRIPE_PUBLISHABLE_KEY','Stripe Publishable Key','Clave pública para el frontend'),
('CONEKTA_SECRET_KEY',   'Conekta Secret Key',     'OXXO Pay · app.conekta.com'),
('CONEKTA_PUBLIC_KEY',   'Conekta Public Key',     'Clave pública Conekta para el frontend'),
('APP_NAME',             'Nombre de la App',       'Nombre que aparece en correos y WhatsApp'),
('APP_URL',              'URL de la App',          'URL base ej: https://tudu.app');


-- ── 8. Activar orgs existentes en trial de 30 días ───────────────────
UPDATE organizaciones
SET
    plan         = 'starter',
    plan_status  = 'trialing',
    trial_ends_at = DATE_ADD(NOW(), INTERVAL 30 DAY),
    members_limit = 3,
    projects_limit = 5,
    tasks_limit   = 100,
    storage_limit_mb = 500
WHERE plan_status IS NULL OR plan_status = '';

-- ── 9. Organización "TuDu Platform" para Super Admins ────────────────
-- (Las contraseñas de usuarios se crean vía setup_superadmins.php)
INSERT IGNORE INTO organizaciones
    (nombre, edition, plan, plan_status, trial_ends_at,
     members_limit, projects_limit, tasks_limit, storage_limit_mb, whatsapp_bot)
VALUES
    ('TuDu Platform', 'corp', 'enterprise', 'active', NULL,
     999999, 999999, 999999, 999999, 1);

-- NOTA: Para crear los usuarios Super Admin (Oscar y Eduardo)
-- con contraseñas bcrypt seguras, ejecuta:
-- http://localhost/tudu_haute_couture_tech/setup_superadmins.php

SET FOREIGN_KEY_CHECKS = 1;

-- ═══════════════════════════════════════════════════════════════════════
-- FIN DE MIGRACIÓN
-- Pasos finales:
--   1. Ejecutar esta migración en MySQL
--   2. Abrir setup_superadmins.php en el navegador (solo localhost)
--   3. Eliminar setup_superadmins.php del servidor de producción
-- Verifica con: SHOW COLUMNS FROM organizaciones;
--               SHOW TABLES LIKE '%subscri%';
-- ═══════════════════════════════════════════════════════════════════════
