-- Prime Care — Migration 008 : Pointage des aides-soignantes & jetons QR code du domicile
-- Encodage : utf8mb4 / utf8mb4_unicode_ci, moteur InnoDB, dates en UTC.

ALTER TABLE `users`
    ADD COLUMN `attendance_gdpr_accepted_at` DATETIME NULL DEFAULT NULL AFTER `last_login_at`;

CREATE TABLE IF NOT EXISTS `attendances` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `user_id` INT UNSIGNED NOT NULL,
    `shift_id` INT UNSIGNED NULL,
    `type` ENUM('in', 'out') NOT NULL,
    `recorded_at` DATETIME NOT NULL,
    `declared_at` DATETIME NULL,
    `method` ENUM('slide', 'qr', 'declared') NOT NULL DEFAULT 'slide',
    `latitude` DECIMAL(10,7) NULL,
    `longitude` DECIMAL(10,7) NULL,
    `accuracy_m` DECIMAL(8,2) NULL,
    `distance_m` INT UNSIGNED NULL,
    `ip` VARCHAR(45) NULL,
    `status` ENUM('valid', 'pending', 'rejected') NOT NULL DEFAULT 'valid',
    `validated_by` INT UNSIGNED NULL,
    `validated_at` DATETIME NULL,
    `reason` VARCHAR(255) NULL,
    `rejection_reason` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_attendances_user_recorded` (`user_id`, `recorded_at`),
    INDEX `idx_attendances_shift` (`shift_id`, `type`),
    INDEX `idx_attendances_status` (`status`, `recorded_at`),
    INDEX `idx_attendances_patient_recorded` (`patient_id`, `recorded_at`),
    INDEX `idx_attendances_deleted` (`deleted_at`),
    CONSTRAINT `fk_attendances_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_attendances_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_attendances_shift`
        FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_attendances_validated_by`
        FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_attendances_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance_qr_tokens` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `token_id` VARCHAR(36) NOT NULL UNIQUE,
    `secret_hash` CHAR(64) NOT NULL,
    `qr_payload` VARCHAR(255) NOT NULL,
    `label` VARCHAR(120) NOT NULL DEFAULT 'Domicile M. Henri Benali',
    `revoked_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_attendance_qr_patient_active` (`patient_id`, `revoked_at`),
    CONSTRAINT `fk_attendance_qr_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_attendance_qr_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
