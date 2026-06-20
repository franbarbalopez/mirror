<?php

namespace Mirror\Impersonation\Preconditions;

use Closure;
use Mirror\Contracts\ImpersonationStore;
use Mirror\Exceptions\ImpersonationAlreadyActive;
use Mirror\ImpersonationStartContext;

final readonly class EnsureImpersonationIsNotStarted
{
    public function __construct(private ImpersonationStore $impersonation)
    {
        //
    }

    public function handle(ImpersonationStartContext $context, Closure $next): mixed
    {
        if ($this->impersonation->active()) {
            throw new ImpersonationAlreadyActive('You are already impersonating a user. Stop the current impersonation before starting a new one.');
        }

        return $next($context);
    }
}
