<?php

namespace Mirror\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mirror\ImpersonationManager;
use Symfony\Component\HttpFoundation\Response;

readonly class RequireImpersonation
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
        if (! $this->impersonation->active()) {
            abort(403, 'This action requires active impersonation.');
        }

        return $next($request);
    }
}
