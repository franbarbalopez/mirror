<?php

declare(strict_types=1);

namespace Mirror;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Mirror\Exceptions\UnsupportedGuard;

final class ResolveImpersonatorGuard
{
    public function handle(ImpersonationStartContext $context, Closure $next): mixed
    {
        $guard = Guard::authenticated();

        if ($guard === null) {
            throw new UnsupportedGuard('Impersonation is only allowed for guards that uses session driver.');
        }

        $context->setImpersonatorGuard($guard);

        /** @var Authenticatable $impersonator */
        $impersonator = auth($guard)->user();
        $context->setImpersonator($impersonator);

        return $next($context);
    }
}
