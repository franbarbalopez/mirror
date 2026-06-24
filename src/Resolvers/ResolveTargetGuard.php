<?php

declare(strict_types=1);

namespace Mirror\Resolvers;

use Closure;
use Mirror\Guard;
use Mirror\PendingImpersonation;

final class ResolveTargetGuard
{
    public function handle(PendingImpersonation $pending, Closure $next): mixed
    {
        if (! $pending->hasTargetGuard()) {
            $pending->setTargetGuard(Guard::from($pending->target()));

            return $next($pending);
        }

        Guard::ensureUsesSessionDriver($pending->targetGuard());

        return $next($pending);
    }
}
