-- ============================================================
-- database/schema.sql
-- Slate booking system — full MySQL schema.
-- Target: MySQL 8.0+ / MariaDB 10.5+
-- Charset: utf8mb4 / utf8mb4_unicode_ci
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- users
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`            INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(120)        NOT NULL,
    `email`         VARCHAR(190)        NOT NULL,
    `password_hash` VARCHAR(255)        NOT NULL,
    `role`          ENUM('admin','customer') NOT NULL DEFAULT 'customer',
    `is_super`      TINYINT(1)          NOT NULL DEFAULT 0,
    `created_at`    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- services
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
    `id`               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(150)   NOT NULL,
    `description`      TEXT           NULL,
    `duration_minutes` SMALLINT UNSIGNED NOT NULL,
    `price`            DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    `color`            CHAR(7)        NOT NULL DEFAULT '#3b82f6', -- hex incl '#'
    `is_active`        TINYINT(1)     NOT NULL DEFAULT 1,
    `created_at`       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_services_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- business_hours (weekly recurring availability)
-- day_of_week: 0 = Sunday … 6 = Saturday  (matches DATE_FORMAT %w)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `business_hours`;
CREATE TABLE `business_hours` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `day_of_week` TINYINT UNSIGNED NOT NULL,
    `open_time`   TIME         NULL,
    `close_time`  TIME         NULL,
    `is_closed`   TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_business_hours_dow` (`day_of_week`),
    CONSTRAINT `chk_business_hours_dow` CHECK (`day_of_week` BETWEEN 0 AND 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- blocked_dates (admin closes individual days)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `blocked_dates`;
CREATE TABLE `blocked_dates` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `date`       DATE         NOT NULL,
    `reason`     VARCHAR(255) NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_blocked_dates_date` (`date`),
    KEY `idx_blocked_dates_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- appointments
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `appointments`;
CREATE TABLE `appointments` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED NULL,
    `service_id`     INT UNSIGNED NOT NULL,
    `customer_name`  VARCHAR(150) NOT NULL,
    `customer_email` VARCHAR(190) NOT NULL,
    `customer_phone` VARCHAR(40)  NULL,
    `starts_at`      DATETIME     NOT NULL,
    `ends_at`        DATETIME     NOT NULL,
    `status`         ENUM('pending','confirmed','cancelled','completed','no_show')
                                  NOT NULL DEFAULT 'pending',
    `notes`          TEXT         NULL,
    `cancel_token`   VARCHAR(64)  NOT NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_appointments_cancel_token` (`cancel_token`),
    KEY `idx_appointments_starts_at` (`starts_at`),
    KEY `idx_appointments_status`    (`status`),
    KEY `idx_appointments_service`   (`service_id`),
    KEY `idx_appointments_user`      (`user_id`),
    CONSTRAINT `fk_appointments_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_appointments_service`
        FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- settings  (key/value store)
-- Note: `key` is reserved in MySQL — always backtick.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`   VARCHAR(100) NOT NULL,
    `value` TEXT         NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_settings_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- email_log
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `email_log`;
CREATE TABLE `email_log` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `appointment_id` INT UNSIGNED NULL,
    `type`           VARCHAR(50)  NOT NULL,   -- confirmation | reminder | cancellation | admin_notice …
    `sent_to`        VARCHAR(190) NOT NULL,
    `sent_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_email_log_appt` (`appointment_id`),
    KEY `idx_email_log_type` (`type`),
    CONSTRAINT `fk_email_log_appointment`
        FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- api_tokens
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `api_tokens`;
CREATE TABLE `api_tokens` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NOT NULL,
    `name`         VARCHAR(120) NOT NULL,
    `token_prefix` VARCHAR(16)  NOT NULL,
    `token_hash`   CHAR(64)     NOT NULL,
    `last_used_at` DATETIME     NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_api_tokens_hash` (`token_hash`),
    KEY `idx_api_tokens_user` (`user_id`),
    CONSTRAINT `fk_api_tokens_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- webhooks
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `webhooks`;
CREATE TABLE `webhooks` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `url`               VARCHAR(500) NOT NULL,
    `secret`            VARCHAR(64)  NOT NULL,
    `events`            TEXT         NOT NULL,
    `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
    `last_triggered_at` DATETIME     NULL,
    `failure_count`     INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_webhooks_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- webhook_queue — delivery log + retry state
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `webhook_queue`;
CREATE TABLE `webhook_queue` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `webhook_id`      INT UNSIGNED NOT NULL,
    `event`           VARCHAR(60)  NOT NULL,
    `payload`         MEDIUMTEXT   NOT NULL,
    `attempt_count`   INT UNSIGNED NOT NULL DEFAULT 0,
    `next_attempt_at` DATETIME     NOT NULL,
    `last_error`      TEXT         NULL,
    `delivered_at`    DATETIME     NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_queue_pending` (`delivered_at`, `next_attempt_at`),
    KEY `idx_queue_webhook` (`webhook_id`),
    CONSTRAINT `fk_queue_webhook`
        FOREIGN KEY (`webhook_id`) REFERENCES `webhooks` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
