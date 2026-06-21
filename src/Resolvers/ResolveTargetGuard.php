<?php

declare(strict_types=1);

namespace Mirror\Resolvers;

use Closure;
use Mirror\Guard;
use Mirror\ImpersonationStartContext;

final class ResolveTargetGuard
{
    public function handle(ImpersonationStartContext $context, Closure $next): mixed
    {
        if (! $context->hasTargetGuard()) {
            $context->setTargetGuard(Guard::from($context->target()));

            return $next($context);
        }

        Guard::ensureStateful($context->targetGuard());

        return $next($context);
    }
}
