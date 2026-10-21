<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class EgovPassportService
{
    protected string $baseUrl;
    protected string $authEndpoint;
    protected string $endpoint;
    protected ?string $authUsername;
    protected ?string $authPassword;
    protected ?string $staticToken;
    protected string $aiServiceUrl;
    protected string $internalAiSecret;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.egov.base_url', 'https://gateway.imv.uz/api/v1'), '/');
        $this->authEndpoint = (string) config('services.egov.auth_endpoint', '/auth/token');
        $this->endpoint = (string) config('services.egov.endpoint', '/egov/passport-gcp-document-birthdate');
        $this->authUsername = config('services.egov.auth_username');
        $this->authPassword = config('services.egov.auth_password');
        $this->staticToken = config('services.egov.token');
        $this->aiServiceUrl = rtrim((string) config('services.ai.url', 'http://backend:8000'), '/');
        $this->internalAiSecret = (string) config('services.ai.secret', 'faceidia_internal_secret_token_2026');
    }

    /**
     * Shlyuzdan (https://gateway.imv.uz/api/v1/auth/token) Basic Auth orqali token oladi yoki keshdan qaytaradi.
     *
     * @param bool $forceRefresh Keshni e'tiborsiz qoldirib, yangi token so'rash
     * @return string Bearer token
     * @throws Exception
     */
    public function getAuthToken(bool $forceRefresh = false): string
    {
        $cacheKey = 'egov_gateway_access_token';

        if (!$forceRefresh) {
            $cachedToken = Cache::get($cacheKey);
            if (!empty($cachedToken)) {
                return (string) $cachedToken;
            }
        }

        if (!empty($this->authUsername) && !empty($this->authPassword)) {
            $authUrl = $this->baseUrl . (str_starts_with($this->authEndpoint, '/') ? '' : '/') . $this->authEndpoint;
            $refreshCacheKey = 'egov_gateway_refresh_token';

            try {
                $response = null;

                // 1. Agar keshda mavjud refresh_token bo'lsa, avval refresh qilishga urinib ko'ramiz
                $cachedRefreshToken = Cache::get($refreshCacheKey);
                if (!empty($cachedRefreshToken)) {
                    Log::info("[eGov Auth] Refresh token orqali yangilash so'ralmoqda...");
                    $refreshResponse = Http::timeout(15)
                        ->withHeaders([
                            'Accept' => '*/*',
                            'Content-Type' => 'application/json',
                        ])
                        ->post($authUrl, [
                            'grant_type' => 'refresh_token',
                            'refresh_token' => $cachedRefreshToken,
                        ]);

                    if ($refreshResponse->successful()) {
                        $response = $refreshResponse;
                        Log::info("[eGov Auth] Refresh token orqali yangilandi.");
                    } else {
                        Log::warning("[eGov Auth] Refresh token muddati o'tgan yoki rad etildi, login/parol orqali yangi olinadi.");
                        Cache::forget($refreshCacheKey);
                    }
                }

                // 2. Agar refresh bo'lmagan bo'lsa (yoki yo'q bo'lsa), to'liq password grant bilan olamiz
                if ($response === null) {
                    Log::info("[eGov Auth] grant_type=password orqali yangi token so'ralmoqda...", [
                        'url' => $authUrl,
                        'username' => $this->authUsername,
                    ]);

                    $response = Http::timeout(15)
                        ->withHeaders([
                            'Accept' => '*/*',
                            'Content-Type' => 'application/json',
                        ])
                        ->post($authUrl, [
                            'grant_type' => 'password',
                            'username' => $this->authUsername,
                            'password' => $this->authPassword,
                            'refresh_token' => null,
                        ]);
                }

                if ($response->failed()) {
                    Log::error("[eGov Auth Token ERROR] Status: {$response->status()}", [
                        'url' => $authUrl,
                        'username' => $this->authUsername,
                        'body' => $response->json() ?? $response->body(),
                    ]);
                    throw new Exception("Tashqi davlat shlyuzidan token olib bo'lmadi (Status: {$response->status()}). Login/parol yoki ruxsatni tekshiring.", 401);
                }

                $json = $response->json();
                $token = null;

                if (is_array($json)) {
                    $token = $json['access_token']
                        ?? $json['token']
                        ?? $json['data']['access_token']
                        ?? $json['data']['token']
                        ?? $json['result']['token']
                        ?? null;
                } elseif (is_string($response->body()) && str_starts_with(trim($response->body()), 'ey')) {
                    $token = trim($response->body());
                }

                if (empty($token)) {
                    throw new Exception("Shlyuz auth javobidan access_token topilmadi.", 502);
                }

                // Agar yangi refresh_token berilgan bo'lsa, keshga saqlaymiz (30 kun)
                if (is_array($json) && !empty($json['refresh_token'])) {
                    Cache::put($refreshCacheKey, (string) $json['refresh_token'], 86400 * 30);
                }

                // Token muddatini aniqlash (expires_in ms yoki s, yoxud JWT exp claim orqali)
                $ttlSeconds = 3000; // default 50 minut
                if (isset($json['expires_in']) && is_numeric($json['expires_in'])) {
                    $expiresIn = (int) $json['expires_in'];
                    if ($expiresIn > 86400) {
                        $expiresIn = (int) ($expiresIn / 1000); // millisekunddan sekundga o'tkazish
                    }
                    $ttlSeconds = max(60, $expiresIn - 120);
                } else {
                    $parts = explode('.', $token);
                    if (count($parts) === 3) {
                        $jwtPayload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
                        if (!empty($jwtPayload['exp']) && is_numeric($jwtPayload['exp'])) {
                            $remaining = $jwtPayload['exp'] - time();
                            if ($remaining > 180) {
                                $ttlSeconds = $remaining - 120;
                            }
                        }
                    }
                }

                Cache::put($cacheKey, $token, $ttlSeconds);
                Log::info("[eGov Auth] Yangi token muvaffaqiyatli olindi va keshga saqlandi (TTL: {$ttlSeconds}s).");

                return $token;

            } catch (Exception $e) {
                if (!empty($this->staticToken)) {
                    Log::warning("[eGov Auth] Dynamic token olishda xatolik, zaxira EGOV_PASSPORT_TOKEN ishlatilmoqda: " . $e->getMessage());
                    return $this->staticToken;
                }
                throw $e;
            }
        }

        // Agar Basic Auth sozlanmagan bo'lsa, zaxiradagi static tokenni ishlatamiz
        if (!empty($this->staticToken)) {
            return $this->staticToken;
        }

        throw new Exception("Davlat shlyuzi uchun EGOV_AUTH_USERNAME / EGOV_AUTH_PASSWORD yoki EGOV_PASSPORT_TOKEN sozlanmagan. Iltimos, .env faylini to'ldiring.", 500);
    }

    /**
     * Tashqi davlat e-gov shlyuziga so'rov yuborib, fuqaroning pasport ma'lumotlari va rasmini oladi.
     *
     * @param string $document Masalan: "AA1234567"
     * @param string $birthDate Masalan: "1995-05-15"
     * @param bool $isPhoto Pasport fotosuratini base64 formatda olish
     * @return array
     * @throws Exception
     */
    public function fetchPassportData(string $document, string $birthDate, bool $isPhoto = true): array
    {
        $cleanDocument = strtoupper(trim(str_replace(' ', '', $document)));
        $cleanBirthDate = trim($birthDate);

        $url = $this->baseUrl . (str_starts_with($this->endpoint, '/') ? '' : '/') . $this->endpoint;

        $payload = [
            'birthDate' => $cleanBirthDate,
            'document' => $cleanDocument,
            'isPhoto' => $isPhoto,
        ];

        try {
            $token = $this->getAuthToken();

            $request = Http::timeout(20)
                ->withHeaders([
                    'Accept' => 'application/json, text/plain, */*',
                    'Content-Type' => 'application/json',
                ])
                ->withToken($token);

            $response = $request->post($url, $payload);

            // Agar 401 Unauthorized kelsa va dynamic basic auth yoqilgan bo'lsa, keshni tozalab 1 marta yangilab ko'ramiz
            if (($response->status() === 401 || $response->status() === 403) && !empty($this->authUsername) && !empty($this->authPassword)) {
                Log::warning("[eGov Passport] Token eskirgan (Status {$response->status()}), kesh tozalanib yangi token bilan qayta urinilmoqda...");
                $freshToken = $this->getAuthToken(forceRefresh: true);

                $response = Http::timeout(20)
                    ->withHeaders([
                        'Accept' => 'application/json, text/plain, */*',
                        'Content-Type' => 'application/json',
                    ])
                    ->withToken($freshToken)
                    ->post($url, $payload);
            }

            if ($response->failed()) {
                $errorBody = $response->json() ?? $response->body();
                Log::error("[eGov Passport ERROR] Status: {$response->status()}", [
                    'url' => $url,
                    'document' => $cleanDocument,
                    'error' => $errorBody,
                ]);

                $errorMessage = "Davlat pasport xizmatidan ma'lumot olib bo'lmadi.";
                if (is_array($errorBody) && !empty($errorBody['message'])) {
                    $errorMessage = $errorBody['message'];
                } elseif ($response->status() === 404) {
                    $errorMessage = "Ko'rsatilgan pasport seriyasi yoki tug'ilgan sana bo'yicha fuqaro topilmadi.";
                } elseif ($response->status() === 401 || $response->status() === 403) {
                    $errorMessage = "Tashqi davlat shlyuzi autentifikatsiya xatosi (Invalid Bearer Token yoki ruxsat yo'q).";
                }

                throw new Exception($errorMessage, $response->status() > 0 ? $response->status() : 502);
            }

            $data = $response->json();
            if (empty($data) || !is_array($data)) {
                throw new Exception("Tashqi davlat shlyuzidan bo'sh yoki noto'g'ri formatdagi javob keldi.", 502);
            }

            return $data;

        } catch (Exception $e) {
            Log::error("[eGov Passport Exception]: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Jonli kamera fotosuratini e-gov pasport fotosurati (base64) bilan 1:1 solishtirish.
     *
     * @param UploadedFile $liveFace Jonli selfi/kamera rasmi
     * @param string $passportBase64 Pasportdagi base64 JPEG rasmi
     * @return array [is_match, similarity_distance, similarity_percentage, liveness_verified]
     * @throws Exception
     */
    public function compareFaceWithPassport(UploadedFile $liveFace, string $passportBase64): array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'X-Internal-Secret' => $this->internalAiSecret,
                ])
                ->attach('file_live', file_get_contents($liveFace->getRealPath()), $liveFace->getClientOriginalName())
                ->asMultipart()
                ->post("{$this->aiServiceUrl}/api/compare-faces", [
                    [
                        'name' => 'passport_base64',
                        'contents' => $passportBase64,
                    ]
                ]);

            $json = $response->json();

            if ($response->failed() || ($json['status'] ?? null) === 'error') {
                $detail = $json['detail'] ?? "Biometrik solishtirishda xatolik yuz berdi.";
                if (is_array($detail)) {
                    $detailMsg = $detail['detail'] ?? $detail['message'] ?? 'Yuz solishtiruvdan o\'tmadi.';
                    $reason = $detail['reason'] ?? 'BIOMETRIC_FAILED';
                } else {
                    $detailMsg = (string)$detail;
                    $reason = 'BIOMETRIC_FAILED';
                }
                throw new BiometricVerificationException($detailMsg, $reason);
            }

            return $json;

        } catch (BiometricVerificationException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error("[AI Compare Faces Exception]: " . $e->getMessage());
            throw new Exception("AI xizmati bilan bog'lanishda xatolik: " . $e->getMessage(), 500);
        }
    }
}
