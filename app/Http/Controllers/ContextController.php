<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->unit_id !== null) {
            abort(403);
        }

        $unitId = $request->input('unit_id');

        if ($unitId === null || $unitId === '' || $unitId === 'all') {
            $request->session()->forget('context_unit_id');
        } else {
            $unit = Unit::query()->where('is_active', true)->find($unitId);

            if (! $unit) {
                abort(404);
            }

            $request->session()->put('context_unit_id', $unit->id);
        }

        return back();
    }
}
