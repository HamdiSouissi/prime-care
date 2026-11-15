#!/usr/bin/env bash
# ==============================================================================
# Prime Care v1.0.0 — Script de restauration d'une sauvegarde chiffrée (Phase 4 §6.2)
# Usage :
#   PRIME_CARE_BACKUP_KEY="..." ./bin/restore.sh <fichier.tar.gz.enc> [nom_base_cible]
# ==============================================================================
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Usage : PRIME_CARE_BACKUP_KEY=\"...\" $0 <archive.tar.gz.enc> [db_name]" >&2
  exit 1
fi

ARCHIVE_ENC="$1"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_HOST="${PRIME_CARE_DB_HOST:-127.0.0.1}"
DB_PORT="${PRIME_CARE_DB_PORT:-3306}"
DB_NAME="${2:-${PRIME_CARE_DB_NAME:-prime_care}}"
DB_USER="${PRIME_CARE_DB_USER:-root}"
DB_PASS="${PRIME_CARE_DB_PASS:-}"
RESTORE_UPLOADS_DIR="${PRIME_CARE_RESTORE_STORAGE_DIR:-${ROOT_DIR}/storage}"

if [[ -z "${PRIME_CARE_BACKUP_KEY:-}" ]]; then
  echo "[ERREUR] La variable d'environnement PRIME_CARE_BACKUP_KEY est obligatoire pour déchiffrer la sauvegarde." >&2
  exit 1
fi

if [[ ! -f "${ARCHIVE_ENC}" ]]; then
  echo "[ERREUR] Fichier de sauvegarde introuvable : ${ARCHIVE_ENC}" >&2
  exit 1
fi

WORK_DIR="$(mktemp -d)"
trap 'rm -rf "${WORK_DIR}"' EXIT

BUNDLE_TAR="${WORK_DIR}/bundle.tar.gz"

echo "[1/3] Déchiffrement AES-256-CBC (PBKDF2) de ${ARCHIVE_ENC}..."
openssl enc -d -aes-256-cbc -pbkdf2 -iter 100000 \
  -in "${ARCHIVE_ENC}" \
  -out "${BUNDLE_TAR}" \
  -pass env:PRIME_CARE_BACKUP_KEY

tar -xzf "${BUNDLE_TAR}" -C "${WORK_DIR}"

if [[ -n "${DB_PASS}" ]]; then
  export MYSQL_PWD="${DB_PASS}"
fi

echo "[2/3] Restauration de la base de données MySQL '${DB_NAME}'..."
mysql --host="${DB_HOST}" --port="${DB_PORT}" --user="${DB_USER}" \
  -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql --host="${DB_HOST}" --port="${DB_PORT}" --user="${DB_USER}" --default-character-set=utf8mb4 \
  "${DB_NAME}" < "${WORK_DIR}/database.sql"

echo "[3/3] Restauration des fichiers téléversés dans ${RESTORE_UPLOADS_DIR}/uploads..."
mkdir -p "${RESTORE_UPLOADS_DIR}"
tar -xzf "${WORK_DIR}/uploads.tar.gz" -C "${RESTORE_UPLOADS_DIR}"

echo "[OK] Restauration terminée avec succès sur la base '${DB_NAME}'."
