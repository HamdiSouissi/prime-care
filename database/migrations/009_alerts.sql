-- Prime Care — Migration 009 : Alertes, préférences de notification, Web Push et file d'envoi
-- Encodage : utf8mb4 / utf8mb4_unicode_ci, moteur InnoDB, dates en UTC.

CREATE TABLE IF NOT EXISTS `alerts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `type` VARCHAR(60) NOT NULL,
    `level` ENUM('info', 'warning', 'critical') NOT NULL DEFAULT 'warning',
    `source_type` VARCHAR(60) NULL,
    `source_id` INT UNSIGNED NULL,
    `dedupe_key` VARCHAR(160) NOT NULL,
    `open_dedupe_key` VARCHAR(160) NULL UNIQUE,
    `title` VARCHAR(180) NOT NULL,
    `message` TEXT NOT NULL,
    `target_url` VARCHAR(255) NULL,
    `status` ENUM('new', 'seen', 'handled') NOT NULL DEFAULT 'new',
    `escalated_at` DATETIME NULL,
    `seen_at` DATETIME NULL,
    `seen_by` INT UNSIGNED NULL,
    `handled_by` INT UNSIGNED NULL,
    `handled_at` DATETIME NULL,
    `resolution` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_alerts_status_level` (`status`, `level`, `created_at`),
    INDEX `idx_alerts_dedupe` (`dedupe_key`, `status`),
    INDEX `idx_alerts_source` (`source_type`, `source_id`),
    INDEX `idx_alerts_patient` (`patient_id`, `created_at`),
    INDEX `idx_alerts_deleted` (`deleted_at`),
    CONSTRAINT `fk_alerts_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_alerts_seen_by`
        FOREIGN KEY (`seen_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_alerts_handled_by`
        FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_alerts_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL UNIQUE,
    `push_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `email_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `sms_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `quiet_hours_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `quiet_start` CHAR(5) NOT NULL DEFAULT '22:00',
    `quiet_end` CHAR(5) NOT NULL DEFAULT '07:00',
    `preferences_json` JSON NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    CONSTRAINT `fk_notification_settings_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `push_subscriptions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `endpoint` VARCHAR(768) NOT NULL,
    `endpoint_hash` CHAR(64) NOT NULL UNIQUE,
    `p256dh` VARCHAR(255) NOT NULL,
    `auth` VARCHAR(120) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `last_seen_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_push_subs_user` (`user_id`, `deleted_at`),
    CONSTRAINT `fk_push_subscriptions_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_queue` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `alert_id` INT UNSIGNED NULL,
    `user_id` INT UNSIGNED NULL,
    `channel` ENUM('push', 'email', 'sms') NOT NULL,
    `recipient` VARCHAR(512) NOT NULL,
    `subject` VARCHAR(200) NULL,
    `content` JSON NOT NULL,
    `status` ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `next_try_at` DATETIME NOT NULL,
    `sent_at` DATETIME NULL,
    `last_error` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_notif_queue_due` (`status`, `next_try_at`),
    INDEX `idx_notif_queue_alert` (`alert_id`),
    INDEX `idx_notif_queue_user` (`user_id`),
    CONSTRAINT `fk_notification_queue_alert`
        FOREIGN KEY (`alert_id`) REFERENCES `alerts` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_notification_queue_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
