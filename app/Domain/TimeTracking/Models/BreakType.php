<?php

namespace App\Domain\TimeTracking\Models;

use App\Domain\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BreakType extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'icon',
        'color',
        'is_paid',
        'max_duration_minutes',
        'max_per_day',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function breakEntries(): HasMany
    {
        return $this->hasMany(BreakEntry::class);
    }
}
