<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials + ['status' => 'active'], $remember)) {
            return back()->withErrors(['email' => 'Invalid credentials or inactive account.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function apiLogin(Request $request): array
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);

        if (! Auth::attempt($credentials + ['status' => 'active'])) {
            abort(422, 'Invalid credentials.');
        }

        ApiToken::where('expires_at', '<=', now())->delete();

        $plain = Str::random(64);
        ApiToken::create([
            'user_id' => Auth::id(),
            'name' => $request->input('device_name', 'mobile'),
            'token' => ApiToken::hashToken($plain),
            'abilities' => ['*'],
            'expires_at' => now()->addDays(30),
        ]);

        return ['token' => $plain, 'user' => Auth::user()];
    }
}
