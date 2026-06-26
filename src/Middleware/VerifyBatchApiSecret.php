<?php

namespace BatchApi\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBatchApiSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('inference.api_secret');

        // Not configured = open (local dev). Configured = require a matching secret.
        if ($secret === null || $secret === '') {
            return $next($request);
        }

        $provided = $request->header('X-Batch-Api-Key') ?? $request->bearerToken();

        if (! is_string($provided) || ! hash_equals((string) $secret, $provided)) {
            abort(401, 'Invalid or missing API secret.');
        }

        return $next($request);
    }
}
