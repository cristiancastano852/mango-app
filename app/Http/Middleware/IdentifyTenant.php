<?php

namespace App\Http\Middleware;

use App\Domain\Company\Models\Company;
use App\Domain\Shared\Tenancy\Tenancy;
use App\Domain\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $this->resolveSubdomain($request->getHost());

        if ($subdomain === null) {
            return $next($request);
        }

        $company = Company::query()->where('slug', $subdomain)->first();

        if (! $company) {
            abort(404);
        }

        $this->tenant->set($company);

        return $next($request);
    }

    /**
     * Devuelve el slug del subdominio del tenant, o null si el host es central
     * (apex, fuera del dominio base) o un subdominio reservado.
     */
    private function resolveSubdomain(string $host): ?string
    {
        $baseDomain = (string) config('tenancy.base_domain');
        $suffix = '.'.$baseDomain;

        if ($host === $baseDomain || Tenancy::isAdminHost($host) || ! str_ends_with($host, $suffix)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen($suffix));

        if (in_array($subdomain, Tenancy::reservedSubdomains(), true)) {
            return null;
        }

        return $subdomain;
    }
}
