#!/bin/bash
set -e

# ==============================================================================
# FaceID Enterprise Automated Database & Biometrics Backup Script
# ==============================================================================

BACKUP_DIR="${BACKUP_DIR:-./backups}"
DATE=$(date +"%Y%m%d_%H%M%S")
mkdir -p "$BACKUP_DIR/db"
mkdir -p "$BACKUP_DIR/biometrics"

echo "[Backup] Boshlandi: $DATE"

# Load environment if exists
if [ -f ".env" ]; then
    DB_USER=$(grep -E '^DB_USERNAME=' .env | cut -d '=' -f2 | tr -d ' "' || true)
    DB_NAME=$(grep -E '^DB_DATABASE=' .env | cut -d '=' -f2 | tr -d ' "' || true)
    REDIS_PASS=$(grep -E '^REDIS_PASSWORD=' .env | cut -d '=' -f2 | tr -d ' "' || true)
fi
DB_USER="${DB_USER:-user}"
DB_NAME="${DB_NAME:-face_recognition}"

# 1. PostgreSQL zaxira nusxasi (pg_dump)
DB_CONTAINER=$(docker ps --filter "name=faceidia-db" --format "{{.ID}}" | head -n 1)

if [ -n "$DB_CONTAINER" ]; then
    echo "[Backup] PostgreSQL ma'lumotlar bazasi nusxalanmoqda..."
    docker exec "$DB_CONTAINER" pg_dump -U "$DB_USER" "$DB_NAME" | gzip > "$BACKUP_DIR/db/${DB_NAME}_$DATE.sql.gz"
    echo "[Backup] DB zaxiralandi: $BACKUP_DIR/db/${DB_NAME}_$DATE.sql.gz"
else
    echo "[Backup ERROR] DB konteyneri topilmadi."
fi

# 2. Redis ma'lumotlar zaxirasi (dump.rdb)
mkdir -p "$BACKUP_DIR/redis"
REDIS_CONTAINER=$(docker ps --filter "name=faceidia-redis" --format "{{.ID}}" | head -n 1)
if [ -n "$REDIS_CONTAINER" ]; then
    echo "[Backup] Redis snapshot olinmoqda..."
    if [ -n "$REDIS_PASS" ]; then
        docker exec "$REDIS_CONTAINER" redis-cli --no-auth-warning -a "$REDIS_PASS" bgsave 2>/dev/null || true
    fi
    docker exec "$REDIS_CONTAINER" tar -czf - -C /data dump.rdb 2>/dev/null > "$BACKUP_DIR/redis/redis_$DATE.tar.gz" || true
    echo "[Backup] Redis zaxiralandi: $BACKUP_DIR/redis/redis_$DATE.tar.gz"
fi

# 3. Biometrik suratlar saqlagichi (Tarball)
LARAVEL_CONTAINER=$(docker ps --filter "name=faceidia-laravel" --format "{{.ID}}" | head -n 1)

if [ -n "$LARAVEL_CONTAINER" ]; then
    echo "[Backup] Biometrik fotosuratlar nusxalanmoqda..."
    docker exec "$LARAVEL_CONTAINER" tar -czf - -C /var/www/html/storage/app/private biometrics > "$BACKUP_DIR/biometrics/biometrics_$DATE.tar.gz"
    echo "[Backup] Biometrik suratlar zaxiralandi: $BACKUP_DIR/biometrics/biometrics_$DATE.tar.gz"
fi

# 4. 7 kundan eski zaxira nusxalarini tozalash (Retention Policy)
echo "[Backup] 7 kundan eski zaxiralar tozalanmoqda..."
find "$BACKUP_DIR/db" -name "*.sql.gz" -mtime +7 -delete 2>/dev/null || true
find "$BACKUP_DIR/redis" -name "*.tar.gz" -mtime +7 -delete 2>/dev/null || true
find "$BACKUP_DIR/biometrics" -name "*.tar.gz" -mtime +7 -delete 2>/dev/null || true

echo "[Backup] Muvaffaqiyatli yakunlandi."
