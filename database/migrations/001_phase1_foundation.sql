-- Prime Care — Migration 001 : Fondations Phase 1
-- Encodage : utf8mb4 / utf8mb4_unicode_ci, moteur InnoDB, dates en UTC.

CREATE TABLE IF NOT EXISTS `migrations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `migration` VARCHAR(190) NOT NULL UNIQUE,
    `executed_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `first_name` VARCHAR(80) NOT NULL,
    `last_name` VARCHAR(80) NOT NULL,
    `email` VARCHAR(180) NOT NULL UNIQUE,
    `phone` VARCHAR(32) NULL,
    `role` ENUM('admin', 'coordinator', 'caregiver', 'viewer') NOT NULL DEFAULT 'caregiver',
    `password_hash` VARCHAR(255) NOT NULL,
    `pin_hash` VARCHAR(255) NULL,
    `avatar_path` VARCHAR(255) NULL,
    `color_hex` VARCHAR(16) NOT NULL DEFAULT '#5E5CE6',
    `contract_type` VARCHAR(50) NULL DEFAULT 'CDI',
    `weekly_hours` DECIMAL(5,2) NULL DEFAULT 35.00,
    `hourly_rate` DECIMAL(6,2) NULL DEFAULT NULL,
    `hired_at` DATE NULL,
    `emergency_contact_name` VARCHAR(120) NULL,
    `emergency_contact_phone` VARCHAR(32) NULL,
    `emergency_contact_relation` VARCHAR(80) NULL,
    `notes` TEXT NULL,
    `theme` ENUM('auto', 'light', 'dark') NOT NULL DEFAULT 'auto',
    `locale` VARCHAR(10) NOT NULL DEFAULT 'fr',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_users_role_active` (`role`, `is_active`),
    INDEX `idx_users_patient` (`patient_id`),
    INDEX `idx_users_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `selector` VARCHAR(64) NOT NULL UNIQUE,
    `validator_hash` CHAR(64) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `ip_address` VARCHAR(45) NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_remember_user` (`user_id`),
    INDEX `idx_remember_expires` (`expires_at`),
    CONSTRAINT `fk_remember_tokens_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(180) NOT NULL,
    `token_hash` CHAR(64) NOT NULL UNIQUE,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_password_resets_email` (`email`),
    INDEX `idx_password_resets_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `identifier` VARCHAR(180) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempted_at` DATETIME NOT NULL,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    INDEX `idx_login_attempts_identifier_time` (`identifier`, `attempted_at`),
    INDEX `idx_login_attempts_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patients` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(80) NOT NULL,
    `last_name` VARCHAR(80) NOT NULL,
    `birth_date` DATE NOT NULL,
    `gender` ENUM('M', 'F', 'O') NOT NULL DEFAULT 'M',
    `photo_path` VARCHAR(255) NULL,
    `address` VARCHAR(255) NULL,
    `access_code` VARCHAR(120) NULL,
    `ssn_masked` VARCHAR(40) NULL,
    `diabetes_type` VARCHAR(80) NOT NULL DEFAULT 'Diabète de type 2',
    `blood_group` VARCHAR(16) NULL,
    `reference_weight_kg` DECIMAL(5,2) NULL,
    `height_cm` SMALLINT UNSIGNED NULL,
    `target_glycemia_fasting_min` DECIMAL(4,2) NOT NULL DEFAULT 0.80,
    `target_glycemia_fasting_max` DECIMAL(4,2) NOT NULL DEFAULT 1.30,
    `target_glycemia_postmeal_max` DECIMAL(4,2) NOT NULL DEFAULT 1.80,
    `hypo_threshold_g_l` DECIMAL(4,2) NOT NULL DEFAULT 0.70,
    `hyper_threshold_g_l` DECIMAL(4,2) NOT NULL DEFAULT 2.50,
    `allergies` TEXT NULL,
    `medical_history` TEXT NULL,
    `permanent_instructions` TEXT NULL,
    `hypo_protocol` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_patients_deleted` (`deleted_at`),
    CONSTRAINT `fk_patients_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patient_contacts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL,
    `category` ENUM('doctor', 'specialist', 'pharmacy', 'family', 'nurse', 'other') NOT NULL DEFAULT 'other',
    `full_name` VARCHAR(140) NOT NULL,
    `relation_role` VARCHAR(120) NOT NULL,
    `organization` VARCHAR(140) NULL,
    `phone` VARCHAR(32) NOT NULL,
    `email` VARCHAR(180) NULL,
    `address` VARCHAR(255) NULL,
    `notes` VARCHAR(255) NULL,
    `is_emergency_priority` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_patient_contacts_patient` (`patient_id`, `sort_order`),
    INDEX `idx_patient_contacts_deleted` (`deleted_at`),
    CONSTRAINT `fk_patient_contacts_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_patient_contacts_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `setting_key` VARCHAR(120) NOT NULL UNIQUE,
    `setting_value` JSON NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_settings_patient` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `patient_id` INT UNSIGNED NULL DEFAULT 1,
    `action` VARCHAR(80) NOT NULL,
    `entity_type` VARCHAR(80) NOT NULL,
    `entity_id` INT UNSIGNED NULL,
    `before_data` JSON NULL,
    `after_data` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_audit_user` (`user_id`),
    INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
    INDEX `idx_audit_patient_date` (`patient_id`, `created_at`),
    CONSTRAINT `fk_audit_logs_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
