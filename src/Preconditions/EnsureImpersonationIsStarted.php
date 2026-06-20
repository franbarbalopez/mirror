<?php

declare(strict_types=1);

namespace Mirror\Preconditions;

use Closure;
use Mirror\Exceptions\ImpersonationNotActive;
use Mirror\SessionImpersonationStore;

final readonly class EnsureImpersonationIsStarted
{
    public function __construct(private SessionImpersonationStore $impersonation)
    {
        //
    }

    public function handle(bool $force, Closure $next): mixed
    {
        if (! $this->impersonation->active()) {
            throw new ImpersonationNotActive('You are not impersonating any user.');
        }

        return $next($force);
    }
}
