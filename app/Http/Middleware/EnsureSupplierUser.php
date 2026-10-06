<?php

namespace App\Http\Middleware;

use App\Enums\SupplierStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupplierUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->role->isSupplier() && $user->supplier_id !== null, 403);
        abort_unless($user->supplier?->status === SupplierStatus::Active, 403, 'Supplier portal access is suspended.');

        return $next($request);
    }
}
