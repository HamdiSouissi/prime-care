-- Prime Care — Migration 007 : Planning d'équipe (modèles de créneaux, créneaux, absences et GPS domicile)
-- Encodage : utf8mb4 / utf8mb4_unicode_ci, moteur InnoDB, dates en UTC.

ALTER TABLE `patients`
    ADD COLUMN `home_latitude` DECIMAL(10,7) NULL DEFAULT 48.7903600 AFTER `access_code`,
    ADD COLUMN `home_longitude` DECIMAL(10,7) NULL DEFAULT 2.4555700 AFTER `home_latitude`,
    ADD COLUMN `home_radius_m` SMALLINT UNSIGNED NOT NULL DEFAULT 150 AFTER `home_longitude`;

CREATE TABLE IF NOT EXISTS `shift_templates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `code` VARCHAR(50) NOT NULL,
    `label` VARCHAR(120) NOT NULL,
    `start_time` CHAR(5) NOT NULL,
    `end_time` CHAR(5) NOT NULL,
    `spans_next_day` TINYINT(1) NOT NULL DEFAULT 0,
    `duration_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 720,
    `color_class` VARCHAR(32) NOT NULL DEFAULT 'day',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    UNIQUE KEY `uniq_shift_template_code` (`patient_id`, `code`),
    INDEX `idx_shift_templates_active` (`patient_id`, `is_active`, `sort_order`),
    CONSTRAINT `fk_shift_templates_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_shift_templates_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shifts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `template_id` INT UNSIGNED NULL,
    `user_id` INT UNSIGNED NULL,
    `replaced_user_id` INT UNSIGNED NULL,
    `start_at` DATETIME NOT NULL,
    `end_at` DATETIME NOT NULL,
    `status` ENUM('planned', 'confirmed', 'cancelled') NOT NULL DEFAULT 'planned',
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_shifts_start_end` (`start_at`, `end_at`),
    INDEX `idx_shifts_user_start` (`user_id`, `start_at`),
    INDEX `idx_shifts_patient_start` (`patient_id`, `start_at`),
    INDEX `idx_shifts_deleted` (`deleted_at`),
    CONSTRAINT `fk_shifts_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_shifts_template`
        FOREIGN KEY (`template_id`) REFERENCES `shift_templates` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_shifts_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_shifts_replaced_user`
        FOREIGN KEY (`replaced_user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_shifts_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `absences` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `user_id` INT UNSIGNED NOT NULL,
    `absence_type` ENUM('conge', 'maladie', 'autre') NOT NULL DEFAULT 'conge',
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `comment` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_absences_user_dates` (`user_id`, `start_date`, `end_date`),
    INDEX `idx_absences_patient_dates` (`patient_id`, `start_date`, `end_date`),
    INDEX `idx_absences_deleted` (`deleted_at`),
    CONSTRAINT `fk_absences_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_absences_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_absences_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
