<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Users', [
            'users' => User::query()->with(['roles:id,name,label', 'unit:id,code'])->orderBy('name')->get()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'unit' => $user->unit?->code,
                'unit_id' => $user->unit_id,
                'role' => $user->roles->pluck('label')->join(', '),
                'role_id' => $user->roles->first()?->id,
                'is_active' => $user->is_active,
            ]),
            'roles' => Role::query()->orderBy('label')->get(['id', 'name', 'label']),
            'units' => Unit::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
        ]);
        $role = Role::query()->findOrFail($data['role_id']);

        if ($role->name === 'bendahara_unit' && empty($data['unit_id'])) {
            return back()->withErrors(['unit_id' => 'Bendahara unit harus ditempatkan pada satu unit.']);
        }

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'unit_id' => $role->name === 'bendahara_unit' ? $data['unit_id'] : null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->roles()->sync([$role->id]);
        $audit->log($request->user(), 'user.created', 'user', $user->id, $user->unit_id, null, [
            'email' => $user->email,
            'role' => $role->name,
        ]);

        return back()->with('success', 'Pengguna ditambahkan.');
    }

    public function update(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role_id' => ['required', 'exists:roles,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if ((int) $user->id === (int) $request->user()->id && ! $data['is_active']) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }

        $role = Role::query()->findOrFail($data['role_id']);
        $old = ['name' => $user->name, 'is_active' => $user->is_active, 'unit_id' => $user->unit_id];
        $user->fill([
            'name' => $data['name'],
            'is_active' => $data['is_active'],
            'unit_id' => $role->name === 'bendahara_unit' ? $data['unit_id'] : null,
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();
        $user->roles()->sync([$role->id]);
        $user->forgetPermissionCache();
        $audit->log($request->user(), 'user.updated', 'user', $user->id, $user->unit_id, $old, [
            'name' => $user->name,
            'is_active' => $user->is_active,
            'role' => $role->name,
        ]);

        return back()->with('success', 'Pengguna diperbarui.');
    }
}
