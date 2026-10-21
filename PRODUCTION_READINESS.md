# 🛡️ FaceID Enterprise — Production Readiness & DevOps Deployment Guide

Ushbu qo'llanma **FaceID Enterprise Biometrics** loyihasini real serverda (**Production Environment**) yuqori yuklamalar (High Availability), maksimal xavfsizlik (Bank-grade Security) va 99.9% uptime bilan ishga tushirish uchun to'liq DevOps yo'riqnomasidir.

---

## 1. 🏗️ Tizim Arxitekturasi (Production Stack)

```
[ Internet / Mobile App / B2B Clients ]
                  │
                  ▼ HTTPS (443) / HTTP (80)
┌─────────────────────────────────────────────────────────────┐
│                 Nginx Reverse Proxy (Alpine)                │
│  - SSL Termination (Let's Encrypt / TLS 1.3 / HTTP/2)       │
│  - Rate Limiting (30 req/s, burst 30) & DDoS Protection    │
│  - Security Headers (HSTS, CSP, X-Frame-Options, Nosniff)  │
└──────────────┬──────────────────────────────┬───────────────┘
               │ (Proxy pass internal)         │ (Static docs)
               ▼                               ▼
┌──────────────────────────────┐        ┌─────────────────────┐
│  Laravel 11 + FrankenPHP     │        │ Swagger /docs UI    │
│  - Worker Engine: Octane     │        └─────────────────────┘
│  - Admin: MoonShine 4        │
│  - B2B Gateway & Auth        │
└──────┬──────────────┬────────┘
       │              │
       ▼              ▼
┌──────────────┐ ┌──────────────┐ ┌───────────────────────────┐
│ FastAPI AI   │ │ PostgreSQL   │ │ Redis 7 In-Memory Cache   │
│ ArcFace 512d │ │ + pgvector   │ │ - Sessions & Rate-limits  │
│ Anti-spoof   │ │ HNSW Index   │ │ - Asynchronous Queues     │
└──────────────┘ └──────────────┘ └───────────────────────────┘
```

---

## 2. 💻 Serverga Minimal va Tavsiya Etilgan Talablar

| Parametr | Minimal Talab (Test / Demo) | Tavsiya etilgan (Production 100k+ user) |
|---|---|---|
| **OS** | Ubuntu 22.04 LTS / 24.04 LTS | Ubuntu 22.04 LTS / 24.04 LTS |
| **CPU** | 4 vCPU (x86_64) | 8–16 vCPU (Intel Xeon / AMD EPYC) |
| **RAM** | 8 GB RAM | 16 GB – 32 GB RAM (ECC) |
| **Disk** | 50 GB NVMe SSD | 150 GB+ NVMe SSD (RAID-1 tavsiya) |
| **GPU (Ixtiyoriy)** | Kerak emas (ONNX CPU) | NVIDIA T4 / A10 (Tezlik 20ms ga tushadi) |
| **Tarmoq** | 100 Mbps | 1 Gbps port, Static Public IP |

---

## 3. ⚙️ Linux Host Tizimini Sozlash (Kernel Tuning)

Serverga kirgach, terminalda quyidagi buyruqlarni bajaring:

```bash
# 1. Tizim paketlarini yangilash
sudo apt-get update && sudo apt-get upgrade -y
sudo apt-get install -y curl git ufw fail2ban jq htop pigz rsync

# 2. Redis va Tarmoq uchun Linux Kernel parametrlarini sozlash
sudo tee -a /etc/sysctl.conf << 'EOF'
# Redis Memory Overcommit
vm.overcommit_memory = 1
# Maximum open files and connections
fs.file-max = 2097152
net.core.somaxconn = 65535
net.ipv4.tcp_max_syn_backlog = 8192
# Swap usage reduction (SSD diskni asrash)
vm.swappiness = 10
EOF

sudo sysctl -p

# 3. Fayl deskriptorlari limitlarini ko'tarish
sudo tee -a /etc/security/limits.conf << 'EOF'
* soft nofile 65535
* hard nofile 65535
root soft nofile 65535
root hard nofile 65535
EOF
```

---

## 4. 🔒 Xavfsizlik va Firewall (UFW) Sozlamalari

Faqat kerakli portlarni oching, qolgan barcha portlar tashqi dunyodan yopiq bo'ladi:

```bash
# UFW Firewallni sozlash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP (Let's Encrypt ACME uchun)
sudo ufw allow 443/tcp   # HTTPS
sudo ufw --force enable
sudo ufw status verbose
```
> [!IMPORTANT]
> PostgreSQL (`5432`), Redis (`6379`), FastAPI (`8000`) va Laravel Octane (`8080`) portlari tashqariga chiqmaydi, ular faqat Docker ichki tarmog'ida (`faceidia-net`) xavfsiz ishlaydi.

---

## 5. 🚀 Birinchi Marta Productionga Joylash (Initial Deployment)

### 1-Qadam: Loyihani klonlash
```bash
git clone https://github.com/davronee/faceIDIA.git /var/www/faceidia
cd /var/www/faceidia
```

### 2-Qadam: Xavfsiz Parollar va .env ni Avtomatik Generatsiya Qilish
DevOps yoki administrator parollarni qo'lda o'ylab topib o'tirmaydi. Bitta buyruq bilan barcha ichki parollar (DB, Redis, AI Secret, App Key) kriptografik darajada xavfsiz generatsiya qilinadi:

```bash
./setup-env.sh
```

Ushbu skript:
1. `DB_PASSWORD`, `REDIS_PASSWORD`, `INTERNAL_AI_SECRET` va `APP_KEY` larni avtomatik yaratadi.
2. Sizdan faqat domenni (masalan: `api.yourdomain.uz`) va eGov ma'lumotlarini so'raydi.
3. Yangi tasodifiy kuchli **Admin Paroli**ni generatsiya qilib, ekranda ko'rsatadi.
4. `.env` faylini yaratib, ruxsatini `chmod 600` (faqat egasi o'qishi uchun) qilib yopadi.

### 3-Qadam: Tizimni Ishga Tushirish (Deploy)
```bash
chmod +x deploy.sh backup.sh init-ssl.sh
./deploy.sh
```

### 4-Qadam: Rasmiy Let's Encrypt SSL Sertifikatini Olish
Domen (masalan `api.yourdomain.uz`) serveringiz IP siga yo'naltirilgach:
```bash
docker compose -f docker-compose.prod.yml run --rm certbot certonly \
    --webroot -w /var/www/certbot \
    -d api.yourdomain.uz \
    --email admin@yourdomain.uz \
    --agree-tos --no-eff-email

# Nginx konfiguratsiyasini yangilash
docker compose -f docker-compose.prod.yml exec nginx nginx -s reload
```

---

## 6. 🗄️ Avtomatik Zaxiralash (Automated Backup & Disaster Recovery)

Serverda har kecha soat 03:00 da PostgreSQL bazasi, Redis snapshotlari va biometrik rasmlar zaxiralanadi:

```bash
# Crontabni ochish
crontab -e

# Quyidagi qatorni qo'shing:
0 3 * * * cd /var/www/faceidia && ./backup.sh >> /var/log/faceidia-backup.log 2>&1
```

Zaxiralar `./backups/` papkasiga saqlanadi va 7 kundan eski nusxalar avtomatik tozalanadi.

---

## 7. 🔄 Zero-Downtime Yangilanishlar (CI/CD Deployment)

Yangi kod versiyasi chiqarilganda tizim to'xtamasdan (downtimesiz) yangilanadi:

```bash
cd /var/www/faceidia
git pull origin main
./deploy.sh
```

`deploy.sh` skripti avtomatik quyidagilarni bajaradi:
1. Docker image'larni qayta yig'adi (`--build`).
2. Yangi DB migratsiyalarini xavfsiz o'tkazadi (`migrate --force`).
3. Laravel config, route va view keshlarini yangilaydi.
4. Octane va Queue Workerlarni qayta yuklaydi (`octane:reload`, `queue:restart`).
5. Muddati o'tgan eski tokenlarni tozalaydi (`sanctum:prune-expired`).
6. `/api/health` tekshiruvini amalga oshiradi.

---

## 8. 🩺 Monitoring va Tekshiruv Buyruqlari

```bash
# Barcha konteynerlar holati
docker compose -f docker-compose.prod.yml ps

# Jonli resurs sarfi (CPU, RAM, Network I/O)
docker stats --no-stream

# Loglarni real vaqtda kuzatish
docker compose -f docker-compose.prod.yml logs -f --tail=50 laravel
docker compose -f docker-compose.prod.yml logs -f --tail=50 backend

# AI Servis sog'lomligi
curl -s http://localhost:8000/health | jq .

# Laravel sog'lomligi
curl -s http://localhost:8080/up
```
