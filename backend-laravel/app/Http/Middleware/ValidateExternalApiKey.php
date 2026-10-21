<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class ValidateExternalApiKey
{
    /**
     * Tashqi integratsiyalar (Banklar, Turniketlar, HR tizimlar) uchun API autentifikatsiyasini tekshiradi.
     * 
     * Ruxsat etilgan usullar:
     * 1. Bearer Token (Tavsiya etiladi): Authorization: Bearer <access_token>
     * 2. X-External-Key (Master Kalit - orqaga moslik uchun): X-External-Key: ...
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Bearer Token ni tekshirish
        $bearerToken = $request->bearerToken();
        if (empty($bearerToken)) {
            $rawAuth = $request->header('Authorization') ?? $request->header('authorization');
            if ($rawAuth) {
                $bearerToken = preg_replace('/^Bearer\s+/i', '', trim($rawAuth));
            }
        } else {
            // Swagger UI yoki mijozda "Bearer " so'zi takror yozilgan bo'lsa tozalash
            $bearerToken = preg_replace('/^Bearer\s+/i', '', trim($bearerToken));
        }

        if (!empty($bearerToken)) {
            $accessToken = PersonalAccessToken::findToken($bearerToken);

            if ($accessToken) {
                // Token muddati o'tganligini tekshirish
                if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
                    $this->logSecurity($accessToken->tokenable?->name ?? 'B2B Mijoz', 'api_unauthorized', 'danger', $request, 'API Tokenining amal qilish muddati tugagan.');
                    return response()->json([
                        'status' => 'error',
                        'reason' => 'TOKEN_EXPIRED',
                        'message' => 'API Tokeningizning amal qilish muddati tugagan.',
                        'detail' => 'Iltimos, POST /api/v1/auth/token orqali yangi token oling.'
                    ], 401);
                }

                $tokenable = $accessToken->tokenable;

                if ($tokenable instanceof ApiClient) {
                    if (!$tokenable->is_active) {
                        $this->logSecurity($tokenable->name, 'api_unauthorized', 'danger', $request, 'API mijoz faoliyati to\'xtatilgan (Nofaol holatda).');
                        return response()->json([
                            'status' => 'error',
                            'reason' => 'CLIENT_INACTIVE',
                            'message' => 'Ushbu API mijoz faoliyati to\'xtatilgan (Nofaol).',
                        ], 403);
                    }

                    if ($tokenable->isExpired()) {
                        $this->logSecurity($tokenable->name, 'api_unauthorized', 'danger', $request, 'API mijozning litsenziya/amal qilish muddati tugagan.');
                        return response()->json([
                            'status' => 'error',
                            'reason' => 'CLIENT_EXPIRED',
                            'message' => 'Ushbu API mijozning litsenziya/amal qilish muddati tugagan.',
                        ], 403);
                    }

                    if (!$tokenable->isIpAllowed($request->ip())) {
                        $this->logSecurity($tokenable->name, 'api_unauthorized', 'danger', $request, 'Ruxsat etilmagan IP manzil: ' . $request->ip());
                        return response()->json([
                            'status' => 'error',
                            'reason' => 'IP_NOT_ALLOWED',
                            'message' => 'Sizning IP manzilingiz ruxsat etilganlar ro\'yxatida mavjud emas.',
                            'detail' => 'Sizning IP: ' . $request->ip(),
                        ], 403);
                    }

                    // Request ga joriy mijozni bog'lash
                    $tokenable->withAccessToken($accessToken);
                    $request->setUserResolver(fn() => $tokenable);

                    // Oxirgi ishlatilgan vaqtni yangilash
                    $accessToken->forceFill(['last_used_at' => now()])->save();

                    return $next($request);
                }
            }
        }

        // 2. Orqaga moslik: X-External-Key / X-Api-Key (Master Secret)
        $masterKey = env('EXTERNAL_API_KEY', 'faceidia_external_b2b_secret_key_2026');
        $headerKey = $request->header('X-External-Key') ?? $request->header('X-Api-Key');

        if (!empty($masterKey) && !empty($headerKey) && hash_equals((string)$masterKey, (string)$headerKey)) {
            return $next($request);
        }

        // 3. Autentifikatsiyadan o'tmadi
        $this->logSecurity('Noma\'lum mijoz', 'api_unauthorized', 'danger', $request, 'Ruxsatsiz API murojaat. Bearer Token topilmadi yoki yaroqsiz.');

        return response()->json([
            'status' => 'error',
            'reason' => 'UNAUTHORIZED_API_CLIENT',
            'message' => 'Autentifikatsiya xatosi. Yaroqli API Bearer Token talab qilinadi.',
            'detail' => 'Token olish uchun: POST /api/v1/auth/token (Basic Auth yoki client_id/client_secret bilan). So\'ng so\'rovlarda "Authorization: Bearer <access_token>" sarlavhasini yuboring.'
        ], 401);
    }

    /**
     * Xavfsizlik jurnali yozish yordamchisi.
     */
    protected function logSecurity(string $clientName, string $action, string $status, Request $request, string $details): void
    {
        try {
            \App\Models\BiometricAuditLog::create([
                'person_name' => $clientName,
                'action' => $action,
                'status' => $status,
                'similarity_distance' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent() ?? 'External API Client',
                'details' => $details,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("[Audit Log Warning] " . $e->getMessage());
        }
    }
}
