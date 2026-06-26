<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

use Illuminate\Contracts\Auth\Authenticatable;

final class CannotInferTargetGuard extends MirrorException implements CannotStartImpersonation
{
    public static function make(Authenticatable $user): self
    {
        return new self(sprintf(
            "Could not infer a guard using Laravel's [session] driver for target [%s]. Pass the target guard explicitly.",
            $user::class,
        ));
    }
}
