<?php

declare(strict_types=1);

namespace Mirror;

use Closure;
use Mirror\Contexts\ImpersonationStopContext;
use Mirror\Contracts\Mirror;
use Mirror\Exceptions\ImpersonationExpired;

final readonly class EnsureImpersonationIsNotExpired
{
    public function __construct(
        private Mirror $impersonation,
        private SessionImpersonationStore $store,
    ) {}

    public function handle(ImpersonationStopContext $context, Closure $next): mixed
    {
        if (! $context->force() && $this->impersonation->expired()) {
            $this->store->forget();

            throw new ImpersonationExpired('The impersonation session has expired.');
        }

        return $next($context);
    }
}
