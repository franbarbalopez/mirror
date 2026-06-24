<?php

namespace Mirror\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Mirror\Contracts\Mirror;
use Mirror\Events\ImpersonationExpired;
use Mirror\SessionImpersonationStore;
use Symfony\Component\HttpFoundation\Response;

readonly class CheckImpersonationTtl
{
    public function __construct(
        private Mirror $impersonation,
        private SessionImpersonationStore $store,
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

        /** @var ?int $ttl */
        $ttl = config('mirror.ttl');

        if ($this->store->expired($ttl)) {
            $payload = $this->impersonation->leave();

            ImpersonationExpired::dispatch($payload);

            /** @var RedirectResponse $response */
            $response = redirect((string) config('mirror.redirects.expired'));

            return $response->with('warning', 'Your impersonation session has expired and you have been returned to your original account.');
        }

        return $next($request);
    }
}
