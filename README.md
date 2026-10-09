# Enterprise Face ID System

![Python](https://img.shields.io/badge/Python-3.10-blue.svg)
![FastAPI](https://img.shields.io/badge/FastAPI-0.100+-green.svg)
![Vue.js](https://img.shields.io/badge/Vue.js-3.0-brightgreen.svg)
![Docker](https://img.shields.io/badge/Docker-Ready-blue.svg)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-pgvector-informational.svg)

## Loyiha Tavsifi (Project Overview)
**Enterprise Face ID System** — bu davlat tashkilotlari, yirik korporatsiyalar va yuqori xavfsizlik talab etiladigan ob'ektlar uchun mo'ljallangan markazlashtirilgan biometrik identifikatsiya tizimi. Tizim an'anaviy yuzni aniqlash arxitekturalaridagi tarmoq va server yuklamasini bartaraf etuvchi innovatsion **Edge-to-Cloud** arxitekturasiga asoslangan.

Ushbu yechim yuz biometrikasini piksellar asosida emas, balki chuqur neyron tarmoqlar (InsightFace) orqali yasalgan **512-o'lchamli vektorlar** asosida saqlaydi va qidiradi.

## Asosiy Imkoniyatlar (Core Features)
- **Kontaktsiz Identifikatsiya:** Karta yoki barmoq izisiz, soniyaning o'ndan bir qismida avtomatik o'tkazish imkoniyati.
- **Client-Side Face Tracking:** Kameralardan videoni serverga yubormasdan, harakatni foydalanuvchi uskunasida (brauzerda) qayta ishlash orqali internet-trafikni 99% tejash.
- **Vektorli Qidiruv (HNSW):** `pgvector` kengaytmasi orqali 1 million foydalanuvchi ma'lumotlar bazasidan aynan bitta insonni 1-5 millisekund ichida izlab topish.
- **Adaptive Execution:** Kod o'rnatilgan serverning quvvatini o'zi aniqlab, yuklamani avtomatik ravishda Protsessor (CPU) yoki Videokarta (CUDA GPU) dvigatellariga taqsimlaydi.

## Texnologik Stek (Tech Stack)
* **Frontend:** Vue.js 3 (Composition API), Vite, Google MediaPipe (Face Detection).
* **Backend:** Python 3.10, FastAPI, Uvicorn (Asinxron ASGI).
* **Sun'iy Intellekt (AI):** InsightFace (`buffalo_sc` State-of-the-Art modeli), ONNX Runtime.
* **Ma'lumotlar Bazasi:** PostgreSQL 15+, pgvector (Metrik izlash uchun).
* **Infratuzilma:** Docker, Docker Compose, Nginx.

## O'rnatish va Ishga Tushirish (Quick Start)

Loyihani o'z serveringizda yoki lokal muhitda ishga tushirish uchun kompyuteringizda **Docker** va **Docker Compose** o'rnatilgan bo'lishi talab etiladi.

1. Repozitoriyni klonlash:
```bash
git clone https://github.com/davronee/faceIDIA.git
cd faceIDIA
```

2. Konteynerlarni yig'ish va ishga tushirish:
```bash
docker-compose up -d --build
```

3. Tizimdan foydalanish:
- **Frontend (Kiyosk interfeysi):** `http://localhost:80` yoki serverning asosiy IP manzili.
- **Backend API (Swagger Docs):** `http://localhost:8000/docs`

## Masshtablash va Resurs Sarfi (Scalability & Resource Usage)

Tizim davlat standartlaridagi yirik oqimlar (High-Load) uchun optimallashtirilgan:

### 1. Ma'lumotlar Bazasi (Disk xotira) Sarfi
Bitta xodim bazaga kiritilganda uning yuzi rasm emas, raqamlar to'plami sifatida saqlanadi.
* 1 ta foydalanuvchi: ~2.1 KB
* 10,000 ta foydalanuvchi: ~20 MB
* 1,000,000 ta foydalanuvchi: ~2 GB 

### 2. Parallel Qayta Ishlash (Concurrency)
* **Idle (Bo'sh) Holat:** Barcha xizmatlar (AI + DB + Web) birgalikda atigi **~250 MB RAM** sarflaydi.
* **Kichik Oqimlar (oddiy CPU da):** 1 soniya ichida 10-15 xodim parallel identifikatsiyadan o'ta oladi.
* **Katta Oqimlar (Nvidia GPU Batch Processing da):** 1 soniya ichida kelib tushgan **1,000 ta parallel so'rov** guruhlarga bo'linib, to'liq **0.5 - 1 soniya** oralig'ida qayta ishlanadi va server qulashining oldi olinadi.

---
*Ushbu loyiha maxfiylik hamda zamonaviy dasturiy ta'minot arxitekturasi tamoyillari (Microservices, Edge AI, Vector Databases) asosida ishlab chiqilgan.*
