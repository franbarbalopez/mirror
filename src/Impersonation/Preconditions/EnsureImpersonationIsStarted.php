<?php

declare(strict_types=1);

namespace Mirror\Impersonation\Preconditions;

use Closure;
use Mirror\Contracts\ImpersonationStore;
use Mirror\Exceptions\ImpersonationNotActive;
use Mirror\ImpersonationStopContext;

final readonly class EnsureImpersonationIsStarted
{
    public function __construct(private ImpersonationStore $impersonation)
    {
        //
    }

    public function handle(ImpersonationStopContext $context, Closure $next): mixed
    {
        if (! $this->impersonation->active()) {
            throw new ImpersonationNotActive('You are not impersonating any user.');
        }

        return $next($context);
    }
}
