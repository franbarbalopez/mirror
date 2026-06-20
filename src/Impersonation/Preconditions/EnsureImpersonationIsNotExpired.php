<?php

declare(strict_types=1);

namespace Mirror\Impersonation\Preconditions;

use Closure;
use Mirror\Contracts\ImpersonationStore;
use Mirror\Exceptions\ImpersonationExpired;
use Mirror\ImpersonationManager;
use Mirror\ImpersonationStopContext;

final readonly class EnsureImpersonationIsNotExpired
{
    public function __construct(
        private ImpersonationManager $impersonation,
        private ImpersonationStore $store,
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
