<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();
        $names = explode('|', $permissions);

        if (! $user || ! $user->hasAnyPermission($names)) {
            abort(403);
        }

        return $next($request);
    }
}
