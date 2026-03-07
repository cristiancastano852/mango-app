<?php

namespace App\Domain\Company\Observers;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\SurchargeRule;
use Database\Seeders\ColombianHolidaysSeeder;

class CompanyObserver
{
    public function created(Company $company): void
    {
        SurchargeRule::create(['company_id' => $company->id]);
        (new ColombianHolidaysSeeder)->seedForCompany($company->id);
    }
}
