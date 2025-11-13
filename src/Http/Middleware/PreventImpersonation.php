<?php

namespace Mirror\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mirror\Facades\Mirror;
use Symfony\Component\HttpFoundation\Response;

class PreventImpersonation
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Mirror::isImpersonating()) {
            abort(403, 'This action is not allowed while impersonating another user.');
        }

        return $next($request);
    }
}
