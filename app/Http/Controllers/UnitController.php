<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Services\AuditLogger;
use App\Services\UnitProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Units', [
            'units' => Unit::query()->withCount(['users', 'cashAccounts', 'bankAccounts'])->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, UnitProvisioner $provisioner, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:16', 'alpha_dash', 'unique:units,code'],
            'name' => ['required', 'string', 'max:160'],
            'short_name' => ['required', 'string', 'max:80'],
            'cash_opening' => ['nullable', 'integer', 'min:0'],
            'bank_opening' => ['nullable', 'integer', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:32'],
        ]);

        $unit = Unit::query()->create([
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'short_name' => $data['short_name'],
            'is_active' => true,
        ]);
        $provisioner->provision(
            $unit,
            (int) ($data['cash_opening'] ?? 0),
            (int) ($data['bank_opening'] ?? 0),
            $data['bank_name'] ?: 'Bank Syariah',
            'Rekening Operasional',
            $data['account_number'] ?: '0000000000',
        );
        $audit->log($request->user(), 'unit.created', 'unit', $unit->id, $unit->id, null, ['code' => $unit->code]);

        return back()->with('success', 'Unit '.$unit->code.' ditambahkan beserta kas dan bank.');
    }

    public function update(Request $request, Unit $unit, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'short_name' => ['required', 'string', 'max:80'],
            'is_active' => ['required', 'boolean'],
        ]);
        $old = ['name' => $unit->name, 'is_active' => $unit->is_active];
        $unit->update($data);
        $audit->log($request->user(), 'unit.updated', 'unit', $unit->id, $unit->id, $old, $data);

        return back()->with('success', 'Unit diperbarui.');
    }
}
