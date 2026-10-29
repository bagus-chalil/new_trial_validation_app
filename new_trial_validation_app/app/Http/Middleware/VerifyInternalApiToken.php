<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards `/api/internal/*`, the server-to-server endpoints sibling apps on the same host call.
 * Fails closed: with no token configured, or a caller outside the allowed IPs (loopback by
 * default), or a wrong `X-Internal-Token`, the request is rejected.
 */
class VerifyInternalApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('services.internal_api.token');

        if ($token === '') {
            abort(503, 'Internal API is not configured.');
        }

        $allowedIps = array_filter(array_map('trim', explode(',', (string) config('services.internal_api.allowed_ips'))));

        if (! in_array($request->ip(), $allowedIps, true)) {
            abort(403);
        }

        if (! hash_equals($token, (string) $request->header('X-Internal-Token'))) {
            abort(401);
        }

        return $next($request);
    }
}
