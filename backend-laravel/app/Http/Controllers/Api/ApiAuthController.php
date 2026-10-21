<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiAuthController extends Controller
{
    /**
     * Client Credentials orqali Bearer Access Token berish.
     * 
     * Mijoz quyidagi 2 xil usulda autentifikatsiyadan o'tishi mumkin:
     * 1. HTTP Basic Auth: Authorization: Basic base64(client_id:client_secret)
     * 2. JSON / Form Body: { "client_id": "...", "client_secret": "..." }
     */
    public function issueToken(Request $request): JsonResponse
    {
        // 1. Basic Auth dan ma'lumotlarni olish
        $clientId = $request->getUser();
        $clientSecret = $request->getPassword();

        // 2. Agar Basic Auth bo'lmasa, Request Body dan tekshirish
        if (empty($clientId)) {
            $clientId = $request->input('client_id');
            $clientSecret = $request->input('client_secret');
        }

        if (empty($clientId) || empty($clientSecret)) {
            $this->recordAudit('Noma\'lum', 'api_auth_failed', 'danger', $request, "Autentifikatsiya xatosi: Client ID yoki Client Secret kiritilmagan.");
            return response()->json([
                'status' => 'error',
                'reason' => 'MISSING_CREDENTIALS',
                'message' => 'Autentifikatsiya ma\'lumotlari yetishmaydi.',
                'detail' => 'Iltimos, HTTP Basic Auth (Authorization: Basic ...) yoki so\'rov tanasida "client_id" va "client_secret" yuboring.'
            ], 400);
        }

        // 3. API mijozini bazadan izlash
        $client = ApiClient::where('client_id', $clientId)->first();

        if (!$client || !hash_equals((string)$client->client_secret, (string)$clientSecret)) {
            $this->recordAudit((string)$clientId, 'api_auth_failed', 'danger', $request, "Autentifikatsiya xatosi: Noto'g'ri Client ID yoki Client Secret ({$clientId}).");
            return response()->json([
                'status' => 'error',
                'reason' => 'INVALID_CREDENTIALS',
                'message' => 'Client ID yoki Client Secret noto\'g\'ri.',
            ], 401);
        }

        // 4. Faollik holatini tekshirish
        if (!$client->is_active) {
            $this->recordAudit($client->name, 'api_auth_failed', 'danger', $request, "Mijoz faoliyati to'xtatilgan (Nofaol holatda): {$client->client_id}.");
            return response()->json([
                'status' => 'error',
                'reason' => 'CLIENT_INACTIVE',
                'message' => 'Ushbu API mijoz faoliyat ko\'rsatmayapti (Nofaol holatda). Administratorga murojaat qiling.',
            ], 403);
        }

        // 5. Amal qilish muddatini tekshirish
        if ($client->isExpired()) {
            $this->recordAudit($client->name, 'api_auth_failed', 'danger', $request, "Mijozning ruxsat muddati tugagan: {$client->client_id}.");
            return response()->json([
                'status' => 'error',
                'reason' => 'CLIENT_EXPIRED',
                'message' => 'Ushbu API mijozning ruxsat muddati tugagan.',
                'detail' => 'Amal qilish muddati: ' . $client->expires_at?->toIso8601String(),
            ], 403);
        }

        // 6. IP manzil oq ro'yxatini (whitelist) tekshirish
        if (!$client->isIpAllowed($request->ip())) {
            $this->recordAudit($client->name, 'api_auth_failed', 'danger', $request, "Ruxsat etilmagan IP manzil: " . $request->ip());
            return response()->json([
                'status' => 'error',
                'reason' => 'IP_NOT_ALLOWED',
                'message' => 'Sizning IP manzilingiz ruxsat etilganlar ro\'yxatida mavjud emas.',
                'detail' => 'Sizning IP: ' . $request->ip(),
            ], 403);
        }

        // 7. Token muddati: 24 soat (86400 sekund)
        $expiresIn = 86400;
        $expiresAt = now()->addSeconds($expiresIn);

        // Sanctum Bearer token yaratish
        $token = $client->createToken('api_client_token', ['b2b:access'], $expiresAt);

        // Oxirgi faollik vaqtini yangilash
        $client->update(['last_used_at' => now()]);

        // Muvaffaqiyatli token berilganligini auditga yozish
        $this->recordAudit($client->name, 'api_auth_token_issued', 'success', $request, "Bearer Access Token muvaffaqiyatli berildi ({$client->client_id}). Amal qilish muddati: 24 soat.");

        return response()->json([
            'status' => 'success',
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_in' => $expiresIn,
            'expires_at' => $expiresAt->toIso8601String(),
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'client_id' => $client->client_id,
                'rate_limit_per_minute' => $client->rate_limit_per_minute,
            ],
            'message' => 'Token muvaffaqiyatli yaratildi. Endi himoyalangan API larga "Authorization: Bearer <access_token>" sarlavhasi bilan murojaat qiling.',
        ]);
    }

    /**
     * Audit jurnali yordamchisi.
     */
    protected function recordAudit(string $name, string $action, string $status, Request $request, string $details): void
    {
        try {
            \App\Models\BiometricAuditLog::create([
                'person_name' => $name,
                'action' => $action,
                'status' => $status,
                'similarity_distance' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent() ?? 'B2B Client',
                'details' => $details,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("[Audit Log Warning] " . $e->getMessage());
        }
    }

    /**
     * Joriy token egasining ma'lumotlarini olish.
     */
    public function me(Request $request): JsonResponse
    {
        $client = $request->user();

        return response()->json([
            'status' => 'success',
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'client_id' => $client->client_id,
                'description' => $client->description,
                'is_active' => $client->is_active,
                'rate_limit_per_minute' => $client->rate_limit_per_minute,
                'expires_at' => $client->expires_at?->toIso8601String(),
                'last_used_at' => $client->last_used_at?->toIso8601String(),
            ]
        ]);
    }

    /**
     * Tokendan chiqish (Revoke / Invalidate).
     */
    public function revokeToken(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && method_exists($user, 'currentAccessToken')) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Token muvaffaqiyatli bekor qilindi (Revoked).',
        ]);
    }
}
