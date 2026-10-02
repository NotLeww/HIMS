<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnforceDeviceSecurityForApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('auth.device_security.enabled', true)) {
            return $next($request);
        }

        if ($request->user()?->currentAccessToken() instanceof PersonalAccessToken) {
            return $this->rejectBearerToken();
        }

        return $next($request);
    }

    private function rejectBearerToken(): JsonResponse
    {
        return response()->json([
            'message' => 'Bearer-token access is unavailable while single-device security is enabled. Sign in through HIMS.',
            'code' => 'DEVICE_SECURITY_REQUIRED',
        ], 401);
    }
}
