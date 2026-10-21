<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\BiometricAuditLog;
use App\Services\FaceEmbeddingService;
use App\Services\BiometricVerificationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class FaceAuthController extends Controller
{
    public function __construct(
        protected FaceEmbeddingService $embeddingService
    ) {}

    public function recognize(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|max:10240',
        ]);

        try {
            $file = $request->file('file');
            $embedding = $this->embeddingService->getEmbedding($file);
            $vectorStr = $this->embeddingService->formatVector($embedding);
            $threshold = 0.4;

            $approved = DB::selectOne("
                SELECT id, full_name, department, phone_number, photo_url, (face_embedding <=> ?::vector) as distance
                FROM persons
                WHERE status = 'approved' AND (face_embedding <=> ?::vector) < ?
                ORDER BY face_embedding <=> ?::vector
                LIMIT 1
            ", [$vectorStr, $vectorStr, $threshold, $vectorStr]);

            if ($approved) {
                $personModel = Person::find($approved->id);
                $token = $personModel ? $personModel->createToken('biometric-session')->plainTextToken : null;

                BiometricAuditLog::create([
                    'person_id' => $approved->id,
                    'person_name' => $approved->full_name,
                    'action' => 'login_success',
                    'status' => 'success',
                    'similarity_distance' => (float)$approved->distance,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'details' => "Muvaffaqiyatli biometrik autentifikatsiya. Yangi sessiya tokeni berildi.",
                ]);

                return response()->json([
                    'status' => 'success',
                    'match' => true,
                    'reason' => 'SUCCESS',
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'person' => [
                        'id' => $approved->id,
                        'full_name' => $approved->full_name,
                        'department' => $approved->department,
                        'phone_number' => $approved->phone_number,
                        'photo_url' => $approved->photo_url,
                        'distance' => (float)$approved->distance,
                    ],
                ]);
            }

            $pending = DB::selectOne("
                SELECT id, full_name, (face_embedding <=> ?::vector) as distance
                FROM persons
                WHERE status = 'pending' AND (face_embedding <=> ?::vector) < ?
                ORDER BY face_embedding <=> ?::vector
                LIMIT 1
            ", [$vectorStr, $vectorStr, $threshold, $vectorStr]);

            if ($pending) {
                BiometricAuditLog::create([
                    'person_id' => $pending->id,
                    'person_name' => $pending->full_name,
                    'action' => 'login_pending',
                    'status' => 'warning',
                    'similarity_distance' => (float)$pending->distance,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'details' => "Tasdiqlanmagan (pending) ariza orqali ilovaga kirishga urinish.",
                ]);

                return response()->json([
                    'status' => 'pending',
                    'match' => false,
                    'reason' => 'PENDING_APPROVAL',
                    'message' => "Hurmatli {$pending->full_name}, sizning arizangiz hali admin tomonidan tasdiqlanmagan.",
                    'detail' => "Arizangiz tasdiqlanishi kutilmoqda.",
                ]);
            }

            BiometricAuditLog::create([
                'person_id' => null,
                'person_name' => null,
                'action' => 'login_not_found',
                'status' => 'danger',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'details' => "Noma'lum biometrik yuz orqali kirishga urinish (Bazadan topilmadi).",
            ]);

            return response()->json([
                'status' => 'not_found',
                'match' => false,
                'reason' => 'FACE_NOT_MATCHED',
                'message' => 'Mos keluvchi shaxs topilmadi.',
                'detail' => 'Shaxs tizim bazasidan topilmadi. Qaytadan urinib ko\'ring yoki ro\'yxatdan o\'ting.',
            ]);

        } catch (BiometricVerificationException $e) {
            return response()->json([
                'status' => 'error',
                'match' => false,
                'reason' => $e->getReason(),
                'message' => $e->getMessage(),
                'detail' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'match' => false,
                'reason' => 'SYSTEM_ERROR',
                'message' => $e->getMessage(),
                'detail' => $e->getMessage(),
            ], 400);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\Person $person */
        $person = $request->user();

        if ($person && method_exists($person, 'currentAccessToken') && $person->currentAccessToken()) {
            $person->currentAccessToken()->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sessiya muvaffaqiyatli yakunlandi.',
        ]);
    }
}

