<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isFrontDesk()) {
            abort(403, 'Akses ditolak: Hanya staf kasir atau admin yang diizinkan mengakses halaman ini.');
        }

        return $next($request);
    }
}
