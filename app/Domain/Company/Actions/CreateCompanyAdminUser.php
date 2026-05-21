<?php

namespace App\Domain\Company\Actions;

use App\Domain\Company\Models\Company;
use App\Models\User;

class CreateCompanyAdminUser
{
    public function execute(Company $company, string $name, string $email): array
    {
        $plainPassword = 'password';

        $user = User::create([
            'company_id' => $company->id,
            'name' => $name,
            'email' => $email,
            'password' => $plainPassword,
        ]);

        $user->assignRole('admin');

        return [$user, $plainPassword];
    }
}
