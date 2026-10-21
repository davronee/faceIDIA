<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BiometricAuditLog;
use App\Services\BiometricVerificationException;
use App\Services\EgovPassportService;
use App\Services\FaceEmbeddingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class ExternalApiController extends Controller
{
    public function __construct(
        protected FaceEmbeddingService $embeddingService,
        protected EgovPassportService $egovService
    ) {}

    /**
     * Yagona Tashqi B2B Biometrik API:
     * Pasport seriyasi, tug'ilgan sana va jonli fotosurat (Base64) qabul qiladi.
     * Davlat eGov bazasidagi pasport rasmi bilan jonli Base64 rasmni 1:1 taqqoslaydi.
     * Agar shaxs tasdiqlansa: is_match: true qaytaradi.
     */
    public function verifyFaceWithPassport(Request $request): JsonResponse
    {
        // 1. Kiruvchi parametrlarni olish va tekshirish
        $document = $request->input('passport') ?? $request->input('document');
        $birthDate = $request->input('birth_date') ?? $request->input('birthDate');
        $photoBase64 = $request->input('photo') ?? $request->input('image') ?? $request->input('photo_base64');

        if (empty($document)) {
            $this->recordAuditLog(null, 'api_verify_failed', 'failed', null, $request, "Validatsiya xatosi: Pasport seriya va raqami kiritilmagan.");
            return response()->json([
                'status' => 'error',
                'is_match' => false,
                'reason' => 'VALIDATION_ERROR',
                'message' => "Pasport seriyasi va raqami kiritilishi shart ('passport' maydoni, masalan: AA1234567).",
            ], 422);
        }

        if (empty($birthDate)) {
            $this->recordAuditLog((string)$document, 'api_verify_failed', 'failed', null, $request, "Validatsiya xatosi: Tug'ilgan sana kiritilmagan.");
            return response()->json([
                'status' => 'error',
                'is_match' => false,
                'reason' => 'VALIDATION_ERROR',
                'message' => "Tug'ilgan sana kiritilishi shart ('birth_date' maydoni, formati: YYYY-MM-DD).",
            ], 422);
        }

        $tmpFile = null;
        $liveFile = null;

        if ($request->hasFile('file') || $request->hasFile('photo')) {
            $liveFile = $request->file('file') ?? $request->file('photo');
        } elseif (!empty($photoBase64)) {
            try {
                $liveFile = $this->decodeBase64ToUploadedFile((string)$photoBase64, $tmpFile);
            } catch (Exception $e) {
                $this->recordAuditLog((string)$document, 'api_verify_failed', 'failed', null, $request, "Base64 xatosi: " . $e->getMessage());
                return response()->json([
                    'status' => 'error',
                    'is_match' => false,
                    'reason' => 'INVALID_BASE64',
                    'message' => $e->getMessage(),
                ], 400);
            }
        } else {
            $this->recordAuditLog((string)$document, 'api_verify_failed', 'failed', null, $request, "Validatsiya xatosi: Jonli yuz fotosurati kiritilmagan.");
            return response()->json([
                'status' => 'error',
                'is_match' => false,
                'reason' => 'VALIDATION_ERROR',
                'message' => "Jonli yuz fotosurati kiritilishi shart ('photo' maydonida Base64 formatda).",
            ], 422);
        }

        $fullName = "Fuqaro (" . strtoupper(trim((string)$document)) . ")";

        try {
            $cleanDocument = strtoupper(trim((string)$document));
            $cleanBirthDate = trim((string)$birthDate);

            // 2. Davlat eGov GCP shlyuzidan fuqaroning rasmiy pasport ma'lumotlari va rasmini olish
            $passportData = $this->egovService->fetchPassportData($cleanDocument, $cleanBirthDate, true);

            $passportPhotoBase64 = $passportData['photo'] ?? null;
            if (empty($passportPhotoBase64)) {
                $this->recordAuditLog($fullName, 'api_verify_failed', 'failed', null, $request, "Davlat pasport bazasida ushbu fuqaroning fotosurati topilmadi ({$cleanDocument}).");
                return response()->json([
                    'status' => 'error',
                    'is_match' => false,
                    'reason' => 'PASSPORT_PHOTO_NOT_FOUND',
                    'message' => "Davlat pasport bazasida ushbu fuqaroning fotosurati topilmadi.",
                ], 404);
            }

            $nameFormatted = trim(implode(' ', array_filter([
                $passportData['surnameLatin'] ?? '',
                $passportData['nameLatin'] ?? '',
                $passportData['patronymLatin'] ?? '',
            ])));
            if (!empty($nameFormatted)) {
                $fullName = $nameFormatted;
            }

            // 3. Jonli Base64 suratni pasportdagi surat bilan 1:1 solishtirish (ArcFace 512-d + Anti-spoofing)
            $matchResult = $this->egovService->compareFaceWithPassport($liveFile, $passportPhotoBase64);

            $isMatch = (bool)($matchResult['is_match'] ?? false);
            $distance = (float)($matchResult['similarity_distance'] ?? 1.0);
            $similarityPercent = (float)($matchResult['similarity_percentage'] ?? 0.0);

            // Audit jurnali yozish (Muvaffaqiyatli yoki Yuz mos kelmagan)
            $this->recordAuditLog(
                $fullName,
                'external_passport_face_verify',
                $isMatch ? 'success' : 'warning',
                $distance,
                $request,
                "Tashqi B2B pasport va jonli yuz solishtiruvi. Moslik: " . ($isMatch ? 'HA (true)' : "YO'Q (false)") . " ({$similarityPercent}%). Masofa: " . round($distance, 4)
            );

            if (!$isMatch) {
                return response()->json([
                    'status' => 'error',
                    'is_match' => false,
                    'similarity_percentage' => $similarityPercent,
                    'similarity_distance' => round($distance, 4),
                    'reason' => 'FACE_MISMATCH',
                    'message' => "Jonli rasm pasportdagi fotosuratga mos kelmadi.",
                    'detail' => "Biometrik o'xshashlik yetarli emas ({$similarityPercent}%).",
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'is_match' => true,
                'similarity_percentage' => $similarityPercent,
                'similarity_distance' => round($distance, 4),
                'message' => "Shaxs muvaffaqiyatli tasdiqlandi. Jonli yuz pasport fotosuratiga to'liq mos keldi.",
                'data' => [
                    'full_name' => $fullName,
                    'pinfl' => $passportData['pinfl'] ?? null,
                    'document' => $cleanDocument,
                    'birth_date' => $cleanBirthDate,
                    'doc_give_place' => $passportData['docGivePlace'] ?? null,
                ]
            ]);

        } catch (BiometricVerificationException $bioEx) {
            $this->recordAuditLog(
                $fullName,
                'api_verify_failed',
                'danger',
                null,
                $request,
                "Biometrik xatolik ({$bioEx->getReason()}): " . $bioEx->getMessage()
            );

            return response()->json([
                'status' => 'error',
                'is_match' => false,
                'reason' => $bioEx->getReason(),
                'message' => $bioEx->getMessage(),
                'detail' => $bioEx->getMessage(),
            ], 400);
        } catch (Exception $e) {
            $this->recordAuditLog(
                $fullName,
                'api_verify_failed',
                'failed',
                null,
                $request,
                "Tashqi API tekshirishda xatolik: " . $e->getMessage()
            );

            return response()->json([
                'status' => 'error',
                'is_match' => false,
                'reason' => 'PASSPORT_VERIFY_FAILED',
                'message' => $e->getMessage(),
                'detail' => $e->getMessage(),
            ], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400);
        } finally {
            if ($tmpFile && file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }

    /**
     * Audit jurnaliga yozish yordamchisi.
     */
    protected function recordAuditLog(?string $personName, string $action, string $status, ?float $distance, Request $request, string $details): void
    {
        try {
            BiometricAuditLog::create([
                'person_name' => $personName ?? "Noma'lum fuqaro",
                'action' => $action,
                'status' => $status,
                'similarity_distance' => $distance,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent() ?? 'External B2B Client',
                'details' => $details,
            ]);
        } catch (Exception $ex) {
            Log::warning("[Audit Log Warning] " . $ex->getMessage());
        }
    }

    /**
     * Base64 satrni UploadedFile obyektiga aylantirish yordamchisi.
     */
    protected function decodeBase64ToUploadedFile(string $base64Data, ?string &$tmpFileOut = null): UploadedFile
    {
        // Agar 'data:image/...;base64,' prefiksi bo'lsa, uni tozalash
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data)) {
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
        }

        $decoded = base64_decode($base64Data, true);
        if ($decoded === false || empty($decoded)) {
            throw new Exception("Yuborilgan rasm Base64 formati noto'g'ri yoki buzilgan.");
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'ext_live_');
        file_put_contents($tmpFile, $decoded);
        $tmpFileOut = $tmpFile;

        return new UploadedFile($tmpFile, 'live_face.jpg', 'image/jpeg', null, true);
    }
}
