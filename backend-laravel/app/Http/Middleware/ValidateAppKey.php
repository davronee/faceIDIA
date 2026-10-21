<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateAppKey
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = env('MOBILE_APP_KEY');

        // Agar muhitda MOBILE_APP_KEY ko'rsatilgan bo'lsa, uni tekshiramiz
        if (!empty($expectedKey)) {
            $clientKey = $request->header('X-App-Key');

            if (!$clientKey || !hash_equals((string)$expectedKey, (string)$clientKey)) {
                return response()->json([
                    'detail' => "Ruxsatsiz ilova so'rovi (Noto'g'ri yoki yetishmayotgan X-App-Key).",
                ], 403);
            }
        }

        return $next($request);
    }
}

