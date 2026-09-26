<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Unit;
use App\Support\Navigation;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user) {
            $user->loadMissing('roles.permissions', 'unit');
        }

        return [
            ...parent::share($request),
            'auth' => $user ? [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'unit_id' => $user->unit_id,
                    'unit' => $user->unit ? ['id' => $user->unit->id, 'code' => $user->unit->code, 'name' => $user->unit->name] : null,
                    'role' => $user->primaryRoleLabel(),
                    'permissions' => $user->permissionNames(),
                ],
            ] : null,
            'navigation' => $user ? Navigation::for($user) : [],
            'unit_options' => $user && $user->unit_id === null
                ? Unit::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])
                : [],
            'context_unit_id' => $user && $user->unit_id === null ? session('context_unit_id') : $user?->unit_id,
            'notifications' => $user ? [
                'unread' => $user->unreadNotifications()->count(),
                'preview' => $user->notifications()->latest()->limit(5)->get()->map(fn ($notification) => [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? '',
                    'body' => $notification->data['body'] ?? '',
                    'href' => $notification->data['href'] ?? '/notifications',
                    'read' => $notification->read_at !== null,
                    'at' => $notification->created_at?->toIso8601String(),
                ]),
            ] : ['unread' => 0, 'preview' => []],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'app_name' => 'Tanwiriyyah',
        ];
    }
}
