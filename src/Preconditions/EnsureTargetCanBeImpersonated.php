<?php

declare(strict_types=1);

namespace Mirror\Preconditions;

use Closure;
use Mirror\Contracts\Impersonatable;
use Mirror\Exceptions\CanNotBeImpersonated;
use Mirror\PendingImpersonation;

final class EnsureTargetCanBeImpersonated
{
    public function handle(PendingImpersonation $pending, Closure $next): mixed
    {
        if (! $pending->target() instanceof Impersonatable || ! $pending->target()->canBeImpersonated()) {
            throw CanNotBeImpersonated::make($pending->target());
        }

        return $next($pending);
    }
}
