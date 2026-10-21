<?php

use App\Models\Person;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Swagger UI interaktiv hujjatlari (to'g'ridan-to'g'ri 200 OK qaytaradi, redirectsiz)
$docsHandler = function () {
    $path = public_path('docs/index.html');
    if (!file_exists($path)) {
        abort(404, "Swagger documentation not found.");
    }
    return response(file_get_contents($path), 200, [
        'Content-Type' => 'text/html; charset=utf-8',
        'Cache-Control' => 'no-cache, must-revalidate',
    ]);
};

Route::get('/docs', $docsHandler);
Route::get('/api/docs', $docsHandler);
Route::get('/swagger', $docsHandler);
Route::get('/api/swagger', $docsHandler);

// OpenAPI YAML spetsifikatsiyasi (CORS ruxsati bilan)
$yamlHandler = function () {
    $path = public_path('docs/openapi.yaml');
    if (!file_exists($path)) {
        abort(404, "OpenAPI specification not found.");
    }
    return response(file_get_contents($path), 200, [
        'Content-Type' => 'text/yaml; charset=utf-8',
        'Access-Control-Allow-Origin' => '*',
    ]);
};

Route::get('/docs/openapi.yaml', $yamlHandler);
Route::get('/openapi.yaml', $yamlHandler);

Route::middleware(['web', \MoonShine\Laravel\Http\Middleware\Authenticate::class])->group(function () {
    // Biometrik fotosuratlarni faqat avtorizatsiyadan o'tgan adminga xavfsiz ko'rsatish
    Route::get('/admin/biometrics/photo/{id}', [\App\Http\Controllers\SecureBiometricPhotoController::class, 'adminPhoto'])
        ->name('admin.biometrics.photo');

    Route::get('/admin/biometrics/passport-photo/{id}', [\App\Http\Controllers\SecureBiometricPhotoController::class, 'adminPassportPhoto'])
        ->name('admin.biometrics.passport_photo');
});
