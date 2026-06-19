<?php

namespace BitDreamIT\MikoPBX\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use BitDreamIT\MikoPBX\Services\BlacklistService;

/**
 * CheckBlacklist Middleware
 *
 * Automatically reject calls from blacklisted numbers
 * before they reach your IVR or agents.
 *
 * Register in your routes:
 *   Route::middleware('mikopbx.blacklist')->group(...)
 */
class CheckBlacklist
{
    public function __construct(private BlacklistService $blacklist) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $number = $request->input('caller') ?? $request->input('caller_number') ?? $request->input('from');

        if ($number && $this->blacklist->isBlocked($number)) {
            return response()->json([
                'blocked'  => true,
                'number'   => $number,
                'message'  => 'Number is blacklisted',
            ], 403);
        }

        return $next($request);
    }
}

/**
 * VerifyWebhookSignature Middleware
 *
 * Verify HMAC signature on incoming MikoPBX webhooks.
 *
 * Register: Route::middleware('mikopbx.webhook')
 */
class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): mixed
    {
        $secret = config('mikopbx.webhook_secret');

        if (!$secret) return $next($request);

        $sig      = $request->header('X-MikoPBX-Signature', '');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($expected, $sig)) {
            return response()->json(['error' => 'Invalid webhook signature'], 401);
        }

        return $next($request);
    }
}

/**
 * MikoPBXApiAuth Middleware
 *
 * Simple API key authentication for MikoPBX package routes.
 * Uses MIKOPBX_ROUTE_API_KEY env variable.
 */
class MikoPBXApiAuth
{
    public function handle(Request $request, Closure $next): mixed
    {
        $key = config('mikopbx.route_api_key');

        if (!$key) return $next($request);

        $provided = $request->header('X-MikoPBX-Key') ?? $request->query('api_key');

        if ($provided !== $key) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}

/**
 * LogCallActivity Middleware
 *
 * Log all API activity to the mikopbx log channel.
 */
class LogCallActivity
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        \Illuminate\Support\Facades\Log::channel('mikopbx')->info('API Call', [
            'method'  => $request->method(),
            'path'    => $request->path(),
            'ip'      => $request->ip(),
            'status'  => $response->status(),
        ]);

        return $response;
    }
}
