#!/bin/bash
set -e

# ==============================================================================
# FaceID Enterprise Production Deployment Script
# ==============================================================================

echo "=================================================="
echo "🚀 FaceID Enterprise Production Deployment Boshlandi"
echo "=================================================="

# 1. Muhit faylini tekshirish (agar bo'lmasa avtomatik generatsiya qilinadi)
if [ ! -f ".env" ]; then
    echo "⚙️  .env fayli topilmadi. Avtomatik xavfsiz parollar generatsiyasi boshlanmoqda..."
    chmod +x ./setup-env.sh
    ./setup-env.sh
fi

# 2. SSL sertifikatlarini tayyorlash
chmod +x ./init-ssl.sh
./init-ssl.sh

# 3. Docker konteynerlarini yangilash va ishga tushirish
echo "📦 Konteynerlar yig'ilmoqda va ishga tushirilmoqda..."
docker compose -f docker-compose.prod.yml up -d --build

# 4. Ma'lumotlar bazasi migratsiyasi va Admin seeder
echo "🗄️ Ma'lumotlar bazasi migratsiyalari tekshirilmoqda..."
docker compose -f docker-compose.prod.yml exec -T laravel php artisan migrate --force

echo "👤 Admin foydalanuvchisi sozlanmoqda..."
docker compose -f docker-compose.prod.yml exec -T laravel php artisan db:seed --class=AdminUserSeeder --force

# 5. Laravel keshlash va optimizatsiya
echo "⚡ Laravel Octane & kesh optimizatsiyasi..."
docker compose -f docker-compose.prod.yml exec -T laravel php artisan config:cache
docker compose -f docker-compose.prod.yml exec -T laravel php artisan route:cache
docker compose -f docker-compose.prod.yml exec -T laravel php artisan view:cache
docker compose -f docker-compose.prod.yml exec -T laravel php artisan octane:reload
docker compose -f docker-compose.prod.yml exec -T laravel-worker php artisan queue:restart 2>/dev/null || true
docker compose -f docker-compose.prod.yml exec -T laravel php artisan sanctum:prune-expired 2>/dev/null || true

# 6. Sog'lomlik tekshiruvi (Healthcheck)
echo "🩺 Tizim tekshiruvi..."
sleep 3
HEALTH_CHECK=$(docker compose -f docker-compose.prod.yml exec -T laravel curl -s http://localhost:8080/api/health || true)

echo "Natija: $HEALTH_CHECK"
echo "=================================================="
echo "✅ Tizim Production rejimida muvaffaqiyatli ishga tushdi!"
echo "📍 Swagger Docs: https://api.yourdomain.uz/docs"
echo "📍 Admin Panel:  https://api.yourdomain.uz/admin"
echo "=================================================="
