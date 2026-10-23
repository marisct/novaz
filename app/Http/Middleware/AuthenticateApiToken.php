<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        abort_unless($plainToken, 401, 'Missing bearer token.');

        $token = ApiToken::with('user')
            ->where('token', ApiToken::hashToken($plainToken))
            ->where('expires_at', '>', now())
            ->first();

        abort_unless($token?->user, 401, 'Invalid bearer token.');

        $token->forceFill(['last_used_at' => now()])->save();
        Auth::setUser($token->user);

        return $next($request);
    }
}
