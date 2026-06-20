<?php

namespace Mirror\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mirror\ImpersonationManager;
use Symfony\Component\HttpFoundation\Response;

readonly class PreventImpersonation
{
    public function __construct(
        private ImpersonationManager $impersonation,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->impersonation->active()) {
            abort(403, 'This action is not allowed while impersonating another user.');
        }

        return $next($request);
    }
}
