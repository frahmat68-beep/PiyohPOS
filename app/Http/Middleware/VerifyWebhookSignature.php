<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    /**
     * Handle webhook signature verification.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Hub-Signature-256');
        $secret = env('WEBHOOK_HMAC_SECRET', 'piyoh_webhook_secure_secret_2026!');

        if (!$signature) {
            return response()->json(['error' => 'Header X-Hub-Signature-256 missing.'], 401);
        }

        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json(['error' => 'Invalid webhook signature.'], 401);
        }

        return $next($request);
    }
}
