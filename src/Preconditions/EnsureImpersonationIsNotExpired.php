<?php

declare(strict_types=1);

namespace Mirror\Preconditions;

use Closure;
use Mirror\Contracts\Mirror;
use Mirror\Exceptions\ImpersonationExpired;
use Mirror\SessionImpersonationStore;

final readonly class EnsureImpersonationIsNotExpired
{
    public function __construct(
        private Mirror $impersonation,
        private SessionImpersonationStore $store,
    ) {}

    public function handle(bool $force, Closure $next): mixed
    {
        if (! $force && $this->impersonation->expired()) {
            $this->store->forget();

            throw new ImpersonationExpired('The impersonation session has expired.');
        }

        return $next($force);
    }
}
