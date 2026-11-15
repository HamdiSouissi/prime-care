-- Prime Care — Migration 011 : Documents médicaux, conservation RGPD, limiteur de débit API et index de performance (Phase 4)
-- Encodage : utf8mb4 / utf8mb4_unicode_ci, moteur InnoDB, dates en UTC.

CREATE TABLE IF NOT EXISTS `documents` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `prescription_id` INT UNSIGNED NULL DEFAULT NULL,
    `category` VARCHAR(60) NOT NULL DEFAULT 'autre',
    `title` VARCHAR(180) NOT NULL,
    `doc_date` DATE NOT NULL,
    `description` TEXT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(180) NULL,
    `mime` VARCHAR(80) NOT NULL,
    `size` INT UNSIGNED NOT NULL DEFAULT 0,
    `sha256` CHAR(64) NOT NULL,
    `uploaded_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_documents_patient_cat_date` (`patient_id`, `deleted_at`, `category`, `doc_date`),
    INDEX `idx_documents_patient_date` (`patient_id`, `deleted_at`, `doc_date`),
    INDEX `idx_documents_sha256` (`patient_id`, `sha256`),
    INDEX `idx_documents_prescription` (`prescription_id`),
    CONSTRAINT `fk_documents_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_documents_prescription`
        FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_documents_uploaded_by`
        FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_documents_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `audit_logs`
    ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `created_at`,
    ADD INDEX `idx_audit_filter` (`patient_id`, `deleted_at`, `created_at`),
    ADD INDEX `idx_audit_action` (`action`, `created_at`);

ALTER TABLE `login_attempts`
    ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `success`;

CREATE TABLE IF NOT EXISTS `api_rate_limits` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `window_minute` CHAR(16) NOT NULL,
    `hits` INT UNSIGNED NOT NULL DEFAULT 1,
    `updated_at` DATETIME NOT NULL,
    UNIQUE KEY `uniq_api_rate_user_min` (`user_id`, `window_minute`),
    INDEX `idx_api_rate_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index composites de performance pour les 10 requêtes les plus fréquentes (§6.5)
ALTER TABLE `measures`
    ADD INDEX `idx_measures_active_type_date` (`patient_id`, `deleted_at`, `measure_type_id`, `measured_at`);

ALTER TABLE `medication_intakes`
    ADD INDEX `idx_intakes_active_sched` (`patient_id`, `deleted_at`, `scheduled_at`, `status`);

ALTER TABLE `meals`
    ADD INDEX `idx_meals_active_date` (`patient_id`, `deleted_at`, `eaten_at`);

ALTER TABLE `notes`
    ADD INDEX `idx_notes_active_date` (`patient_id`, `deleted_at`, `created_at`);

ALTER TABLE `shifts`
    ADD INDEX `idx_shifts_active_window` (`patient_id`, `deleted_at`, `status`, `start_at`, `end_at`);

ALTER TABLE `attendances`
    ADD INDEX `idx_attendances_active_status_rec` (`patient_id`, `deleted_at`, `status`, `recorded_at`);

ALTER TABLE `alerts`
    ADD INDEX `idx_alerts_active_status_created` (`patient_id`, `deleted_at`, `status`, `level`, `created_at`);
