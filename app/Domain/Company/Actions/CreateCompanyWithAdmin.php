<?php

namespace App\Domain\Company\Actions;

use App\Domain\Company\Models\Company;
use App\Domain\Shared\Tenancy\Tenancy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCompanyWithAdmin
{
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $company = Company::create([
                'name' => $data['company_name'],
                'slug' => $this->resolveSlug($data['company_name'], $data['subdomain'] ?? null),
                'timezone' => 'America/Bogota',
                'country' => 'CO',
                'onboarding_completed' => false,
            ]);

            $plainPassword = 'password';

            $user = User::create([
                'company_id' => $company->id,
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $plainPassword,
            ]);

            $user->assignRole('admin');

            return [$company, $user, $plainPassword];
        });
    }

    /**
     * Resuelve el slug que servirá como subdominio del tenant. Usa el subdominio
     * explícito (ya validado) o autogenera uno limpio desde el nombre, agregando
     * un sufijo numérico solo en caso de colisión.
     */
    private function resolveSlug(string $companyName, ?string $explicit): string
    {
        if ($explicit !== null && $explicit !== '') {
            return $explicit;
        }

        $base = trim(Str::limit(Str::slug($companyName), 63, ''), '-');
        $base = $base !== '' ? $base : 'empresa';

        $slug = $base;
        $attempt = 2;

        while ($this->slugTaken($slug)) {
            $suffix = '-'.$attempt;
            $slug = trim(Str::limit($base, 63 - strlen($suffix), ''), '-').$suffix;
            $attempt++;
        }

        return $slug;
    }

    private function slugTaken(string $slug): bool
    {
        return Company::where('slug', $slug)->exists()
            || in_array($slug, Tenancy::reservedSubdomains(), true);
    }
}
