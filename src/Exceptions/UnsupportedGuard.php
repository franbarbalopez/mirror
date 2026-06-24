<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;

final class UnsupportedGuard extends MirrorException
{
    public static function cannotInferFor(Authenticatable $user): self
    {
        return new self(sprintf(
            'Could not infer a Laravel session guard for target [%s]. Pass the target guard explicitly.',
            $user::class,
        ));
    }

    public static function notSession(string $guard): self
    {
        return new self(sprintf(
            'The [%s] guard is not supported because it is not a Laravel session guard. Supported guards must be backed by [%s].',
            $guard,
            SessionGuard::class,
        ));
    }

    public static function noAuthenticatedSessionGuard(): self
    {
        return new self('Could not find an authenticated Laravel session guard for the impersonator. Authenticate the impersonator with a session guard before starting impersonation.');
    }
}
