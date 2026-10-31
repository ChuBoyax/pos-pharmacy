<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Usage: ->middleware('role:admin,pharmacist')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user()?->hasRole(...$roles), 403, 'You do not have access to this feature.');

        return $next($request);
    }
}
