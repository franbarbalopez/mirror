<?php

declare(strict_types=1);

namespace Mirror;

use Illuminate\Auth\Recaller as BaseRecaller;
use Illuminate\Auth\SessionGuard;

final class Recaller
{
    public static function shouldRemember(SessionGuard $guard, int|string $userId): bool
    {
        $recaller = self::resolve($guard);

        return $recaller instanceof BaseRecaller
            && $recaller->valid()
            && $userId == $recaller->id();
    }

    private static function resolve(SessionGuard $guard): ?BaseRecaller
    {
        $recaller = $guard->getRequest()->cookies->get($guard->getRecallerName());

        if (! is_string($recaller)) {
            return null;
        }

        return new BaseRecaller($recaller);
    }
}
