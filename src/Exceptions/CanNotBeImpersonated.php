<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

use Illuminate\Contracts\Auth\Authenticatable;
use Mirror\Contracts\Impersonatable;

/** @phpstan-consistent-constructor */
class CanNotBeImpersonated extends MirrorException implements CannotStartImpersonation
{
    public static function make(Authenticatable $target): static
    {
        return new static(sprintf(
            'The target model [%s] cannot be impersonated. Ensure the model implements [%s] and returns true from [canBeImpersonated()].',
            $target::class,
            Impersonatable::class,
        ));
    }
}
