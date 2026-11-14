<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePermission
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->is_active, 403, 'This account is inactive.');
        abort_unless($request->user()->canAccessRoute((string) $request->route()->getName()), 403, 'Your role does not have permission for this action.');

        return $next($request);
    }
}
