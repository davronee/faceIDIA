<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Person;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SecureBiometricPhotoController extends Controller
{
    /**
     */
    public function adminPhoto(Request $request, int $id): BinaryFileResponse
    {
        $person = Person::findOrFail($id);
        $rawPhoto = $person->getRawOriginal('photo_url') ?? $person->photo_url;
        $filePath = $this->resolveFilePath($rawPhoto);

        if (!$filePath) {
            throw new NotFoundHttpException("Biometrik surat topilmadi.");
        }

        return response()->file($filePath, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     */
    public function citizenPhoto(Request $request): BinaryFileResponse
    {
        /** @var Person $person */
        $person = $request->user();

        if (!$person) {
            throw new NotFoundHttpException("Foydalanuvchi topilmadi.");
        }

        $rawPhoto = $person->getRawOriginal('photo_url') ?? $person->photo_url;
        $filePath = $this->resolveFilePath($rawPhoto);

        if (!$filePath) {
            throw new NotFoundHttpException("Biometrik surat topilmadi.");
        }

        return response()->file($filePath, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function adminPassportPhoto(Request $request, int $id): BinaryFileResponse
    {
        $person = Person::findOrFail($id);
        $rawPhoto = $person->passport_photo_url;
        $filePath = $this->resolveFilePath($rawPhoto);

        if (!$filePath) {
            throw new NotFoundHttpException("Pasport fotosurati topilmadi.");
        }

        return response()->file($filePath, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function resolveFilePath(?string $photoUrl): ?string
    {
        if (empty($photoUrl)) {
            return null;
        }

        $filename = basename($photoUrl);

        // 1. Asosiy private biometrik xavfsiz saqlash joyi (Laravel 11 app/private/biometrics)
        $privatePath = storage_path('app/private/biometrics/' . $filename);
        if (file_exists($privatePath)) {
            return $privatePath;
        }

        // 2. storage/app/biometrics
        $appPath = storage_path('app/biometrics/' . $filename);
        if (file_exists($appPath)) {
            return $appPath;
        }

        // 3. Fallback: app/public/uploads
        $publicPath = storage_path('app/public/uploads/' . $filename);
        if (file_exists($publicPath)) {
            return $publicPath;
        }

        return null;
    }
}
