# FaceID Enterprise — Native Android (Kotlin) Integratsiya Qo'llanmasi

Ushbu hujjat **Android (Kotlin)** dasturchilari uchun FaceID Enterprise REST API tizimiga ulanish, biometrik yuzni tanish va ro'yxatdan o'tkazishni amalga oshirish bo'yicha to'liq texnik qo'llanmadir.

---

## 1. Asosiy Aloqa Parametrlari

* **Base URL (Local / Dev):** `http://<SERVER_IP>:8080/`
* **Base URL (Production):** `https://api.yourdomain.uz/`
* **Interaktiv Swagger UI (Browserda sinash):** `https://api.yourdomain.uz/docs`
* **OpenAPI 3.0 Spetsifikatsiya:** `https://api.yourdomain.uz/docs/openapi.yaml`

### Majburiy Sarlavhalar (HTTP Headers)

| Sarlavha (Header) | Qiymati | Qayerda ishlatiladi? |
| :--- | :--- | :--- |
| **`Accept`** | `application/json` | Barcha so'rovlarda |
| **`X-App-Key`** | Backend admini bergan maxfiy kalit (masalan, `faceidia_android_mobile_prod_key_77a912bf08`) | `/api/register` va `/api/recognize` |
| **`Authorization`** | `Bearer <SANCTUM_TOKEN>` | `/api/user/me`, `/api/user/photo`, `/api/user/logout` |

---

## 2. API Endpoints va Funksiyalar Xaritasi

### 2.1. Fuqaroni Ro'yxatdan O'tkazish (Registration)
* **Method & Path:** `POST /api/register`
* **Content-Type:** `multipart/form-data`
* **Headers:** `X-App-Key: <KEY>`, `Accept: application/json`
* **Parametrlar:**
  * `file`: Kamera/Galereyadan olingan surat (JPEG/PNG)
  * `full_name`: String (F.I.Sh)
  * `phone_number`: String (ixtiyoriy, masalan: `+998901234567`)
  * `department`: String (ixtiyoriy, masalan: `Moliya bo'limi`)

#### Muvaffaqiyatli Javob (HTTP 201 Created):
```json
{
  "status": "success",
  "message": "Arizangiz qabul qilindi. Admin tasdiqlashi kutilmoqda.",
  "id": 104,
  "approved": false,
  "photo_url": "uuid-v4-filename.jpg"
}
```

---

### 2.2. Biometrik Autentifikatsiya (Face Login / Recognize)
* **Method & Path:** `POST /api/recognize`
* **Content-Type:** `multipart/form-data`
* **Headers:** `X-App-Key: <KEY>`, `Accept: application/json`
* **Parametrlar:**
  * `file`: Jonli kamera kadri (JPEG/PNG)

#### Muvaffaqiyatli Kirish (HTTP 200 OK — Shaxs tasdiqlangan):
```json
{
  "status": "success",
  "match": true,
  "reason": "SUCCESS",
  "token": "1|3c90ab01...f32",
  "token_type": "Bearer",
  "person": {
    "id": 12,
    "full_name": "Aliyev Vali Valiyevich",
    "department": "Moliya boshqarmasi",
    "phone_number": "+998901234567",
    "photo_url": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d.jpg",
    "distance": 0.184
  }
}
```

#### Ariza Kutilmoqda (HTTP 200 OK — status: "pending"):
```json
{
  "status": "pending",
  "match": false,
  "reason": "PENDING_APPROVAL",
  "message": "Hurmatli Aliyev Vali Valiyevich, sizning arizangiz hali admin tomonidan tasdiqlanmagan.",
  "detail": "Arizangiz tasdiqlanishi kutilmoqda."
}
```

#### Shaxs Topilmadi (HTTP 200 OK — status: "not_found"):
```json
{
  "status": "not_found",
  "match": false,
  "reason": "FACE_NOT_MATCHED",
  "message": "Mos keluvchi shaxs topilmadi.",
  "detail": "Shaxs tizim bazasidan topilmadi. Qaytadan urinib ko'ring yoki ro'yxatdan o'ting."
}
```

---

### 2.3. Xatoliklar Formati va Kodlari (HTTP 400 Bad Request)
Server doim standart kontraktda xatolik qaytaradi:
```json
{
  "status": "error",
  "match": false,
  "reason": "SPOOF_DETECTED",
  "message": "Jonlilik tasdiqlanmadi (Ekrandan yozib olingan video). Iltimos, haqiqiy shaxs sifatida kameraga to'g'ri qarang.",
  "detail": "Jonlilik tasdiqlanmadi (Ekrandan yozib olingan video). Iltimos, haqiqiy shaxs sifatida kameraga to'g'ri qarang."
}
```

#### `reason` Kodlari Ro'yxati (Android UI dagi ishlov berish uchun):
1. **`SPOOF_DETECTED`** — Anti-spoofing himoyasi ishladi (foydalanuvchi telefon ekrani yoki qog'ozdagi rasmni kameraga tutgan). Foydalanuvchiga kameraga jonli qarashni ayting.
2. **`LOW_QUALITY_IMAGE`** — Surat xira, fokus yo'q yoki bosh burilgan (pitch/yaw/roll > 20°). Kamerani to'g'ri tutish so'raladi.
3. **`NO_FACE_DETECTED`** — Kadrda yuz umuman topilmadi.
4. **`DUPLICATE_FACE`** — Ro'yxatdan o'tishda ushbu yuz egasi allaqachon boshqa nom bilan ro'yxatdan o'tgan.
5. **`DUPLICATE_PHONE`** — Ro'yxatdan o'tishda ko'rsatilgan telefon raqam boshqa fuqaroga tegishli.
6. **`PENDING_APPROVAL`** — Ushbu yuz bo'yicha ariza allaqachon yuborilgan va adminga tushgan.
7. **`SYSTEM_ERROR`** — Ichki server xatoligi.

---

### 2.4. Avtorizatsiyalangan So'rovlar

* **Joriy Profil:** `GET /api/user/me`
  * Header: `Authorization: Bearer <token>`
* **Shaxsiy Surat (Stream):** `GET /api/user/photo`
  * Header: `Authorization: Bearer <token>`
  * Qaytaradi: JPEG rasm fayli (Glide yoki Coil kutubxonasi orqali to'g'ridan-to'g'ri ko'rsatiladi).
* **Tizimdan Chiqish:** `POST /api/user/logout`
  * Header: `Authorization: Bearer <token>`
  * Sanctum tokenini bazadan bekor qiladi.

---

## 3. Kotlin Namuna Koding (Retrofit2 + OkHttp)

### 3.1. Gradle Bog'liqliklari (`build.gradle.kts`)
```kotlin
dependencies {
    // Retrofit & OkHttp
    implementation("com.squareup.retrofit2:retrofit:2.11.0")
    implementation("com.squareup.retrofit2:converter-moshi:2.11.0")
    implementation("com.squareup.okhttp3:okhttp:4.12.0")
    implementation("com.squareup.okhttp3:logging-interceptor:4.12.0")
    
    // Moshi JSON
    implementation("com.squareup.moshi:moshi-kotlin:1.15.1")
    
    // Rasm yuklash (Glide yoki Coil)
    implementation("io.coil-kt:coil:2.6.0")
}
```

### 3.2. Kotlin Data Modellar (`FaceIdModels.kt`)
```kotlin
package uz.faceidia.app.data.models

import com.squareup.moshi.Json
import com.squareup.moshi.JsonClass

@JsonClass(generateAdapter = true)
data class RegisterResponse(
    @Json(name = "status") val status: String,
    @Json(name = "message") val message: String,
    @Json(name = "id") val id: Int?,
    @Json(name = "approved") val approved: Boolean?,
    @Json(name = "photo_url") val photoUrl: String?
)

@JsonClass(generateAdapter = true)
data class PersonDto(
    @Json(name = "id") val id: Int,
    @Json(name = "full_name") val fullName: String,
    @Json(name = "department") val department: String?,
    @Json(name = "phone_number") val phoneNumber: String?,
    @Json(name = "photo_url") val photoUrl: String?,
    @Json(name = "distance") val distance: Float?
)

@JsonClass(generateAdapter = true)
data class RecognizeResponse(
    @Json(name = "status") val status: String, // "success", "pending", "not_found", "error"
    @Json(name = "match") val match: Boolean,
    @Json(name = "reason") val reason: String?,
    @Json(name = "token") val token: String?,
    @Json(name = "token_type") val tokenType: String?,
    @Json(name = "message") val message: String?,
    @Json(name = "detail") val detail: String?,
    @Json(name = "person") val person: PersonDto?
)

@JsonClass(generateAdapter = true)
data class ApiErrorResponse(
    @Json(name = "status") val status: String?,
    @Json(name = "match") val match: Boolean?,
    @Json(name = "reason") val reason: String?,
    @Json(name = "message") val message: String?,
    @Json(name = "detail") val detail: String?
)
```

### 3.3. Retrofit Interfeysi (`FaceIdApiService.kt`)
```kotlin
package uz.faceidia.app.data.api

import okhttp3.MultipartBody
import okhttp3.RequestBody
import okhttp3.ResponseBody
import retrofit2.Response
import retrofit2.http.*
import uz.faceidia.app.data.models.PersonDto
import uz.faceidia.app.data.models.RecognizeResponse
import uz.faceidia.app.data.models.RegisterResponse

interface FaceIdApiService {

    @Multipart
    @POST("api/register")
    suspend fun register(
        @Part file: MultipartBody.Part,
        @Part("full_name") fullName: RequestBody,
        @Part("phone_number") phoneNumber: RequestBody?,
        @Part("department") department: RequestBody?
    ): Response<RegisterResponse>

    @Multipart
    @POST("api/recognize")
    suspend fun recognize(
        @Part file: MultipartBody.Part
    ): Response<RecognizeResponse>

    @GET("api/user/me")
    suspend fun getProfile(): Response<Map<String, Any>>

    @GET("api/user/photo")
    @Streaming
    suspend fun getUserPhoto(): Response<ResponseBody>

    @POST("api/user/logout")
    suspend fun logout(): Response<Map<String, String>>
}
```

### 3.4. OkHttp Interceptor (Headerlar va Tokenlarni avtomatik qo'shish)
```kotlin
package uz.faceidia.app.data.api

import okhttp3.Interceptor
import okhttp3.Response

class AuthHeaderInterceptor(
    private val appKey: String,
    private val tokenProvider: () -> String?
) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val original = chain.request()
        val requestBuilder = original.newBuilder()
            .header("Accept", "application/json")
            .header("X-App-Key", appKey)

        val token = tokenProvider()
        if (!token.isNullOrEmpty()) {
            requestBuilder.header("Authorization", "Bearer $token")
        }

        return chain.proceed(requestBuilder.build())
    }
}
```

### 3.5. Rasmni Optimallashtirish va Yuborish (Camera Helper)
Kameradan chiqqan 4K (12-20 MB) suratlarni to'g'ridan-to'g'ri yubormasdan, **1080p gacha qisqartirib (resize)** va JPEG sifatini **85%** qilib yuborish tarmoq uzatish tezligini 95% ga oshiradi:

```kotlin
fun createOptimizedImagePart(context: Context, imageUri: Uri): MultipartBody.Part {
    val inputStream = context.contentResolver.openInputStream(imageUri)
    val originalBitmap = BitmapFactory.decodeStream(inputStream)
    inputStream?.close()

    // 1080p dan oshmaslik uchun masshtablash
    val maxDim = 1080
    val width = originalBitmap.width
    val height = originalBitmap.height
    val scale = if (width > height) maxDim.toFloat() / width else maxDim.toFloat() / height
    
    val targetBitmap = if (scale < 1.0f) {
        Bitmap.createScaledBitmap(originalBitmap, (width * scale).toInt(), (height * scale).toInt(), true)
    } else {
        originalBitmap
    }

    val stream = ByteArrayOutputStream()
    targetBitmap.compress(Bitmap.CompressFormat.JPEG, 85, stream)
    val byteArray = stream.toByteArray()

    val requestFile = byteArray.toRequestBody("image/jpeg".toMediaTypeOrNull())
    return MultipartBody.Part.createFormData("file", "selfie_${System.currentTimeMillis()}.jpg", requestFile)
}
```

---

## 4. Xavfsizlik va Production Tavsiyalari

1. **SSL Pinning:** Production rejimida OkHttp `CertificatePinner` orqali `api.yourdomain.uz` domeningizning SSL sertifikat xeshini pin qiling (Man-in-the-Middle xujumlarini oldini oladi).
2. **Token saqlash:** `token` ni oddiy `SharedPreferences` ga emas, balki Android `EncryptedSharedPreferences` ga saqlang.
3. **Kamera Rotatsiyasi (EXIF):** Samsung va Xiaomi telefonlarida selfi olinganda tasvir burilib qolishi mumkin. Bitmapni uzatishdan oldin EXIF orientatsiyasini tekshirib, to'g'ri holatda (0°) yuborayotganingizga ishonch hosil qiling.
