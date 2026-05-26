<?php

namespace App\Domain\Shared\Scopes;

use App\Domain\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app(TenantContext::class);

        if ($tenant->check()) {
            $builder->where($model->getTable().'.company_id', $tenant->id());

            return;
        }

        if (auth()->check() && auth()->user()->company_id) {
            $builder->where($model->getTable().'.company_id', auth()->user()->company_id);
        }
    }
}
