<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RestrictPanelAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->guest(route('filament.admin.auth.login'));
        }

        // super_admin and admin can access all panels
        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
            return $next($request);
        }

        // Restrict by specific panel role requirement
        if ($role === 'cashier' && $user->hasRole('cashier')) {
            return $next($request);
        }

        if ($role === 'kitchen' && $user->hasRole('kitchen')) {
            return $next($request);
        }

        // Deny access
        Auth::logout();
        return redirect()->route('filament.admin.auth.login')->with('error', 'You do not have access to this panel.');
    }
}
