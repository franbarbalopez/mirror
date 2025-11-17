<?php

namespace Mirror\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Mirror\Exceptions\ImpersonationException;
use Mirror\Impersonator;
use Symfony\Component\HttpFoundation\Response;

readonly class CheckImpersonationTtl
{
    public function __construct(
        private Impersonator $impersonator
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws ImpersonationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->impersonator->isImpersonating()) {
            return $next($request);
        }

        if ($this->impersonator->isExpired()) {
            $redirectUrl = $this->impersonator->getLeaveRedirectUrl()
                ?? $this->impersonator->getDefaultRedirectUrl();

            $this->impersonator->forceStop();

            /** @var RedirectResponse $response */
            $response = redirect($redirectUrl);

            return $response->with('warning', 'Your impersonation session has expired and you have been returned to your original account.');
        }

        return $next($request);
    }
}
