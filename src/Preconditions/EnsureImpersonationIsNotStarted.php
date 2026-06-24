<?php

namespace Mirror\Preconditions;

use Closure;
use Mirror\Exceptions\ImpersonationAlreadyActive;
use Mirror\PendingImpersonation;
use Mirror\SessionImpersonationStore;

final readonly class EnsureImpersonationIsNotStarted
{
    public function __construct(private SessionImpersonationStore $impersonation)
    {
        //
    }

    public function handle(PendingImpersonation $pending, Closure $next): mixed
    {
        if ($this->impersonation->active()) {
            throw ImpersonationAlreadyActive::make();
        }

        return $next($pending);
    }
}
