<?php

namespace App\Http\Controllers\Settings;

use App\Domain\TimeTracking\Models\BreakType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreBreakTypeRequest;
use App\Http\Requests\Settings\UpdateBreakTypeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BreakTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $breakTypes = BreakType::withoutGlobalScopes()
            ->where('company_id', $request->user()->company_id)
            ->orderBy('name')
            ->get();

        return Inertia::render('settings/BreakTypes', [
            'breakTypes' => $breakTypes,
        ]);
    }

    public function store(StoreBreakTypeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $companyId = $request->user()->company_id;

        DB::transaction(function () use ($data, $companyId) {
            if (! empty($data['is_default'])) {
                BreakType::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            BreakType::create(array_merge($data, [
                'company_id' => $companyId,
                'slug' => Str::slug($data['name']),
            ]));
        });

        return to_route('break-types.index');
    }

    public function update(UpdateBreakTypeRequest $request, BreakType $breakType): RedirectResponse
    {
        if ($breakType->company_id !== $request->user()->company_id) {
            throw ValidationException::withMessages([
                'break_type' => 'No tienes permiso para editar este tipo de pausa.',
            ]);
        }

        $data = $request->validated();
        $companyId = $request->user()->company_id;

        DB::transaction(function () use ($data, $companyId, $breakType) {
            if (! empty($data['is_default'])) {
                BreakType::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('is_default', true)
                    ->where('id', '!=', $breakType->id)
                    ->update(['is_default' => false]);
            }

            $breakType->update(array_merge($data, [
                'slug' => Str::slug($data['name']),
            ]));
        });

        return to_route('break-types.index');
    }

    public function toggleActive(Request $request, BreakType $breakType): RedirectResponse
    {
        if ($breakType->company_id !== $request->user()->company_id) {
            throw ValidationException::withMessages([
                'break_type' => 'No tienes permiso para modificar este tipo de pausa.',
            ]);
        }

        if ($breakType->is_active && $breakType->is_default) {
            throw ValidationException::withMessages([
                'break_type' => 'No se puede desactivar el tipo de pausa por defecto.',
            ]);
        }

        $breakType->update(['is_active' => ! $breakType->is_active]);

        return to_route('break-types.index');
    }
}
