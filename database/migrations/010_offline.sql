-- Prime Care — Migration 010 : Identifiant client idempotent (client_uuid) pour le mode hors ligne
-- Encodage : utf8mb4 / utf8mb4_unicode_ci, moteur InnoDB.

ALTER TABLE `measures`
    ADD COLUMN `client_uuid` CHAR(36) NULL UNIQUE AFTER `patient_id`;

ALTER TABLE `medication_intakes`
    ADD COLUMN `client_uuid` CHAR(36) NULL UNIQUE AFTER `patient_id`;

ALTER TABLE `meals`
    ADD COLUMN `client_uuid` CHAR(36) NULL UNIQUE AFTER `patient_id`;

ALTER TABLE `notes`
    ADD COLUMN `client_uuid` CHAR(36) NULL UNIQUE AFTER `patient_id`;

ALTER TABLE `care_checks`
    ADD COLUMN `client_uuid` CHAR(36) NULL UNIQUE AFTER `patient_id`;
