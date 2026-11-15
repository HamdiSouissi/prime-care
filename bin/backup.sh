#!/usr/bin/env bash
# ==============================================================================
# Prime Care v1.0.0 — Script de sauvegarde chiffrée (Phase 4 §6.2)
# - Dump MySQL transactionnel (--single-transaction)
# - Archive des fichiers téléversés (storage/uploads)
# - Chiffrement OpenSSL AES-256-CBC (-pbkdf2) avec clé lue dans PRIME_CARE_BACKUP_KEY
# - Rotation automatique à 30 jours
# ==============================================================================
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${PRIME_CARE_BACKUP_DIR:-${ROOT_DIR}/storage/backups}"
DB_HOST="${PRIME_CARE_DB_HOST:-127.0.0.1}"
DB_PORT="${PRIME_CARE_DB_PORT:-3306}"
DB_NAME="${PRIME_CARE_DB_NAME:-prime_care}"
DB_USER="${PRIME_CARE_DB_USER:-root}"
DB_PASS="${PRIME_CARE_DB_PASS:-}"
RETENTION_DAYS="${PRIME_CARE_BACKUP_RETENTION_DAYS:-30}"

if [[ -z "${PRIME_CARE_BACKUP_KEY:-}" ]]; then
  echo "[ERREUR] La variable d'environnement PRIME_CARE_BACKUP_KEY est obligatoire pour chiffrer la sauvegarde." >&2
  exit 1
fi

mkdir -p "${BACKUP_DIR}"
mkdir -p "${ROOT_DIR}/storage/uploads"

TIMESTAMP="$(date -u +%Y%m%d_%H%M%S)"
WORK_DIR="$(mktemp -d)"
trap 'rm -rf "${WORK_DIR}"' EXIT

SQL_DUMP="${WORK_DIR}/database.sql"
UPLOADS_ARCHIVE="${WORK_DIR}/uploads.tar.gz"
BUNDLE_TAR="${WORK_DIR}/bundle.tar.gz"
FINAL_ENC="${BACKUP_DIR}/prime_care_backup_${TIMESTAMP}.tar.gz.enc"

echo "[1/4] Dump transactionnel MySQL de la base '${DB_NAME}'..."
MYSQL_PWD_ARGS=()
if [[ -n "${DB_PASS}" ]]; then
  export MYSQL_PWD="${DB_PASS}"
fi

mysqldump \
  --host="${DB_HOST}" \
  --port="${DB_PORT}" \
  --user="${DB_USER}" \
  --single-transaction \
  --routines \
  --triggers \
  --hex-blob \
  --default-character-set=utf8mb4 \
  "${DB_NAME}" > "${SQL_DUMP}"

echo "[2/4] Archivage du répertoire storage/uploads/..."
tar -czf "${UPLOADS_ARCHIVE}" -C "${ROOT_DIR}/storage" uploads

echo "[3/4] Assemblage et chiffrement AES-256-CBC (PBKDF2)..."
tar -czf "${BUNDLE_TAR}" -C "${WORK_DIR}" database.sql uploads.tar.gz
openssl enc -aes-256-cbc -salt -pbkdf2 -iter 100000 \
  -in "${BUNDLE_TAR}" \
  -out "${FINAL_ENC}" \
  -pass env:PRIME_CARE_BACKUP_KEY

chmod 600 "${FINAL_ENC}"
SHA256_SUM="$(sha256sum "${FINAL_ENC}" | awk '{print $1}')"

echo "[4/4] Rotation des sauvegardes de plus de ${RETENTION_DAYS} jours..."
find "${BACKUP_DIR}" -maxdepth 1 -type f -name "prime_care_backup_*.tar.gz.enc" -mtime +"${RETENTION_DAYS}" -delete

echo "[OK] Sauvegarde créée : ${FINAL_ENC}"
echo "[OK] SHA-256 : ${SHA256_SUM}"
