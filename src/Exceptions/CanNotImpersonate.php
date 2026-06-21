<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

use Illuminate\Contracts\Auth\Authenticatable;
use Mirror\Contracts\Impersonatable;

final class CanNotImpersonate extends MirrorException
{
    public static function userIsNotAllowed(Authenticatable $impersonator): self
    {
        return new self(sprintf(
            'The authenticated user [%s] is not allowed to impersonate other users. Ensure the model implements [%s] and returns true from [canImpersonate()].',
            $impersonator::class,
            Impersonatable::class,
        ));
    }
}
