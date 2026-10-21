<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Exception;

class BiometricVerificationException extends Exception
{
    protected string $reason;

    public function __construct(string $message, string $reason = 'LOW_QUALITY_IMAGE', int $code = 400)
    {
        parent::__construct($message, $code);
        $this->reason = $reason;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}

class FaceEmbeddingService
{
    protected string $aiServiceUrl;
    protected string $internalSecret;

    public function __construct()
    {
        $this->aiServiceUrl = config('services.ai.url', 'http://127.0.0.1:8000');
        $this->internalSecret = config('services.ai.secret', 'faceidia_internal_secret_token_2026');
    }

    /**
     * Rasmdan 512-o'lchamli biometrik vektorni ajratib oladi va passiv liveness tekshiruvini amalga oshiradi.
     *
     * @param UploadedFile $file
     * @param bool $verifyLiveness
     * @return array<float>
     * @throws BiometricVerificationException|Exception
     */
    public function getEmbedding(UploadedFile $file, bool $verifyLiveness = true): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Internal-Secret' => $this->internalSecret,
                ])
                ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                ->post("{$this->aiServiceUrl}/api/extract-embedding?verify_liveness=" . ($verifyLiveness ? 'true' : 'false'));

            $json = $response->json();

            // FastAPI validation error handling
            if ($response->failed() || !empty($json['detail']) || ($json['status'] ?? null) === 'error') {
                $detailData = $json['detail'] ?? $json;
                if (is_array($detailData)) {
                    $reason = $detailData['reason'] ?? 'LOW_QUALITY_IMAGE';
                    $message = $detailData['detail'] ?? $detailData['message'] ?? 'Biometrik tekshiruvdan o\'tmadi.';
                } else {
                    $reason = 'LOW_QUALITY_IMAGE';
                    $message = is_string($detailData) ? $detailData : 'Biometrik tekshiruvdan o\'tmadi.';
                }
                throw new BiometricVerificationException($message, $reason);
            }

            $embedding = $json['embedding'] ?? null;
            if (!is_array($embedding) || count($embedding) !== 512) {
                throw new BiometricVerificationException(
                    "Biometrik vektor hajmi noto'g'ri (512 ta parametr talab qilinadi).",
                    'LOW_QUALITY_IMAGE'
                );
            }

            return $embedding;
        } catch (BiometricVerificationException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new BiometricVerificationException($e->getMessage(), 'SYSTEM_ERROR');
        }
    }

    /**
     * 512 float massivini PostgreSQL pgvector satriga ('[0.12,0.34,...]') aylantiradi.
     */
    public function formatVector(array $embedding): string
    {
        return '[' . implode(',', $embedding) . ']';
    }
}
