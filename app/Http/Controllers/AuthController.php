<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request, AuditLogger $audit): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $audit->log(null, 'login.failed', 'user', null, null, null, [
                'email' => $request->string('email')->toString(),
            ]);

            return back()->withErrors(['email' => 'Email atau kata sandi tidak sesuai.'])->onlyInput('email');
        }

        $user = $request->user();

        if (! $user->is_active) {
            Auth::logout();

            return back()->withErrors(['email' => 'Akun ini tidak aktif.']);
        }

        $request->session()->regenerate();
        $audit->log($user, 'login', 'user', $user->id, $user->unit_id, null, ['email' => $user->email]);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $audit->log($user, 'logout', 'user', $user->id, $user->unit_id);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
