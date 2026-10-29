-- Migration: recurring appointment series.
-- Run once against an existing database.

CREATE TABLE IF NOT EXISTS `appointment_series` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `service_id`       INT UNSIGNED NOT NULL,
    `customer_name`    VARCHAR(150) NOT NULL,
    `customer_email`   VARCHAR(190) NOT NULL,
    `customer_phone`   VARCHAR(40)  NULL,
    `notes`            TEXT         NULL,
    `freq`             ENUM('weekly','biweekly','monthly') NOT NULL,
    `byweekday`        TINYINT UNSIGNED NOT NULL,       -- 0=Sun...6=Sat, informational
    `start_time`       TIME         NOT NULL,
    `duration_minutes` SMALLINT UNSIGNED NOT NULL,       -- snapshot at booking time
    `starts_on`        DATE         NOT NULL,            -- date of the first instance
    `ends_on`          DATE         NULL,                -- NULL = no end date
    `status`           ENUM('active','cancelled') NOT NULL DEFAULT 'active',
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_appointment_series_status` (`status`),
    CONSTRAINT `fk_appointment_series_service`
        FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `appointments`
    ADD COLUMN `series_id` INT UNSIGNED NULL AFTER `service_id`,
    ADD KEY `idx_appointments_series` (`series_id`),
    ADD CONSTRAINT `fk_appointments_series`
        FOREIGN KEY (`series_id`) REFERENCES `appointment_series` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;
