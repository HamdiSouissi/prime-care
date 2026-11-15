-- Prime Care — Migration 006 : Journal de bord, transmissions et soins rapides (Phase 2)
-- Encodage : utf8mb4 / utf8mb4_unicode_ci, moteur InnoDB, dates en UTC.

CREATE TABLE IF NOT EXISTS `notes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `category` ENUM('general', 'medical', 'behavior', 'hygiene', 'family', 'supply', 'handover') NOT NULL DEFAULT 'general',
    `priority` ENUM('normal', 'important', 'urgent') NOT NULL DEFAULT 'normal',
    `title` VARCHAR(160) NULL,
    `content` TEXT NOT NULL,
    `photo_path` VARCHAR(255) NULL,
    `photo_thumb_path` VARCHAR(255) NULL,
    `is_handover` TINYINT(1) NOT NULL DEFAULT 0,
    `handover_summary` JSON NULL,
    `recorded_by` INT UNSIGNED NOT NULL,
    `modified_reason` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_notes_patient_date` (`patient_id`, `created_at`),
    INDEX `idx_notes_handover` (`patient_id`, `is_handover`, `created_at`),
    INDEX `idx_notes_priority` (`patient_id`, `priority`, `created_at`),
    INDEX `idx_notes_deleted` (`deleted_at`),
    CONSTRAINT `fk_notes_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_notes_recorded_by`
        FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_notes_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `note_reads` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `note_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `read_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    UNIQUE KEY `uniq_note_reads_note_user` (`note_id`, `user_id`),
    INDEX `idx_note_reads_user` (`user_id`, `read_at`),
    CONSTRAINT `fk_note_reads_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_note_reads_note`
        FOREIGN KEY (`note_id`) REFERENCES `notes` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_note_reads_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `care_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `code` VARCHAR(60) NOT NULL,
    `label` VARCHAR(120) NOT NULL,
    `icon_name` VARCHAR(60) NOT NULL DEFAULT 'check-circle',
    `category` VARCHAR(60) NOT NULL DEFAULT 'hygiene',
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    UNIQUE KEY `uniq_care_items_patient_code` (`patient_id`, `code`),
    INDEX `idx_care_items_patient` (`patient_id`, `is_active`, `sort_order`),
    INDEX `idx_care_items_deleted` (`deleted_at`),
    CONSTRAINT `fk_care_items_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `care_checks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `care_item_id` INT UNSIGNED NOT NULL,
    `checked_at` DATETIME NOT NULL,
    `comment` VARCHAR(255) NULL,
    `performed_by` INT UNSIGNED NOT NULL,
    `modified_reason` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_care_checks_patient_date` (`patient_id`, `checked_at`),
    INDEX `idx_care_checks_item_date` (`care_item_id`, `checked_at`),
    INDEX `idx_care_checks_deleted` (`deleted_at`),
    CONSTRAINT `fk_care_checks_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_care_checks_item`
        FOREIGN KEY (`care_item_id`) REFERENCES `care_items` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_care_checks_performed_by`
        FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
