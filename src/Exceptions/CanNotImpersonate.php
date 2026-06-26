<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

use Illuminate\Contracts\Auth\Authenticatable;
use Mirror\Contracts\Impersonatable;

/** @phpstan-consistent-constructor */
class CanNotImpersonate extends MirrorException implements CannotStartImpersonation
{
    public static function make(Authenticatable $impersonator): static
    {
        return new static(sprintf(
            'The authenticated model [%s] is not allowed to impersonate others. Ensure the model implements [%s] and returns true from [canImpersonate()].',
            $impersonator::class,
            Impersonatable::class,
        ));
    }
}
