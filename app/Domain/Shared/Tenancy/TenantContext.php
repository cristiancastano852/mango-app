<?php

namespace App\Domain\Shared\Tenancy;

use App\Domain\Company\Models\Company;

class TenantContext
{
    private ?Company $company = null;

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function get(): ?Company
    {
        return $this->company;
    }

    public function check(): bool
    {
        return $this->company !== null;
    }

    public function id(): ?int
    {
        return $this->company?->id;
    }
}
