<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

use Illuminate\Contracts\Auth\Authenticatable;
use Mirror\Contracts\Impersonatable;

final class CanNotBeImpersonated extends MirrorException
{
    public static function targetIsNotAllowed(Authenticatable $target): self
    {
        return new self(sprintf(
            'The target user [%s] cannot be impersonated. Ensure the model implements [%s] and returns true from [canBeImpersonated()].',
            $target::class,
            Impersonatable::class,
        ));
    }
}
