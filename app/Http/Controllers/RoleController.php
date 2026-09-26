<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Roles', [
            'roles' => Role::query()->with('permissions:id,name')->orderBy('label')->get()->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label,
                'description' => $role->description,
                'permissions' => $role->permissions->pluck('name'),
            ]),
            'permissions' => Permission::query()->orderBy('group')->orderBy('name')->get(['id', 'name', 'label', 'group']),
        ]);
    }

    public function update(Request $request, Role $role, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);
        $ids = Permission::query()->whereIn('name', $data['permissions'] ?? [])->pluck('id');

        if ($role->name === 'super_admin' && ! Permission::query()->whereIn('id', $ids)->where('name', 'USER_MANAGE')->exists()) {
            return back()->with('error', 'Peran super admin harus tetap dapat mengelola pengguna.');
        }

        $before = $role->permissions()->pluck('name')->all();
        $role->permissions()->sync($ids);
        $audit->log($request->user(), 'role.updated', 'role', $role->id, null, ['permissions' => $before], [
            'permissions' => $data['permissions'] ?? [],
        ]);

        return back()->with('success', 'Izin peran diperbarui.');
    }
}
