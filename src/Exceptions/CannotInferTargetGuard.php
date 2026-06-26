<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

use Illuminate\Contracts\Auth\Authenticatable;

/** @phpstan-consistent-constructor */
class CannotInferTargetGuard extends MirrorException implements CannotStartImpersonation
{
    public static function make(Authenticatable $user): static
    {
        return new static(sprintf(
            "Could not infer a guard using Laravel's [session] driver for target [%s]. Pass the target guard explicitly.",
            $user::class,
        ));
    }
}
