<?php

namespace App\Http\Middleware;

use App\Models\TableSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class VerifyQrSession
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sessionCode = Session::get('qr_session_code');

        if (! $sessionCode) {
            return response()->json(['error' => 'No active session. Please scan the QR code on your table.'], 403);
        }

        $sessionExists = TableSession::where('session_code', $sessionCode)
            ->where('status', 'open')
            ->exists();

        if (! $sessionExists) {
            Session::forget(['qr_session_code', 'qr_table_id', 'qr_cart']);

            return response()->json(['error' => 'Session expired or closed. Please scan the QR code again.'], 403);
        }

        return $next($request);
    }
}
