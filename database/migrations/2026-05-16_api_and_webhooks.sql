-- Migration: API tokens + webhook subscriptions + webhook delivery queue.
-- Run once against an existing database.

CREATE TABLE IF NOT EXISTS `api_tokens` (
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

CREATE TABLE IF NOT EXISTS `webhooks` (
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

CREATE TABLE IF NOT EXISTS `webhook_queue` (
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
