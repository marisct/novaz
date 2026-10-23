<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $timeout = (int) config('session.lifetime') * 60;
        $lastActivity = (int) $request->session()->get('last_activity_at', time());

        if (Auth::check() && time() - $lastActivity > $timeout) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Session expired. Please sign in again.']);
        }

        $request->session()->put('last_activity_at', time());

        return $next($request);
    }
}
