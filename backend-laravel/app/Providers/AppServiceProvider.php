<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. Yuzni tanish / tekshirish: 1 daqiqada bitta IP dan ko'pi bilan 20 ta
        RateLimiter::for('face-recognize', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip())->response(function () {
                return response()->json([
                    'detail' => "Juda ko'p biometrik so'rov yuborildi. Iltimos, biroz kuting (Too Many Requests)."
                ], 429);
            });
        });

        // 3. Tashqi B2B / Turniket API lari: 1 daqiqada 60 ta so'rov
        RateLimiter::for('external-api', function (Request $request) {
            $key = $request->header('X-External-Key') ?? $request->ip();
            return Limit::perMinute(60)->by($key)->response(function () {
                return response()->json([
                    'status' => 'error',
                    'reason' => 'RATE_LIMIT_EXCEEDED',
                    'message' => "Tashqi tizim so'rovlar limiti oshib ketdi (Too Many Requests).",
                    'detail' => "1 daqiqada ko'pi bilan 60 ta so'rov yuborish mumkin."
                ], 429);
            });
        });
    }
}
