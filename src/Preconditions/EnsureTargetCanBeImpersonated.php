<?php

declare(strict_types=1);

namespace Mirror\Preconditions;

use Closure;
use Mirror\Contracts\Impersonatable;
use Mirror\Exceptions\CanNotBeImpersonated;
use Mirror\ImpersonationStartContext;

final class EnsureTargetCanBeImpersonated
{
    public function handle(ImpersonationStartContext $context, Closure $next): mixed
    {
        if (! $context->target() instanceof Impersonatable || ! $context->target()->canBeImpersonated()) {
            throw new CanNotBeImpersonated("This user can't be impersonated.");
        }

        return $next($context);
    }
}
