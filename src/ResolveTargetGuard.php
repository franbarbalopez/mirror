<?php

declare(strict_types=1);

namespace Mirror;

use Closure;

final class ResolveTargetGuard
{
    public function handle(ImpersonationStartContext $context, Closure $next): mixed
    {
        if ($context->requestedGuard() === null) {
            $context->setTargetGuard(Guard::from($context->target()));

            return $next($context);
        }

        Guard::ensureStateful($context->requestedGuard());

        $context->setTargetGuard($context->requestedGuard());

        return $next($context);
    }
}
