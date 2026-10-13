<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if ($request->isMethodSafe() || ! $idempotencyKey) {
            return $next($request);
        }

        $cacheKey = 'idempotency:'.hash('sha256', implode(':', [
            (string) $request->user()?->getAuthIdentifier(),
            $request->method(),
            $request->path(),
            $idempotencyKey,
        ]));
        $requestFingerprint = hash('sha256', $request->getContent());

        if ($cached = Cache::get($cacheKey)) {
            return $this->replay($cached, $requestFingerprint);
        }

        $lock = Cache::lock($cacheKey.':lock', 30);

        if (! $lock->get()) {
            return response()->json([
                'message' => 'An identical request is already being processed.',
            ], 409, ['Retry-After' => '1']);
        }

        try {
            if ($cached = Cache::get($cacheKey)) {
                return $this->replay($cached, $requestFingerprint);
            }

            $response = $next($request);

            if ($response->isSuccessful()) {
                Cache::put($cacheKey, [
                    'status' => $response->getStatusCode(),
                    'data' => json_decode($response->getContent(), true) ?? $response->getContent(),
                    'fingerprint' => $requestFingerprint,
                ], now()->addMinutes(30));
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    private function replay(array $cached, string $requestFingerprint): Response
    {
        if (($cached['fingerprint'] ?? null) !== $requestFingerprint) {
            return response()->json([
                'message' => 'This Idempotency-Key was already used with a different request payload.',
            ], 409);
        }

        return response()->json($cached['data'], $cached['status'], [
            'X-Idempotent-Replay' => 'true',
        ]);
    }
}
