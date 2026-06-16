<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $expectedToken = config('master-data.sync_token');

        if (! $token || $token !== $expectedToken) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid or missing sync token.',
            ], 401);
        }

        return $next($request);
    }
}
