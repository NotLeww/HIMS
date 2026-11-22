<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFleetApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedHash = (string) config('services.fleet.api_key_sha256');

        if ($expectedHash === '') {
            return response()->json(['message' => 'Fleet integration is not configured.'], 503);
        }

        $providedKey = $request->header('X-API-Key');

        if (! is_string($providedKey) || $providedKey === ''
            || ! hash_equals($expectedHash, hash('sha256', $providedKey))) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
