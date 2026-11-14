-- =============================================================================
-- Prime Care — Migration 002 : Appareils de confiance pour la connexion par PIN
-- =============================================================================

CREATE TABLE IF NOT EXISTS `trusted_devices` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `selector` VARCHAR(32) NOT NULL,
    `validator_hash` CHAR(64) NOT NULL,
    `device_label` VARCHAR(120) NOT NULL DEFAULT 'Appareil mobile',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_used_at` DATETIME NULL DEFAULT NULL,
    `expires_at` DATETIME NOT NULL,
    `revoked_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_trusted_devices_selector` (`selector`),
    KEY `idx_trusted_devices_user` (`user_id`, `revoked_at`, `expires_at`),
    CONSTRAINT `fk_trusted_devices_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
