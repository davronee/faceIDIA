<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\BiometricAuditLog;
use App\Models\EgovPassportLog;
use App\Services\EgovPassportService;
use App\Services\FaceEmbeddingService;
use App\Services\BiometricVerificationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class EgovPassportController extends Controller
{
    public function __construct(
        protected EgovPassportService $egovService,
        protected FaceEmbeddingService $embeddingService
    ) {}

    /**
     * 1-qadam: Pasport seriya raqami va tug'ilgan sana bo'yicha davlat shlyuzidan fuqaroni qidirish.
     */
    public function fetchPassport(Request $request): JsonResponse
    {
        if ($request->has('birth_date') && !$request->has('birthDate')) {
            $request->merge(['birthDate' => $request->input('birth_date')]);
        }

        $validated = $request->validate([
            'document' => 'required|string|min:7|max:15',
            'birthDate' => 'required|date_format:Y-m-d',
        ]);

        try {
            $passportData = $this->egovService->fetchPassportData(
                $validated['document'],
                $validated['birthDate'],
                true
            );

            $fullName = trim(implode(' ', array_filter([
                $passportData['surnameLatin'] ?? '',
                $passportData['nameLatin'] ?? '',
                $passportData['patronymLatin'] ?? '',
            ])));

            if (empty($fullName)) {
                $fullName = "Fuqaro ({$validated['document']})";
            }

            // eGov javobini audit uchun bazaga (egov_passport_logs) saqlash
            try {
                EgovPassportLog::create([
                    'document' => $validated['document'],
                    'birth_date' => $validated['birthDate'],
                    'pinfl' => $passportData['pinfl'] ?? null,
                    'full_name' => $fullName,
                    'status' => 'success',
                    'raw_response' => $passportData,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent() ?? 'Flutter Mobile',
                    'created_at' => now(),
                ]);
            } catch (Exception $logEx) {
                Log::warning("[eGov Log Error] egov_passport_logs ga yozishda xatolik: " . $logEx->getMessage());
            }

            // Sessiyani Redis-da 15 daqiqaga saqlash
            $sessionId = (string) Str::uuid();
            Cache::put("egov_session:{$sessionId}", [
                'document' => $validated['document'],
                'birthDate' => $validated['birthDate'],
                'fullName' => $fullName,
                'pinfl' => $passportData['pinfl'] ?? null,
                'photo' => $passportData['photo'] ?? null,
                'data' => $passportData,
            ], 900);

            return response()->json([
                'status' => 'success',
                'session_id' => $sessionId,
                'pinfl' => $passportData['pinfl'] ?? null,
                'document' => $validated['document'],
                'birth_date' => $validated['birthDate'],
                'full_name' => $fullName,
                'surname' => $passportData['surnameLatin'] ?? null,
                'name' => $passportData['nameLatin'] ?? null,
                'patronym' => $passportData['patronymLatin'] ?? null,
                'photo' => $passportData['photo'] ?? null,
                'doc_give_place' => $passportData['docGivePlace'] ?? null,
                'message' => "Pasport ma'lumotlari muvaffaqiyatli tasdiqlandi. Endi shaxsingizni tasdiqlash uchun Face ID kamerasi ochiladi.",
            ]);

        } catch (Exception $e) {
            try {
                EgovPassportLog::create([
                    'document' => $validated['document'] ?? 'UNKNOWN',
                    'birth_date' => $validated['birthDate'] ?? now()->toDateString(),
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent() ?? 'Flutter Mobile',
                    'created_at' => now(),
                ]);
            } catch (Exception $logEx) {
                // Ignore log save failure on already failed request
            try {
                BiometricAuditLog::create([
                    'person_name' => $validated['document'] ?? 'Noma\'lum fuqaro',
                    'action' => 'egov_passport_failed',
                    'status' => 'failed',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent() ?? 'Flutter Mobile',
                    'details' => "eGov Pasport qidirishda xatolik: " . $e->getMessage(),
                ]);
            } catch (Exception $logEx) {
                // Ignore log save failure
            }

            return response()->json([
                'status' => 'error',
                'reason' => 'PASSPORT_LOOKUP_FAILED',
                'message' => $e->getMessage(),
                'detail' => $e->getMessage(),
            ], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400);
        }
    }

    /**
     * 2-qadam: Kameradan olingan jonli yuzni pasportdagi rasm bilan 1:1 solishtirish va tizimga kirish.
     *
     * Rasm manbasi 3 xil usulda qabul qilinadi:
     * 1) session_id (1-bosqich /api/egov/passport-verify dan qaytgan vaqtinchalik ID)
     * 2) document + birthDate (shlyuzdan to'g'ridan-to'g'ri qidirib solishtirish)
     * 3) passport_photo (to'g'ridan-to'g'ri pasportning base64 formati)
     */
    public function verifyFaceAndLogin(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|max:10240', // max 10MB kamera kadri
            'session_id' => 'nullable|string',
            'passport_photo' => 'nullable|string',
            'document' => 'nullable|string|min:7|max:15',
            'birthDate' => 'nullable|date_format:Y-m-d',
            'birth_date' => 'nullable|date_format:Y-m-d',
            'phone_number' => 'nullable|string|max:50',
        ]);

        try {
            $passportBase64 = null;
            $cachedSession = null;
            $sessionId = $request->input('session_id');

            // 1. session_id orqali keshdan olish
            if (!empty($sessionId)) {
                $cachedSession = Cache::get("egov_session:{$sessionId}");
                if ($cachedSession && !empty($cachedSession['photo'])) {
                    $passportBase64 = $cachedSession['photo'];
                }
            }

            // 2. Agar session_id bo'lmasa, lekin document va birthDate berilgan bo'lsa, shlyuzdan olish
            if (empty($passportBase64) && $request->filled('document') && ($request->filled('birthDate') || $request->filled('birth_date'))) {
                $doc = (string) $request->input('document');
                $bDate = (string) ($request->input('birthDate') ?? $request->input('birth_date'));
                try {
                    $passportData = $this->egovService->fetchPassportData($doc, $bDate, true);
                    if (!empty($passportData['photo'])) {
                        $passportBase64 = $passportData['photo'];
                        $fullName = trim(implode(' ', array_filter([
                            $passportData['surnameLatin'] ?? '',
                            $passportData['nameLatin'] ?? '',
                            $passportData['patronymLatin'] ?? '',
                        ])));
                        $cachedSession = [
                            'document' => $doc,
                            'birthDate' => $bDate,
                            'fullName' => $fullName,
                            'pinfl' => $passportData['pinfl'] ?? null,
                            'photo' => $passportBase64,
                            'data' => $passportData,
                        ];
                    }
                } catch (Exception $egovEx) {
                    Log::warning("[Face Match] Shlyuzdan pasport olishda xatolik: " . $egovEx->getMessage());
                }
            }

            // 3. To'g'ridan-to'g'ri yuborilgan base64 rasm
            if (empty($passportBase64)) {
                $passportBase64 = $request->input('passport_photo');
            }

            if (empty($passportBase64)) {
                return response()->json([
                    'status' => 'error',
                    'reason' => 'SESSION_EXPIRED',
                    'message' => "Pasport ma'lumotlari topilmadi. session_id eskirgan yoki pasport seriyasi/rasmi kiritilmagan. Iltimos, pasport ma'lumotlarini qayta kiriting.",
                ], 400);
            }

            // Jonli fotosuratni pasport fotosurati bilan 1:1 solishtirish
            $file = $request->file('file');
            $matchResult = $this->egovService->compareFaceWithPassport($file, $passportBase64);

            $isMatch = $matchResult['is_match'] ?? false;
            $distance = (float)($matchResult['similarity_distance'] ?? 1.0);
            $similarityPercent = (float)($matchResult['similarity_percentage'] ?? 0.0);

            $fullName = $cachedSession['fullName'] ?? "eGov Fuqaro";
            $pinfl = $cachedSession['pinfl'] ?? null;
            $phone = $request->input('phone_number');

            if (!$isMatch) {
                BiometricAuditLog::create([
                    'person_name' => $fullName,
                    'action' => 'egov_face_mismatch',
                    'status' => 'warning',
                    'similarity_distance' => $distance,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent() ?? 'Flutter Mobile eGov',
                    'details' => "Kameradagi yuz pasportdagi suratga mos kelmadi (O'xshashlik: {$similarityPercent}%).",
                ]);

                return response()->json([
                    'status' => 'error',
                    'is_match' => false,
                    'reason' => 'FACE_MISMATCH',
                    'message' => "Kameradagi yuz pasportdagi fotosuratga mos kelmadi.",
                    'detail' => "Biometrik o'xshashlik yetarli emas ({$similarityPercent}%). Iltimos, haqiqiy pasport egasi sifatida kameraga to'g'ri qarang.",
                    'similarity_percentage' => $similarityPercent,
                ], 400);
            }

            // Yuz mos keldi! Jonli yuzning 512-d vektorini olish
            $embedding = $this->embeddingService->getEmbedding($file, false);
            $vectorStr = $this->embeddingService->formatVector($embedding);

            // Jonli selfi rasmni xavfsiz saqlash
            $filename = Str::uuid()->toString() . '.jpg';
            $file->storeAs('biometrics', $filename, 'local');

            // e-Gov pasport rasmini (base64) diskka xavfsiz saqlash
            $passportPhotoFilename = null;
            if (!empty($passportBase64)) {
                try {
                    $cleanB64 = trim($passportBase64);
                    if (str_contains($cleanB64, ',')) {
                        $cleanB64 = explode(',', $cleanB64)[1];
                    }
                    $passportBytes = base64_decode($cleanB64);
                    if ($passportBytes) {
                        $passportPhotoFilename = 'passport_' . Str::uuid()->toString() . '.jpg';
                        Storage::disk('local')->put("biometrics/{$passportPhotoFilename}", $passportBytes);
                    }
                } catch (Exception $photoEx) {
                    Log::warning("[eGov Photo Save Warning] Pasport fotosuratini saqlashda ogohlantirish: " . $photoEx->getMessage());
                }
            }

            // eGov javobidagi to'liq ma'lumotlarni olish
            $rawEgov = $cachedSession['data'] ?? [];
            $docSeria = $rawEgov['docSeria'] ?? null;
            $docNumber = $rawEgov['docNumber'] ?? null;
            $pinfl = $rawEgov['pinfl'] ?? ($cachedSession['pinfl'] ?? $pinfl);
            $birthDate = $rawEgov['birthDate'] ?? ($cachedSession['birthDate'] ?? null);

            // Bazada shaxsni topish (JSHSHIR, Pasport raqami yoki Telefon bo'yicha)
            $person = null;
            if (!empty($pinfl)) {
                $person = Person::where('pinfl', $pinfl)->first() ?? Person::where('phone_number', $pinfl)->first();
            }
            if (!$person && !empty($docSeria) && !empty($docNumber)) {
                $person = Person::where('doc_seria', $docSeria)->where('doc_number', $docNumber)->first();
            }
            if (!$person && !empty($phone)) {
                $person = Person::where('phone_number', $phone)->first();
            }

            $personPayload = [
                'full_name' => $fullName,
                'pinfl' => $pinfl,
                'doc_seria' => $docSeria,
                'doc_number' => $docNumber,
                'surname_latin' => $rawEgov['surnameLatin'] ?? null,
                'name_latin' => $rawEgov['nameLatin'] ?? null,
                'patronym_latin' => $rawEgov['patronymLatin'] ?? null,
                'birth_date' => $birthDate,
                'birth_place' => $rawEgov['birthPlace'] ?? null,
                'birth_country' => $rawEgov['birthCountry'] ?? null,
                'nationality' => $rawEgov['nationality'] ?? null,
                'citizenship' => $rawEgov['citizenship'] ?? null,
                'gender' => isset($rawEgov['sex']) ? (int)$rawEgov['sex'] : null,
                'doc_give_place' => $rawEgov['docGivePlace'] ?? null,
                'doc_date_begin' => $rawEgov['docDateBegin'] ?? null,
                'doc_date_end' => $rawEgov['docDateEnd'] ?? null,
                'phone_number' => $phone ?? $pinfl ?? (!empty($docSeria) && !empty($docNumber) ? "{$docSeria}{$docNumber}" : ('DOC-' . Str::random(8))),
                'department' => $rawEgov['docGivePlace'] ?? 'Davlat e-Gov Biometrik Tizimi',
                'status' => 'approved',
                'photo_url' => $filename,
                'passport_photo_url' => $passportPhotoFilename,
                'face_embedding' => DB::raw("'$vectorStr'::vector"),
                'egov_data' => !empty($rawEgov) ? json_encode($rawEgov) : null,
                'updated_at' => now(),
            ];

            if ($person) {
                $person->update($personPayload);
            } else {
                $personPayload['created_at'] = now();
                $id = DB::table('persons')->insertGetId($personPayload);
                $person = Person::find($id);
            }

            // Sessiyani tozalash
            if ($sessionId) {
                Cache::forget("egov_session:{$sessionId}");
            }

            // Sanctum Bearer token generatsiya qilish
            $token = $person ? $person->createToken('egov-biometric-session')->plainTextToken : null;

            BiometricAuditLog::create([
                'person_id' => $person?->id,
                'person_name' => $fullName,
                'action' => 'egov_login_success',
                'status' => 'success',
                'similarity_distance' => $distance,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent() ?? 'Flutter Mobile eGov',
                'details' => "Davlat pasporti (GCP) orqali biometrik Face ID tasdiqlandi. Tizimga muvaffaqiyatli kirildi.",
            ]);

            return response()->json([
                'status' => 'success',
                'is_match' => true,
                'similarity_distance' => round($distance, 4),
                'similarity_percentage' => $similarityPercent,
                'token' => $token,
                'token_type' => 'Bearer',
                'person' => [
                    'id' => $person?->id,
                    'full_name' => $person?->full_name,
                    'phone_number' => $person?->phone_number,
                    'department' => $person?->department,
                    'status' => $person?->status,
                    'photo_url' => $person?->photo_url,
                ],
                'message' => "Shaxsingiz muvaffaqiyatli tasdiqlandi. Tizimga xush kelibsiz!",
            ]);

        } catch (BiometricVerificationException $e) {
            try {
                BiometricAuditLog::create([
                    'person_name' => $fullName ?? ($cachedSession['fullName'] ?? 'Noma\'lum fuqaro'),
                    'action' => 'egov_face_mismatch',
                    'status' => 'warning',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent() ?? 'Flutter Mobile',
                    'details' => "eGov Yuz mos kelmadi yoki biometrik ogohlantirish ({$e->getReason()}): " . $e->getMessage(),
                ]);
            } catch (Exception $logEx) {}

            return response()->json([
                'status' => 'error',
                'is_match' => false,
                'reason' => $e->getReason(),
                'message' => $e->getMessage(),
                'detail' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            Log::error("[VerifyFaceAndLogin Exception]: " . $e->getMessage());
            try {
                BiometricAuditLog::create([
                    'person_name' => $fullName ?? ($cachedSession['fullName'] ?? 'Noma\'lum fuqaro'),
                    'action' => 'egov_face_mismatch',
                    'status' => 'failed',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent() ?? 'Flutter Mobile',
                    'details' => "eGov Face tizim xatosi: " . $e->getMessage(),
                ]);
            } catch (Exception $logEx) {}

            return response()->json([
                'status' => 'error',
                'is_match' => false,
                'reason' => 'SYSTEM_ERROR',
                'message' => "Tizimda xatolik yuz berdi.",
                'detail' => $e->getMessage(),
            ], 500);
        }
    }
}
