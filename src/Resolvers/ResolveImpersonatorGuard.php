<?php

declare(strict_types=1);

namespace Mirror\Resolvers;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Mirror\Exceptions\UnsupportedGuard;
use Mirror\Guard;
use Mirror\PendingImpersonation;

final class ResolveImpersonatorGuard
{
    public function handle(PendingImpersonation $pending, Closure $next): mixed
    {
        $guard = Guard::authenticated();

        if ($guard === null) {
            throw UnsupportedGuard::noAuthenticatedSessionDriverGuard();
        }

        $pending->setImpersonatorGuard($guard);

        /** @var Authenticatable $impersonator */
        $impersonator = auth($guard)->user();
        $pending->setImpersonator($impersonator);

        return $next($pending);
    }
}
