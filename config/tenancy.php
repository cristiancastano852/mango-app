<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dominio base del SaaS
    |--------------------------------------------------------------------------
    |
    | Cada empresa (tenant) se sirve en {slug}.{base_domain}. En local con Herd
    | suele ser "mango-app.test"; en producción "webplena.com".
    |
    */

    'base_domain' => env('TENANCY_BASE_DOMAIN', 'mango-app.test'),

    /*
    |--------------------------------------------------------------------------
    | Subdominio de administración de plataforma
    |--------------------------------------------------------------------------
    |
    | Host donde inicia sesión y opera el super-admin (company_id = null).
    | No resuelve a ningún tenant.
    |
    */

    'admin_subdomain' => env('TENANCY_ADMIN_SUBDOMAIN', 'admin'),

    /*
    |--------------------------------------------------------------------------
    | Subdominios reservados
    |--------------------------------------------------------------------------
    |
    | Nunca se interpretan como slug de tenant y no pueden usarse como slug de
    | una empresa.
    |
    */

    'reserved_subdomains' => ['www', 'admin'],

];
