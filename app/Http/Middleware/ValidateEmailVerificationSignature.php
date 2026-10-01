<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ValidateEmailVerificationSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! URL::hasValidSignature($request) && ! URL::hasValidSignature($request, absolute: false)) {
            throw new InvalidSignatureException;
        }

        return $next($request);
    }
}
