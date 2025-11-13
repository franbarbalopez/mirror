<?php

namespace Mirror\Http\Middleware;

use Closure;
use Illuminate\Config\Repository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Mirror\Exceptions\ImpersonationException;
use Mirror\Facades\Mirror;
use Mirror\ImpersonationSession;
use Symfony\Component\HttpFoundation\Response;

readonly class CheckImpersonationTtl
{
    public function __construct(
        private ImpersonationSession $impersonationSession,
        private Repository $config
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
        if (Mirror::isImpersonating()) {
            $ttl = $this->config->get('mirror.ttl');

            if ($this->impersonationSession->isExpired($ttl)) {
                $redirectUrl = Mirror::getLeaveRedirectUrl() ?? $this->config->get('mirror.default_redirect_url', '/');

                Mirror::forceStop();

                /** @var RedirectResponse $response */
                $response = redirect($redirectUrl);

                return $response->with('warning', 'Your impersonation session has expired and you have been returned to your original account.');
            }
        }

        return $next($request);
    }
}
