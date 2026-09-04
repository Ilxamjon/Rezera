#!/usr/bin/env bash
# Daily PostgreSQL backup for Rezera.
# Usage:
#   export PGPASSWORD=...
#   ./scripts/backup-postgres.sh
# Env overrides: DB_HOST DB_PORT DB_NAME DB_USER BACKUP_DIR RETENTION_DAYS

set -euo pipefail

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_NAME:-rezera}"
DB_USER="${DB_USER:-rezera}"
BACKUP_DIR="${BACKUP_DIR:-./storage/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"

mkdir -p "$BACKUP_DIR"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
FILE="${BACKUP_DIR}/${DB_NAME}_${STAMP}.dump"

pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -Fc -f "$FILE" "$DB_NAME"
echo "Wrote $FILE"

find "$BACKUP_DIR" -name "${DB_NAME}_*.dump" -type f -mtime +"$RETENTION_DAYS" -delete
echo "Retention: deleted dumps older than ${RETENTION_DAYS} days"
