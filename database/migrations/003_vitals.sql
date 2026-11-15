-- Prime Care — Migration 003 : Mesures médicales, seuils et occurrences attendues (Phase 2)
-- Encodage : utf8mb4 / utf8mb4_unicode_ci, moteur InnoDB, dates en UTC.

CREATE TABLE IF NOT EXISTS `measure_types` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `code` VARCHAR(60) NOT NULL,
    `label` VARCHAR(120) NOT NULL,
    `unit` VARCHAR(32) NOT NULL,
    `secondary_label` VARCHAR(80) NULL,
    `secondary_unit` VARCHAR(32) NULL,
    `tertiary_label` VARCHAR(80) NULL,
    `tertiary_unit` VARCHAR(32) NULL,
    `decimals` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `min_value` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `max_value` DECIMAL(8,2) NOT NULL DEFAULT 999.00,
    `crit_low` DECIMAL(8,2) NULL,
    `low` DECIMAL(8,2) NULL,
    `high` DECIMAL(8,2) NULL,
    `crit_high` DECIMAL(8,2) NULL,
    `secondary_crit_low` DECIMAL(8,2) NULL,
    `secondary_low` DECIMAL(8,2) NULL,
    `secondary_high` DECIMAL(8,2) NULL,
    `secondary_crit_high` DECIMAL(8,2) NULL,
    `icon_name` VARCHAR(60) NOT NULL DEFAULT 'activity',
    `is_secondary` TINYINT(1) NOT NULL DEFAULT 0,
    `is_custom` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    UNIQUE KEY `uniq_measure_types_patient_code` (`patient_id`, `code`),
    INDEX `idx_measure_types_patient_order` (`patient_id`, `is_active`, `sort_order`),
    INDEX `idx_measure_types_deleted` (`deleted_at`),
    CONSTRAINT `fk_measure_types_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_measure_types_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `measures` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `measure_type_id` INT UNSIGNED NOT NULL,
    `value` DECIMAL(8,2) NOT NULL,
    `value2` DECIMAL(8,2) NULL,
    `value3` DECIMAL(8,2) NULL,
    `unit_recorded` VARCHAR(32) NULL,
    `context` VARCHAR(40) NOT NULL DEFAULT 'routine',
    `measured_at` DATETIME NOT NULL,
    `comment` TEXT NULL,
    `photo_path` VARCHAR(255) NULL,
    `photo_thumb_path` VARCHAR(255) NULL,
    `alert_level` ENUM('ok', 'warn', 'critical') NOT NULL DEFAULT 'ok',
    `threshold_level` ENUM('crit_low', 'low', 'ok', 'high', 'crit_high') NOT NULL DEFAULT 'ok',
    `out_of_bounds_confirmed` TINYINT(1) NOT NULL DEFAULT 0,
    `recorded_by` INT UNSIGNED NOT NULL,
    `modified_reason` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_measures_patient_type_date` (`patient_id`, `measure_type_id`, `measured_at`),
    INDEX `idx_measures_patient_date` (`patient_id`, `measured_at`),
    INDEX `idx_measures_alert_date` (`patient_id`, `alert_level`, `measured_at`),
    INDEX `idx_measures_deleted` (`deleted_at`),
    CONSTRAINT `fk_measures_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_measures_type`
        FOREIGN KEY (`measure_type_id`) REFERENCES `measure_types` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_measures_recorded_by`
        FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_measures_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `measure_schedules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `measure_type_id` INT UNSIGNED NOT NULL,
    `scheduled_time` CHAR(5) NOT NULL,
    `days_mask` VARCHAR(32) NOT NULL DEFAULT '1,2,3,4,5,6,7',
    `context` VARCHAR(40) NOT NULL DEFAULT 'routine',
    `label` VARCHAR(120) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_measure_schedules_patient` (`patient_id`, `is_active`),
    INDEX `idx_measure_schedules_deleted` (`deleted_at`),
    CONSTRAINT `fk_measure_schedules_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_measure_schedules_type`
        FOREIGN KEY (`measure_type_id`) REFERENCES `measure_types` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `measure_expectations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `measure_schedule_id` INT UNSIGNED NOT NULL,
    `measure_type_id` INT UNSIGNED NOT NULL,
    `scheduled_at` DATETIME NOT NULL,
    `status` ENUM('upcoming', 'done', 'late') NOT NULL DEFAULT 'upcoming',
    `measure_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    UNIQUE KEY `uniq_measure_schedule_occurrence` (`measure_schedule_id`, `scheduled_at`),
    INDEX `idx_measure_expectations_patient_date` (`patient_id`, `scheduled_at`),
    INDEX `idx_measure_expectations_status` (`patient_id`, `status`, `scheduled_at`),
    INDEX `idx_measure_expectations_deleted` (`deleted_at`),
    CONSTRAINT `fk_measure_expectations_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_measure_expectations_schedule`
        FOREIGN KEY (`measure_schedule_id`) REFERENCES `measure_schedules` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_measure_expectations_type`
        FOREIGN KEY (`measure_type_id`) REFERENCES `measure_types` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_measure_expectations_measure`
        FOREIGN KEY (`measure_id`) REFERENCES `measures` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
