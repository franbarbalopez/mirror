<?php

namespace Mirror;

use Closure;
use Mirror\Exceptions\ImpersonationAlreadyActive;

final readonly class EnsureImpersonationIsNotStarted
{
    public function __construct(private SessionImpersonationStore $impersonation)
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
