<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings', [
            'settings' => [
                'prevent_self_approval' => Setting::flag('prevent_self_approval', true),
                'budget_thresholds' => Setting::values('budget_thresholds', [75, 90, 100]),
                'organization_name' => Setting::query()->find('organization_name')?->value['text'] ?? 'Yayasan Tanwiriyyah',
            ],
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'prevent_self_approval' => ['required', 'boolean'],
            'budget_thresholds' => ['required', 'array', 'min:1'],
            'budget_thresholds.*' => ['integer', 'min:1', 'max:100'],
            'organization_name' => ['required', 'string', 'max:160'],
        ]);

        Setting::query()->updateOrCreate(['key' => 'prevent_self_approval'], ['value' => ['enabled' => $data['prevent_self_approval']]]);
        Setting::query()->updateOrCreate(['key' => 'budget_thresholds'], ['value' => ['values' => array_values($data['budget_thresholds'])]]);
        Setting::query()->updateOrCreate(['key' => 'organization_name'], ['value' => ['text' => $data['organization_name']]]);
        $audit->log($request->user(), 'settings.updated', 'setting', null, null, null, [
            'prevent_self_approval' => $data['prevent_self_approval'],
            'budget_thresholds' => $data['budget_thresholds'],
        ]);

        return back()->with('success', 'Pengaturan disimpan.');
    }
}
