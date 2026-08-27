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
            if ($request->expectsJson()) {
                return response()->json(['error' => 'No active session. Please scan the QR code on your table.'], 403);
            }

            abort(403, 'No active session. Please scan the QR code on your table.');
        }

        $tableSession = TableSession::where('session_code', $sessionCode)
            ->where('status', 'open')
            ->first();

        if (! $tableSession || $tableSession->isExpired()) {
            if ($tableSession && $tableSession->isExpired()) {
                $tableSession->update([
                    'status' => 'closed',
                    'closed_at' => now(),
                ]);
            }

            Session::forget(['qr_session_code', 'qr_table_id', 'qr_cart']);

            if ($request->expectsJson()) {
                return response()->json(['error' => 'Session expired or closed. Please scan the QR code again.'], 403);
            }

            abort(403, 'Session expired or closed. Please scan the QR code again.');
        }

        return $next($request);
    }
}
