<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

use Illuminate\Auth\SessionGuard;

/** @phpstan-consistent-constructor */
class GuardDoesNotUseSessionDriver extends MirrorException implements CannotStartImpersonation
{
    public static function make(string $guard): static
    {
        return new static(sprintf(
            "The [%s] guard is not supported because it does not use Laravel's [session] driver. Supported guards must be backed by [%s].",
            $guard,
            SessionGuard::class,
        ));
    }
}
