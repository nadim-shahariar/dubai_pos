<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user()?->canAccessRoute((string) $request->route()->getName()), 403, 'Your role does not have permission for this page.');

        return $next($request);
    }
}
