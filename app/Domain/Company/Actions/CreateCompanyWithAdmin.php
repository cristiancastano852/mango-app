<?php

namespace App\Domain\Company\Actions;

use App\Domain\Company\Models\Company;
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
                'slug' => Str::limit(Str::slug($data['company_name']), 248, '').'-'.Str::random(6),
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
}
