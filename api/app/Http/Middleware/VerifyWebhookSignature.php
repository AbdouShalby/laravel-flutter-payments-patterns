<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('payments.webhook_secret');
        $timestamp = (string) $request->header('X-Timestamp', '');
        $signature = (string) $request->header('X-Signature', '');

        if ($secret === '' || ! ctype_digit($timestamp) || $signature === '') {
            abort(401, 'Missing signature');
        }

        if (abs(time() - (int) $timestamp) > config('payments.webhook_tolerance_seconds', 300)) {
            abort(401, 'Stale webhook');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid signature');
        }

        return $next($request);
    }
}
