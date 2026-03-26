<?php

namespace App\Domain\Company\Actions;

use App\Domain\Company\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterCompany
{
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $company = Company::create([
                'name' => $data['company_name'],
                'slug' => Str::limit(Str::slug($data['company_name']), 248, '').'-'.Str::random(6),
                'timezone' => 'America/Bogota',
                'country' => 'CO',
                'onboarding_completed' => false,
            ]);

            $user = User::create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $user->assignRole('admin');

            Auth::login($user);

            return $user;
        });
    }
}
