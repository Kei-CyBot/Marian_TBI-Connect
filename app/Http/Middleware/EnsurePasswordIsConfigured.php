<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsConfigured
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('force_password_change')
            && ! $request->routeIs('profile.edit', 'profile.update', 'profile.password.update', 'logout')) {
            return redirect()->route('profile.edit')
                ->with('status', 'Please set your personal account password to complete your setup.');
        }

        return $next($request);
    }
}
