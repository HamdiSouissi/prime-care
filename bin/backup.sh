#!/usr/bin/env bash
# ==============================================================================
# Prime Care v1.0.0 — Script de sauvegarde chiffrée (Phase 4 §6.2 & H6)
# - Lecture optionnelle d'un fichier d'environnement hors dépôt (/etc/prime-care/backup.env)
# - Dump MySQL transactionnel (--single-transaction)
# - Archive des fichiers téléversés (storage/uploads)
# - Chiffrement via `age` s'il est présent et configuré (AGE_RECIPIENT), sinon OpenSSL AES-256-CBC (-pbkdf2)
# - Copie optionnelle vers un second emplacement distant ou secondaire via BACKUP_REMOTE (rsync ou sftp)
# - Rotation automatique à 30 jours
# ==============================================================================
set -euo pipefail

# Chargement d'un fichier d'environnement hors du dépôt s'il existe
ENV_FILE="${PRIME_CARE_ENV_FILE:-/etc/prime-care/backup.env}"
set -a
if [[ -f "${ENV_FILE}" ]]; then
  # shellcheck disable=SC1090
  source "${ENV_FILE}"
elif [[ -f "${HOME}/.config/prime-care/backup.env" ]]; then
  # shellcheck disable=SC1090
  source "${HOME}/.config/prime-care/backup.env"
fi
set +a
export PRIME_CARE_BACKUP_KEY="${PRIME_CARE_BACKUP_KEY:-}"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${PRIME_CARE_BACKUP_DIR:-${ROOT_DIR}/storage/backups}"
DB_HOST="${PRIME_CARE_DB_HOST:-127.0.0.1}"
DB_PORT="${PRIME_CARE_DB_PORT:-3306}"
DB_NAME="${PRIME_CARE_DB_NAME:-prime_care}"
DB_USER="${PRIME_CARE_DB_USER:-root}"
DB_PASS="${PRIME_CARE_DB_PASS:-}"
RETENTION_DAYS="${PRIME_CARE_BACKUP_RETENTION_DAYS:-30}"
BACKUP_REMOTE="${BACKUP_REMOTE:-}"

USE_AGE=0
if command -v age >/dev/null 2>&1 && [[ -n "${AGE_RECIPIENT:-}" ]]; then
  USE_AGE=1
elif [[ -z "${PRIME_CARE_BACKUP_KEY:-}" ]]; then
  echo "[ERREUR] Définissez PRIME_CARE_BACKUP_KEY (OpenSSL AES-256-CBC) ou AGE_RECIPIENT (age) dans ${ENV_FILE} ou l'environnement." >&2
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

echo "[1/5] Dump transactionnel MySQL de la base '${DB_NAME}'..."
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

echo "[2/5] Archivage du répertoire storage/uploads/..."
tar -czf "${UPLOADS_ARCHIVE}" -C "${ROOT_DIR}/storage" uploads
tar -czf "${BUNDLE_TAR}" -C "${WORK_DIR}" database.sql uploads.tar.gz

if [[ "${USE_AGE}" -eq 1 ]]; then
  echo "[3/5] Assemblage et chiffrement avec age (destinataire : ${AGE_RECIPIENT})..."
  age -r "${AGE_RECIPIENT}" -o "${FINAL_ENC}" "${BUNDLE_TAR}"
else
  echo "[3/5] Assemblage et chiffrement OpenSSL AES-256-CBC (PBKDF2)..."
  openssl enc -aes-256-cbc -salt -pbkdf2 -iter 100000 \
    -in "${BUNDLE_TAR}" \
    -out "${FINAL_ENC}" \
    -pass env:PRIME_CARE_BACKUP_KEY
fi

chmod 600 "${FINAL_ENC}"
SHA256_SUM="$(sha256sum "${FINAL_ENC}" | awk '{print $1}')"
echo "${SHA256_SUM}  $(basename "${FINAL_ENC}")" > "${FINAL_ENC}.sha256"

if [[ -n "${BACKUP_REMOTE}" ]]; then
  echo "[4/5] Copie de l'archive chiffrée vers le second emplacement (${BACKUP_REMOTE})..."
  if [[ "${BACKUP_REMOTE}" == sftp://* ]]; then
    SFTP_TARGET="${BACKUP_REMOTE#sftp://}"
    SFTP_HOST="${SFTP_TARGET%%:*}"
    SFTP_PATH="${SFTP_TARGET#*:}"
    sftp -b - "${SFTP_HOST}" <<SFTP_EOF
put "${FINAL_ENC}" "${SFTP_PATH}/"
put "${FINAL_ENC}.sha256" "${SFTP_PATH}/"
SFTP_EOF
  elif command -v rsync >/dev/null 2>&1; then
    rsync -az "${FINAL_ENC}" "${FINAL_ENC}.sha256" "${BACKUP_REMOTE%/}/"
  else
    mkdir -p "${BACKUP_REMOTE}"
    cp -f "${FINAL_ENC}" "${FINAL_ENC}.sha256" "${BACKUP_REMOTE%/}/"
  fi
else
  echo "[4/5] Aucun second emplacement BACKUP_REMOTE configuré (conservation locale uniquement)."
fi

echo "[5/5] Rotation des sauvegardes locales de plus de ${RETENTION_DAYS} jours..."
find "${BACKUP_DIR}" -maxdepth 1 -type f -name "prime_care_backup_*.tar.gz.enc*" -mtime +"${RETENTION_DAYS}" -delete

echo "[OK] Sauvegarde créée : ${FINAL_ENC}"
echo "[OK] SHA-256 : ${SHA256_SUM}"
