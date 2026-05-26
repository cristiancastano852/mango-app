<?php

namespace App\Http\Middleware;

use App\Domain\Shared\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Tenancy::isAdminHost($request->getHost())) {
            abort(404);
        }

        return $next($request);
    }
}
