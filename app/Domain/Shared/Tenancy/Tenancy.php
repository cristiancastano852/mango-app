<?php

namespace App\Domain\Shared\Tenancy;

class Tenancy
{
    public static function baseDomain(): string
    {
        return (string) config('tenancy.base_domain');
    }

    public static function adminHost(): string
    {
        return config('tenancy.admin_subdomain').'.'.self::baseDomain();
    }

    public static function isAdminHost(string $host): bool
    {
        return $host === self::adminHost();
    }

    /**
     * Subdominios que nunca son tenant: los reservados de config más el de
     * administración (que es configurable), para que ambos se mantengan en sync.
     *
     * @return list<string>
     */
    public static function reservedSubdomains(): array
    {
        return array_values(array_unique(array_merge(
            config('tenancy.reserved_subdomains', []),
            [config('tenancy.admin_subdomain')],
        )));
    }

    /**
     * Raíz de URL (scheme + host) del tenant indicado por su slug. Sin slug,
     * devuelve la del host de administración. El scheme se infiere de app.url.
     */
    public static function rootUrl(?string $slug): string
    {
        $scheme = str_starts_with((string) config('app.url'), 'https') ? 'https' : 'http';
        $host = $slug !== null && $slug !== '' ? $slug.'.'.self::baseDomain() : self::adminHost();

        return $scheme.'://'.$host;
    }
}
