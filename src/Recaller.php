<?php

declare(strict_types=1);

namespace Mirror;

use Illuminate\Auth\Recaller as BaseRecaller;
use Illuminate\Auth\SessionGuard;

class Recaller
{
    /**
     * Determine whether the original impersonator should be remembered after login.
     */
    public static function shouldRemember(SessionGuard $guard, int|string $userId): bool
    {
        $recaller = static::resolve($guard);

        return $recaller instanceof BaseRecaller
            && $recaller->valid()
            && $userId == $recaller->id();
    }

    /**
     * Resolve Laravel's remember-me recaller cookie for the given guard.
     */
    protected static function resolve(SessionGuard $guard): ?BaseRecaller
    {
        $recaller = $guard->getRequest()->cookies->get($guard->getRecallerName());

        if (! is_string($recaller)) {
            return null;
        }

        return new BaseRecaller($recaller);
    }
}
