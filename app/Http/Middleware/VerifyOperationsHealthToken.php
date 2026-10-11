<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyOperationsHealthToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('operations.health_token');
        $provided = $request->bearerToken();

        if (! is_string($configured)
            || strlen($configured) < 32
            || ! is_string($provided)
            || ! hash_equals($configured, $provided)) {
            abort(404);
        }

        return $next($request);
    }
}
