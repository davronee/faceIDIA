#!/bin/bash
set -e

# ==============================================================================
# FaceID Enterprise SSL Initialization Script
# ==============================================================================

SSL_DIR="./nginx/ssl/live"
mkdir -p "$SSL_DIR"

if [ ! -f "$SSL_DIR/fullchain.pem" ] || [ ! -f "$SSL_DIR/privkey.pem" ]; then
    echo "[SSL Init] SSL sertifikatlari topilmadi. Vaqtinchalik self-signed sertifikat yaratilmoqda..."
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout "$SSL_DIR/privkey.pem" \
        -out "$SSL_DIR/fullchain.pem" \
        -subj "/C=UZ/ST=Tashkent/L=Tashkent/O=FaceID/OU=IT/CN=faceidia.uz"
    echo "[SSL Init] Vaqtinchalik self-signed sertifikat muvaffaqiyatli yaratildi."
else
    echo "[SSL Init] Mavjud SSL sertifikatlari topildi ($SSL_DIR)."
fi
