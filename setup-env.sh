#!/bin/bash
set -e

# ==============================================================================
# FaceID Enterprise — Production Secrets & .env Generator
# ==============================================================================

ENV_FILE=".env"
EXAMPLE_FILE=".env.production.example"

echo "=================================================="
echo "🔐 FaceID Enterprise: Production .env Generator"
echo "=================================================="

if [ -f "$ENV_FILE" ]; then
    echo "⚠️  Ogohlantirish: '$ENV_FILE' fayli allaqachon mavjud!"
    read -p "Uni qaytadan yangi xavfsiz parollar bilan almashtirishni xohlaysizmi? (ha/yoq): " CONFIRM
    if [[ "$CONFIRM" != "ha" && "$CONFIRM" != "yes" && "$CONFIRM" != "y" ]]; then
        echo "❌ Bekor qilindi. Mavjud .env fayli o'zgarishsiz qoldi."
        exit 0
    fi
    cp "$ENV_FILE" "${ENV_FILE}.backup_$(date +%Y%m%d_%H%M%S)"
    echo "📦 Eski .env zaxiralandi."
fi

# 1. Kriptografik kuchli tasodifiy parollarni generatsiya qilish
echo "🎲 Kriptografik xavfsiz parollar generatsiya qilinmoqda..."

GEN_APP_KEY="base64:$(openssl rand -base64 32)"
GEN_DB_PASS=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24)
GEN_REDIS_PASS=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24)
GEN_AI_SECRET=$(openssl rand -hex 16)
GEN_MOBILE_KEY="faceidia_mob_$(openssl rand -hex 12)"
GEN_EXTERNAL_KEY="faceidia_b2b_$(openssl rand -hex 16)"
GEN_ADMIN_PASS="FaceID_$(openssl rand -base64 12 | tr -dc 'a-zA-Z0-9' | head -c 10)!"

# 2. Domen nomini so'rash (ixtiyoriy, default: api.faceidia.uz)
read -p "🌐 Server domeni yoki IP manzilini kiriting (masalan: api.yourdomain.uz): " DOMAIN_INPUT
DOMAIN_INPUT=${DOMAIN_INPUT:-"api.faceidia.uz"}
if [[ ! "$DOMAIN_INPUT" =~ ^https?:// ]]; then
    DOMAIN_URL="https://$DOMAIN_INPUT"
else
    DOMAIN_URL="$DOMAIN_INPUT"
fi

# 3. eGov GCP shlyuz ma'lumotlarini so'rash
echo "🏛️ eGov Davlat GCP Pasport Shlyuzi ma'lumotlari:"
read -p "eGov Login (agar hozir bo'lmasa bo'sh qoldiring): " EGOV_USER_INPUT
EGOV_USER_INPUT=${EGOV_USER_INPUT:-""}

read -p "eGov Parol (agar hozir bo'lmasa bo'sh qoldiring): " EGOV_PASS_INPUT
EGOV_PASS_INPUT=${EGOV_PASS_INPUT:-""}

# 4. Yangi .env faylini shakllantirish
cat << EOF > "$ENV_FILE"
# ==============================================================================
# FaceID Enterprise Production Environment Configuration
# Avtomatik generatsiya qilingan sana: $(date -u +"%Y-%m-%d %H:%M:%S UTC")
# ==============================================================================

# 1. Laravel Ilova Sozlamalari
APP_NAME="FaceID Enterprise Biometrics"
APP_ENV=production
APP_KEY=$GEN_APP_KEY
APP_DEBUG=false
APP_URL=$DOMAIN_URL

APP_LOCALE=uz
APP_FALLBACK_LOCALE=en

# 2. Ichki PostgreSQL Ma'lumotlar Bazasi (pgvector)
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=face_recognition
DB_USERNAME=faceidia_user
DB_PASSWORD=$GEN_DB_PASS

# 3. Redis Kesh va Asinxron Navbatlar
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=$GEN_REDIS_PASS

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# 4. Log Jurnallari
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

# 5. Mobil Ilova va Tashqi B2B Mijozlar Kalitlari
MOBILE_APP_KEY=$GEN_MOBILE_KEY
EXTERNAL_API_KEY=$GEN_EXTERNAL_KEY

# 6. Davlat eGov Pasport GCP Shlyuzi (gateway.imv.uz)
EGOV_PASSPORT_BASE_URL="https://gateway.imv.uz/api/v1"
EGOV_AUTH_ENDPOINT="/auth/token"
EGOV_PASSPORT_ENDPOINT="/egov/passport-gcp-document-birthdate"
EGOV_AUTH_USERNAME="$EGOV_USER_INPUT"
EGOV_AUTH_PASSWORD="$EGOV_PASS_INPUT"
EGOV_PASSPORT_TOKEN=""

# 7. Ichki AI Mikroxizmati (FastAPI ArcFace 512-d)
AI_SERVICE_URL=http://backend:8000
INTERNAL_AI_SECRET=$GEN_AI_SECRET

# 8. Bosh Administrator Hisobi (MoonShine Admin Panel)
ADMIN_DEFAULT_EMAIL=admin@faceidia.uz
ADMIN_DEFAULT_PASSWORD=$GEN_ADMIN_PASS
ADMIN_DEFAULT_NAME="Bosh Xavfsizlik Nazoratchisi"

# 9. Octane Web Server (FrankenPHP)
OCTANE_SERVER=frankenphp
EOF

# Ruxsatlarni qat'iy cheklash (faqat server egasi o'qishi uchun)
chmod 600 "$ENV_FILE"

echo ""
echo "=================================================="
echo "✅ '.env' fayli muvaffaqiyatli yaratildi!"
echo "🔒 Fayl ruxsati 'chmod 600' qilib mahkamlandi."
echo "=================================================="
echo "📋 GENERATSIYA QILINGAN MUHIM MA'LUMOTLAR:"
echo "--------------------------------------------------"
echo "🔑 Admin Panel Login:    admin@faceidia.uz"
echo "🔑 Admin Panel Parol:    $GEN_ADMIN_PASS"
echo "🌐 Sayt manzili:         $DOMAIN_URL"
echo "--------------------------------------------------"
echo "💡 Eslatma: Ushbu Admin parolini xavfsiz joyga saqlab qo'ying!"
echo "   Database va Redis parollari konteynerlar o'rtasida avtomatik ulandi."
echo "=================================================="
