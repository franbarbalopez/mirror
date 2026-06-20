<?php

namespace Mirror\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Mirror\Contracts\Mirror;
use Mirror\Events\ImpersonationExpired;
use Mirror\ImpersonationPayload;
use Symfony\Component\HttpFoundation\Response;

readonly class CheckImpersonationTtl
{
    public function __construct(
        private Mirror $impersonation
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->impersonation->active()) {
            return $next($request);
        }

        if ($this->impersonation->expired()) {
            $payload = $this->impersonation->payload();
            $redirectUrl = $this->impersonation->leaveUrl()
                ?? $this->impersonation->expiredRedirectUrl();

            $this->impersonation->forceLeave();

            if ($payload instanceof ImpersonationPayload) {
                ImpersonationExpired::dispatch($payload);
            }

            /** @var RedirectResponse $response */
            $response = redirect($redirectUrl);

            return $response->with('warning', 'Your impersonation session has expired and you have been returned to your original account.');
        }

        return $next($request);
    }
}
