-- Prime Care — Migration 005 : Repas, photos avant/après et favoris alimentaires (Phase 2)
-- Encodage : utf8mb4 / utf8mb4_unicode_ci, moteur InnoDB, dates en UTC.

CREATE TABLE IF NOT EXISTS `meals` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `meal_type` ENUM('breakfast', 'lunch', 'snack', 'dinner', 'night_snack') NOT NULL DEFAULT 'lunch',
    `eaten_at` DATETIME NOT NULL,
    `description` TEXT NOT NULL,
    `portion_eaten` TINYINT UNSIGNED NOT NULL DEFAULT 100,
    `carbs_g` SMALLINT UNSIGNED NULL,
    `drink_type` VARCHAR(120) NULL,
    `drink_ml` SMALLINT UNSIGNED NULL,
    `comment` TEXT NULL,
    `recorded_by` INT UNSIGNED NOT NULL,
    `modified_reason` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_meals_patient_date` (`patient_id`, `eaten_at`),
    INDEX `idx_meals_patient_type` (`patient_id`, `meal_type`, `eaten_at`),
    INDEX `idx_meals_deleted` (`deleted_at`),
    CONSTRAINT `fk_meals_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_meals_recorded_by`
        FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_meals_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `meal_photos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `meal_id` INT UNSIGNED NOT NULL,
    `kind` ENUM('avant', 'apres', 'autre') NOT NULL DEFAULT 'avant',
    `photo_path` VARCHAR(255) NOT NULL,
    `thumb_path` VARCHAR(255) NOT NULL,
    `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_meal_photos_meal` (`meal_id`, `sort_order`),
    INDEX `idx_meal_photos_deleted` (`deleted_at`),
    CONSTRAINT `fk_meal_photos_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_meal_photos_meal`
        FOREIGN KEY (`meal_id`) REFERENCES `meals` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_meal_photos_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `food_favorites` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `label` VARCHAR(160) NOT NULL,
    `meal_type` ENUM('breakfast', 'lunch', 'snack', 'dinner', 'night_snack', 'any') NOT NULL DEFAULT 'any',
    `default_carbs_g` SMALLINT UNSIGNED NULL,
    `default_drink` VARCHAR(120) NULL,
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    INDEX `idx_food_favorites_patient` (`patient_id`, `is_active`, `sort_order`),
    INDEX `idx_food_favorites_deleted` (`deleted_at`),
    CONSTRAINT `fk_food_favorites_patient`
        FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
