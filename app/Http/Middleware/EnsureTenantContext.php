<?php

namespace App\Http\Middleware;

use App\Domain\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenant->check()) {
            abort(404);
        }

        return $next($request);
    }
}
