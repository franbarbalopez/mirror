<?php

namespace Mirror\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mirror\Facades\Mirror;
use Symfony\Component\HttpFoundation\Response;

class RequireImpersonation
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Mirror::isImpersonating()) {
            abort(403, 'This action requires active impersonation.');
        }

        return $next($request);
    }
}
