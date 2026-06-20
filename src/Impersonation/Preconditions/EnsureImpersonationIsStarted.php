<?php

declare(strict_types=1);

namespace Mirror\Impersonation\Preconditions;

use Closure;
use Mirror\Contexts\ImpersonationStopContext;
use Mirror\Exceptions\ImpersonationNotActive;
use Mirror\SessionImpersonationStore;

final readonly class EnsureImpersonationIsStarted
{
    public function __construct(private SessionImpersonationStore $impersonation)
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
