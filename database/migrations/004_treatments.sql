-- Prime Care — Migration 004 : Médicaments, prescriptions et prises (Phase 2)
-- Encodage : utf8mb4 / utf8mb4_unicode_ci, moteur InnoDB, dates en UTC.

CREATE TABLE IF NOT EXISTS `medications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `name` VARCHAR(140) NOT NULL,
    `dci` VARCHAR(140) NULL,
    `form` VARCHAR(60) NOT NULL DEFAULT 'comprimé',
    `default_dosage` DECIMAL(8,2) NULL,
    `default_unit` VARCHAR(32) NOT NULL DEFAULT 'mg',
    `box_photo_path` VARCHAR(255) NULL,
    `box_photo_thumb_path` VARCHAR(255) NULL,
    `instructions` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_medications_patient_active` (`patient_id`, `is_active`),
    INDEX `idx_medications_deleted` (`deleted_at`),
    CONSTRAINT `fk_medications_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_medications_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prescriptions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `medication_id` INT UNSIGNED NOT NULL,
    `dose` DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    `unit` VARCHAR(32) NOT NULL DEFAULT 'mg',
    `start_date` DATE NOT NULL,
    `end_date` DATE NULL,
    `ends_on_rx` TINYINT(1) NOT NULL DEFAULT 0,
    `days_mask` VARCHAR(32) NOT NULL DEFAULT '1,2,3,4,5,6,7',
    `as_needed` TINYINT(1) NOT NULL DEFAULT 0,
    `min_interval_h` TINYINT UNSIGNED NULL,
    `max_per_day` TINYINT UNSIGNED NULL,
    `insulin_kind` ENUM('rapide', 'lente') NULL DEFAULT NULL,
    `dose_mode` ENUM('fixe', 'tableau') NOT NULL DEFAULT 'fixe',
    `correction_table` JSON NULL,
    `instructions` TEXT NULL,
    `document_path` VARCHAR(255) NULL,
    `document_mime` VARCHAR(80) NULL,
    `document_original_name` VARCHAR(180) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_prescriptions_patient_active` (`patient_id`, `is_active`),
    INDEX `idx_prescriptions_medication` (`medication_id`),
    INDEX `idx_prescriptions_deleted` (`deleted_at`),
    CONSTRAINT `fk_prescriptions_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_prescriptions_medication`
        FOREIGN KEY (`medication_id`) REFERENCES `medications` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_prescriptions_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prescription_times` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `prescription_id` INT UNSIGNED NOT NULL,
    `scheduled_time` CHAR(5) NOT NULL,
    `moment_label` ENUM('matin', 'midi', 'soir', 'coucher') NOT NULL DEFAULT 'matin',
    `dose_override` DECIMAL(8,2) NULL,
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_prescription_times_rx` (`prescription_id`, `sort_order`),
    INDEX `idx_prescription_times_deleted` (`deleted_at`),
    CONSTRAINT `fk_prescription_times_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_prescription_times_rx`
        FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `medication_intakes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `prescription_id` INT UNSIGNED NOT NULL,
    `prescription_time_id` INT UNSIGNED NULL,
    `scheduled_at` DATETIME NOT NULL,
    `status` ENUM('pending', 'given', 'skipped') NOT NULL DEFAULT 'pending',
    `given_at` DATETIME NULL,
    `given_dose` DECIMAL(8,2) NULL,
    `injection_site` VARCHAR(60) NULL,
    `reason` VARCHAR(180) NULL,
    `comment` TEXT NULL,
    `related_measure_id` INT UNSIGNED NULL,
    `given_by` INT UNSIGNED NULL,
    `modified_reason` VARCHAR(255) NULL,
    `version` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    UNIQUE KEY `uniq_prescription_time_scheduled` (`prescription_time_id`, `scheduled_at`),
    INDEX `idx_intakes_patient_scheduled` (`patient_id`, `scheduled_at`),
    INDEX `idx_intakes_patient_status` (`patient_id`, `status`, `scheduled_at`),
    INDEX `idx_intakes_rx_given` (`prescription_id`, `given_at`),
    INDEX `idx_intakes_deleted` (`deleted_at`),
    CONSTRAINT `fk_intakes_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_intakes_prescription`
        FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_intakes_prescription_time`
        FOREIGN KEY (`prescription_time_id`) REFERENCES `prescription_times` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_intakes_related_measure`
        FOREIGN KEY (`related_measure_id`) REFERENCES `measures` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_intakes_given_by`
        FOREIGN KEY (`given_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_intakes_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
