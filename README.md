# Enterprise Face ID Biometric System (REST API & Admin)

![PHP](https://img.shields.io/badge/PHP-8.3-777BB4.svg)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20.svg)
![FrankenPHP](https://img.shields.io/badge/FrankenPHP-Octane-blueviolet.svg)
![FastAPI](https://img.shields.io/badge/FastAPI-0.100+-green.svg)
![Python](https://img.shields.io/badge/Python-3.10-blue.svg)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-pgvector-informational.svg)
![Redis](https://img.shields.io/badge/Redis-7.x-red.svg)
![Docker](https://img.shields.io/badge/Docker-Production_Ready-blue.svg)

## Loyiha Arxitekturasi

**Enterprise Face ID System** — bu davlat tashkilotlari, banklar va yirik korporatsiyalar uchun mo'ljallangan yuqori xavfsizlikka ega biometrik identifikatsiya platformasi.

Tizim quyidagi ko'p qatlamli (Multi-Tier) mikroservis arxitekturasi asosida ishlaydi:
1. **Mijoz qatlamlari (REST API Clients):** Tashqi axborot tizimlari, banklar, davlat portallari va turniketlar (REST API orqali biometrik so'rovlarni yuboradi).
2. **API Gateway & Admin Panel (Laravel 13 + FrankenPHP Octane):**
   - Tashqi tizimlar va hamkorlar uchun yuqori unumdorlikdagi REST API (`/api/v1/auth/token`, `/api/v1/external/verify`, `/api/health`).
   - HTTP Basic Auth orqali 24 soatlik Bearer token olish va Sanctum xavfsizligi.
   - **MoonShine Admin Panel** (`/admin`) — arizalarni ko'rib chiqish, tasdiqlash/rad etish, biometrik fotosuratlarni xavfsiz ko'rish va xavfsizlik audit jurnali.
3. **Sun'iy Intellekt va Jonlilikni Tekshirish Servisi (FastAPI + InsightFace + MiniFASNet):**
   - 512-o'lchamli biometrik neyron vektorlarni ajratib olish (`buffalo_sc`).
   - Passiv Anti-Spoofing (ekrandan video qo'yish yoki qog'ozdagi rasmni aniqlash).
4. **Vektorli Ma'lumotlar Bazasi (PostgreSQL + pgvector):**
   - HNSW kosinus masofasi indeksi orqali millionlab foydalanuvchilar ichidan 1-5 millisekundda qidirish.
5. **Yuqori Tezlikdagi Kesh, Sessiya va Navbatlar (Redis 7 + phpredis):**
   - FrankenPHP Octane stateless xotira rejimida HTTP sessiyalar va keshni sub-millisekund (<0.5ms) tezlikda saqlash.
   - API so'rovlari va FaceID urinishlari uchun taqsimlangan Rate Limiting (`throttle`).
   - Fon vazifalarini asinxron bajaruvchi Redis Queues (audit loglar, tasvirlarni siqish, bildirishnomalar).
6. **Infratuzilma (DevOps):**
   - Nginx Reverse Proxy (SSL, DDoS rate-limiting, HTTP/2).
   - Docker Compose Production orkestratsiyasi, avtomatik zaxira nusxalash (`backup.sh`) va uzluksiz yangilash (`deploy.sh`).

---

##  Serverga Texnik Shartlar (3M — 10M Foydalanuvchi Masshtabi Uchun)

Tizim mavjud yirik axborot tizimiga integratsiya qilinayotgani va **hozirda 3 millionta, kelajakda esa 10 milliontagacha fuqaro** FaceID orqali autentifikatsiyadan o'tishi hisobga olingan holda resurslar matematik hisob-kitob asosida taqsimlandi.

---

###  Resurslar va Ma'lumotlar Sarfi Hisob-Kitobi (Data Sizing)

| Ko'rsatkich | 3 Million Foydalanuvchi | 10 Million Foydalanuvchi | Izoh |
| :--- | :--- | :--- | :--- |
| **Biometrik Vektorlar (Jadval)** | ~7.5 GB | ~25 GB | 512 ta float32 (2KB) + qator ma'lumotlari |
| **HNSW Grafigi Indeksi (RAM)** | **10 — 12 GB** | **35 — 42 GB** | `m=16`, `ef_construction=64`. **Tezlik 1-5ms bo'lishi uchun to'liq RAMda turishi shart!** |
| **Redis In-Memory RAM (Kesh & Sessiya)** | **4 — 8 GB RAM** | **16 — 32 GB RAM** | Sessiyalar, token kesh, rate-limiter hisoblagichlari va asinxron navbatlar |
| **Biometrik Fotosuratlar** | ~450 GB | ~1.5 TB | 1080p siqilgan JPEG (~120-150 KB/shaxs) |
| **Tig'iz Vaqtdagi Oqim (Peak QPS)** | 100 — 250 so'rov/soniya | 400 — 800+ so'rov/soniya | Ertalabki/kunduzgi eng yuqori kirish vaqtlari |

> ️ **DevOpsdan Muhim Qoida:** Agar PostgreSQL `pgvector` HNSW indeksi RAM xotiraga to'liq sig'masa va diskdan (paging) o'qiy boshlasa, yuzni qidirish tezligi 2 millisekunddan **250-400 millisekundgacha sekinlashadi** va tig'iz paytlarda serverda so'rovlar tiqilishi yuzaga keladi. Shuning uchun RAM hajmi eng kritik parametr hisoblanadi.

---

###  Variant 1: Yagona Kuchli Bare-Metal Server (All-in-One High-End)
*Ushbu variant **3 million** foydalanuvchi uchun ayni muddao va **10 milliongacha** o'rta oqimda barqaror xizmat ko'rsatish imkonini beradi (Barcha konteynerlar bitta yirik serverda ishlaydi).*

* **Protsessor (CPU):** **32 — 64 vCPU / Cores** (AMD EPYC 7003/9004 seriyasi yoki Intel Xeon Gold/Platinum, AVX2 / AVX-512 qo'llab-quvvatlovi bilan).
* **Tezkor Xotira (RAM):** **128 GB — 256 GB ECC DDR4/DDR5 RAM** (PostgreSQL shared_buffers, HNSW grafigi, Octane workerlar va Redis uchun).
* **Redis In-Memory Qatlami:** **8 — 16 GB ajratilgan RAM** (Redis 7 — Octane stateless sessiyalari, taqsimlangan rate-limiting, tokenlar keshi va asinxron navbatlar).
* **Doimiy Xotira (Disk):** **2x 2 TB yoki 2x 4 TB NVMe SSD (RAID 1 Mirror)**.  
  *O'qish/yozish tezligi kamida 3,500+ MB/s (PCIe 4.0 NVMe Enterprise U.2/U.3).*
* **Videokarta (GPU — Majburiy darajada tavsiya etiladi):**  
  * **1x yoki 2x NVIDIA L4 (24GB)** yoki **NVIDIA A10 (24GB)** / **RTX 4090 (24GB)**.  
  *CUDA 12.x va TensorRT/ONNX Execution Provider orqali yuzni aniqlash va passiv liveness tekshirish tezligi 15-25 millisekundni tashkil qiladi va soniyasiga 400-600 ta yuzni parallel qayta ishlay oladi.*
* **Tarmoq (Network):** Kamida 1 Gbps — 10 Gbps barqaror kanal, statik oq IP.

---

###  Variant 2: Klasterli Mikroservis Arxitekturasi (Scale-Out / 10M Enterprise HA)
*Ushbu variant **10 million** foydalanuvchili, to'xtovsiz (24/7 SLA 99.99%) va davlat/bank darajasidagi yirik yuklamalar uchun mo'ljallangan klaster tuzilmasi:*

```
                    [ 2x Nginx Load Balancers (Keepalived/CARP) ]
                                         │
                 ┌───────────────────────┴───────────────────────┐
                 ▼                                               ▼
   [ 2-3x API Gateway Node ]                       [ 2x AI GPU Inference Node ]
   (Laravel 13 + FrankenPHP Octane)                (FastAPI + InsightFace + CUDA)
   8-16 vCPU, 32 GB RAM                            16 vCPU, 32 GB RAM, NVIDIA L4/A10
          │              │                                       │
          │              └───────────────────────┬───────────────┘
          ▼                                      ▼
   [ Redis 7 HA Klasteri ]             [ PostgreSQL pgvector Klasteri ]
   (Sentinel / Master-Replica)         • Master Node:   32 vCPU, 128 GB RAM
   • 2x Node (8 vCPU, 32 GB RAM)       • Read Replica:  32 vCPU, 128 GB RAM
   (Kesh, Sessiya, Rate Limiter)                 │
                                                 ▼
                                     [ MinIO / S3 Object Storage ]
                                     (1.5 TB — 3 TB Biometrik fotosuratlar)
```

1. **Load Balancer Qatlami (2 ta Node):** 4 vCPU, 8 GB RAM (SSL terminatsiyasi va DDoS limitlari).
2. **API Gateway & Admin Qatlami (2-3 ta Node):** Har biri 8-16 vCPU, 32 GB RAM (FrankenPHP workerlari yuzlab parallel HTTP so'rovlarni o'zida ushlaydi).
3. **AI Dedikatsiyalangan GPU Nodlari (2 ta Node):** Har biri 8-16 vCPU, 32 GB RAM + 1x NVIDIA L4/A10 GPU (Faqat yuzni aniqlash, liveness va 512-d vektor chiqarish bilan shug'ullanadi).
4. **Redis 7 HA In-Memory Klasteri (2 ta Node + Redis Sentinel):** Har biri 8 vCPU, **32 GB RAM** (Stateless Octane sessiyalari, tokenlar keshi, taqsimlangan rate-limiter hisoblagichlari va asinxron navbatlarni sub-millisekundda uzatish).
5. **Ma'lumotlar Bazasi Qatlami (Master + Read Replica):**
   - **PostgreSQL Master Node:** 32 vCPU, **128 GB RAM**, 2 TB Enterprise NVMe SSD (Ro'yxatdan o'tish va admin boshqaruvi uchun).
   - **PostgreSQL Read Replica Node:** 32 vCPU, **128 GB RAM**, 2 TB Enterprise NVMe SSD (Barcha tashqi tizimlardan kelayotgan yuqori yuklamali biometrik qidiruv so'rovlari replika bazaga yo'naltiriladi).
6. **Suratlar Uchun Object Storage (S3 / MinIO):** 10 million shaxsning 1.5 TB suratlarini alohida S3-mos saqlagichda saqlash tavsiya etiladi.

---

###  PostgreSQL pgvector Sozlamalari (3M — 10M uchun Kernel Tuning)

3M — 10M biometrik bazada HNSW qidiruvi 1-5 millisekundda ishlashi uchun `postgresql.conf` da quyidagi parametrlar sozlanishi shart:

```ini
# Xotira taqsimoti (128 GB RAM ga mo'ljallangan)
shared_buffers = 32GB                  # HNSW indeksi va jadvallarni xotirada saqlash
effective_cache_size = 96GB            # OS disk keshi bilan birgalikdagi umumiy kesh
work_mem = 64MB                        # Murakkab saralash amallari uchun
maintenance_work_mem = 8GB             # HNSW indeksini tez qurish (Reindex) uchun

# Parallel protsessorlardan foydalanish
max_worker_processes = 16
max_parallel_workers = 16
max_parallel_workers_per_gather = 4
max_parallel_maintenance_workers = 4

# HNSW qidiruv aniqligi va tezligi balansi
# (So'rov oldidan: SET hnsw.ef_search = 64; 99.8% aniqlik va ~2ms tezlik)
```

---

### Redis 7 In-Memory Arxitekturasi va Production Tuning (3M — 10M Uchun)

FrankenPHP Octane ko'p oqimli (multi-worker) modelida ishlaydi. So'rovlarni mikrosekundlarda qayta ishlash va ma'lumotlar yaxlitligini ta'minlash uchun **Redis 7** barcha workerlar uchun markaziy tezkor xotira vazifasini bajaradi.

#### 1. Ma'lumotlar Bazalarini Izolyatsiya Qilish (Logical DB Partitioning)
Ma'lumotlar aralashib ketmasligi va tozalash amallari bir-biriga ta'sir qilmasligi uchun mantiqiy bazalar ajratilgan:
* **Redis DB 0 (`CACHE_STORE=redis`):** API javoblari, tokenlar va tez-tez o'qiladigan biometrik metama'lumotlar keshi.
* **Redis DB 1 (`SESSION_DRIVER=redis`):** Admin panel va veb-foydalanuvchilarning faol sessiyalari.
* **Redis DB 2 (`QUEUE_CONNECTION=redis`):** Asinxron fon navbatlari (surat siqish, audit arxivlash va webhooklar).

#### 2. Kesh va Xotirani Boshqarish Siyosati (Eviction Policy)
* **Xotira hajmi:** 3M foydalanuvchi uchun **4 GB**, 10M uchun **16 — 32 GB** tavsiya etiladi.
* **`maxmemory-policy: allkeys-lru`:** Xotira to'lgan holatda eng kam foydalanilgan (LRU) va muddati o'tgan kalitlar xavfsiz chiqarib yuboriladi, sessiyalar uzilib qolmaydi.
* **Doimiylik (Persistence - RDB + AOF):**
  - `appendonly yes` va `appendfsync everysec` — har bir soniyada diskka yoziladi (server o'chib qolsa ham maksimal yo'qotish 1 soniyadan oshmaydi).
  - `save 300 10` — 5 daqiqada 10 tadan ortiq o'zgarish bo'lsa, ixcham RDB snapshot saqlanadi.

#### 3. Linux Yadro (Kernel) Sozlamalari (Host OS Tuning)
Redis tig'iz yuklamada xotira yetishmasligi (OOM) xatolarini bermasligi uchun serverda quyidagi buyruqlar kiritiladi:
```bash
# Xotirani ajratish rejimini to'liq ochish
sudo sysctl vm.overcommit_memory=1
echo "vm.overcommit_memory = 1" | sudo tee -a /etc/sysctl.conf

# Tarmoq ulanishlari navbatini oshirish (DDoS va yuqori QPS paytida)
sudo sysctl -w net.core.somaxconn=1024
echo "net.core.somaxconn = 1024" | sudo tee -a /etc/sysctl.conf

# Transparent Huge Pages (THP) ni o'chirish (Redis latency oshib ketmasligi uchun)
echo never | sudo tee /sys/kernel/mm/transparent_hugepage/enabled
```

#### 4. Redis Xavfsizligi va Parol Himoyasi
* Barcha ulanishlar `.env` dagi `REDIS_PASSWORD` orqali shifrlangan autentifikatsiya bilan amalga oshiriladi.
* Production muhitida (`docker-compose.prod.yml`) Redis porti tashqi internetga ochilmaydi, faqat ichki xavfsiz Docker tarmog'ida (`bridge: internal`) ishlaydi.

#### 5. Foydali Redis CLI Buyruqlari
```bash
# 1. Redis bilan aloqani tekshirish (Ping)
docker compose exec redis redis-cli --no-auth-warning -a "${REDIS_PASSWORD:-FaceID_Redis_Secure_Pass_2026!}" ping
# yoki to'g'ridan-to'g'ri:
docker exec faceidia-redis redis-cli --no-auth-warning -a 'FaceID_Redis_Secure_Pass_2026!' ping

# 2. Xotira sarfi va xotira boshqaruvi (Memory & Policy)
docker exec faceidia-redis redis-cli --no-auth-warning -a 'FaceID_Redis_Secure_Pass_2026!' info memory

# 3. Keshdagi kalitlar sonini tekshirish (DB bo'yicha)
docker exec faceidia-redis redis-cli --no-auth-warning -a 'FaceID_Redis_Secure_Pass_2026!' info keyspace

# 4. Real vaqt rejimida barcha so'rovlar oqimini kuzatish (Debug / Monitor)
docker exec faceidia-redis redis-cli --no-auth-warning -a 'FaceID_Redis_Secure_Pass_2026!' monitor

# 5. Keshni xavfsiz tozalash (Faqat DB 0 keshini)
docker compose exec laravel php artisan cache:clear
```

###  Dasturiy Ta'minot va Tarmoq Talablari
* **Operatsion Tizim (OS):** Linux (Ubuntu 22.04 LTS / 24.04 LTS yoki Debian 12 / Rocky Linux 9).
* **Docker Engine:** Versiya `25.0+` & **Docker Compose:** `v2.24+`.
* **NVIDIA Stack (GPU uchun):** NVIDIA Container Toolkit, CUDA Driver `535+` / `550+`.
* **Xavfsizlik va Ochiq Portlar:**
  * `80/tcp` (HTTP) & `443/tcp` (HTTPS).
  * `22/tcp` (SSH — faqat VPN yoki oq IP ro'yxati orqali).
  *(PostgreSQL `5432` va FastAPI `8000` portlari qat'iyan faqat ichki tarmoqda (Private Subnet) yashirin bo'lishi shart).*

---

## Ishga Tushirish (Quick Start)

### 1. Ishlab chiqish muhiti (Development / Local)
```bash
# Repozitoriyda konteynerlarni ishga tushirish
docker compose up -d --build

# Havolalar:
# - API Swagger UI: http://localhost:8080/docs
# - Admin Panel:    http://localhost:8080/admin
# - Healthcheck:    http://localhost:8080/api/health
```

### 2. Ishlab chiqarish muhiti (Production Deployment)
```bash
# 1. Production muhit sozlamalarini tayyorlash
cp .env.production.example .env.production
nano .env.production # Parollar va APP_KEY ni o'zgartiring

# 2. Avtomatlashtirilgan deploy skriptini yurgazish
chmod +x deploy.sh backup.sh init-ssl.sh
./deploy.sh
```

---

## 🏢 Tashqi B2B Biometrik REST API Integratsiyasi

Ushbu REST API tashqi hamkorlar (Banklar, to'lov tashkilotlari, davlat portallari va korxonalar) uchun fuqaroning pasport ma'lumotlari va jonli fotosuratini davlat e-Gov GCP bazasidagi fotosurat bilan 1:1 solishtirish uchun mo'ljallangan.

### 📖 Interaktiv Swagger Hujjatlari:
- **Lokal muhit:** [http://localhost:8080/docs](http://localhost:8080/docs) (yoki [http://localhost:8080/api/docs](http://localhost:8080/api/docs))
- **Production muhit:** `https://api.yourdomain.uz/docs`
- **OpenAPI 3.0 Spetsifikatsiyasi:** [http://localhost:8080/docs/openapi.yaml](http://localhost:8080/docs/openapi.yaml)

---

### 🔄 Ishlash Zanjiri (2 Qadam):

#### 1-Qadam: Bearer Access Token olish
Tashqi hamkor admin panel bergan `client_id` va `client_secret` orqali HTTP Basic Auth yordamida 24 soatlik Bearer Token oladi:
* **Endpoint:** `POST /api/v1/auth/token`
* **Avtorizatsiya:** `Authorization: Basic base64(client_id:client_secret)`

```bash
curl -X POST http://localhost:8080/api/v1/auth/token \
  -u "client_id:client_secret" \
  -H "Accept: application/json"
```

**Muvaffaqiyatli Javob (200 OK):**
```json
{
  "status": "success",
  "access_token": "1|abcdef123456...",
  "token_type": "Bearer",
  "expires_in": 86400
}
```

#### 2-Qadam: Biometrik Verifikatsiya (1:1 Solishtirish)
Olingan Bearer token bilan fuqaroning pasport seriya/raqami (`passport`), tug'ilgan sanasi (`birth_date`) va jonli fotosurati (`photo` Base64) yuboriladi:
* **Endpoint:** `POST /api/v1/external/verify`
* **Avtorizatsiya:** `Authorization: Bearer <TOKEN>`

```bash
curl -X POST http://localhost:8080/api/v1/external/verify \
  -H "Authorization: Bearer 1|abcdef123456..." \
  -H "Content-Type: application/json" \
  -d '{
    "passport": "AA1234567",
    "birth_date": "1995-08-15",
    "photo": "data:image/jpeg;base64,/9j/4AAQSkZJRg..."
  }'
```

**Muvaffaqiyatli Javob (200 OK — Shaxs tasdiqlandi):**
```json
{
  "status": "success",
  "is_match": true,
  "confidence": 0.982,
  "similarity": 0.98,
  "distance": 0.18,
  "threshold": 0.4,
  "person": {
    "full_name": "ISMOILOV ANVAR BOTIROVICH",
    "pinfl": "31508950000000",
    "passport": "AA1234567",
    "birth_date": "1995-08-15"
  },
  "message": "Fuqaro shaxsi muvaffaqiyatli tasdiqlandi"
}
```

Agar yuz mos kelmasa:
```json
{
  "status": "fail",
  "is_match": false,
  "confidence": 0.312,
  "distance": 0.69,
  "threshold": 0.4,
  "message": "Fuqaroning yuzi pasportdagi fotosuratga mos kelmadi"
}
```

---

## ⚙️ Davlat e-Gov GCP Integratsiya Sozlamalari (.env)

Tizim davlat GCP shlyuziga avtomatlashtirilgan tarzda ulanadi:
```env
EGOV_PASSPORT_BASE_URL="https://gateway.imv.uz/api/v1"
EGOV_AUTH_ENDPOINT="/auth/token"
EGOV_PASSPORT_ENDPOINT="/egov/passport-gcp-document-birthdate"
EGOV_AUTH_USERNAME="your_gateway_username"
EGOV_AUTH_PASSWORD="your_gateway_password"
```

---

## 🛡️ Admin Panel (MoonShine)

Admin boshqaruv paneli:
* URL: `https://api.yourdomain.uz/admin`
* Boshlang'ich login: `admin@faceidia.uz`
* Boshlang'ich parol: `FaceID_Admin_2026!` *(Productionda darhol o'zgartiring)*

Admin quyidagi imkoniyatlarga ega:
- Tashqi API so'rovlari va biometrik tekshiruvlar loglarini kuzatish.
- Muvaffaqiyatsiz (`FAILED`) so'rovlarni alohida filtrlab sabablarini ko'rish (masalan, pasport topilmadi, yuz mos kelmadi, xato token).
- Biometrik fotosuratlarni xavfsiz (fayl yo'lini oshkor qilmagan holda) ko'rish.
- Anti-spoofing hujumlari va tizim xavfsizlik jurnali.
- To'g'ridan-to'g'ri interaktiv Swagger API hujjatlariga o'tish.

---

## 💾 Zaxira Nusxalash (Backups)

Avtomatlashtirilgan zaxiralash skripti:
```bash
./backup.sh
```
Ushbu buyruq PostgreSQL bazasini (`.sql.gz`) va biometrik suratlar arxivini (`.tar.gz`) `backups/` papkasiga saqlaydi hamda 7 kundan eski nusxalarni avtomatik o'chiradi (Cron ga qo'shish tavsiya etiladi: `0 3 * * * /path/to/backup.sh`).
