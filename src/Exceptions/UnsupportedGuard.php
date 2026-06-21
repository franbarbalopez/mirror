<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

use Illuminate\Contracts\Auth\Authenticatable;

final class UnsupportedGuard extends MirrorException
{
    public static function cannotInferFor(Authenticatable $user): self
    {
        return new self(sprintf(
            'Mirror could not infer a stateful session guard for target [%s]. Pass the target guard explicitly with [Mirror::impersonate($user, guard: ...)].',
            $user::class,
        ));
    }

    public static function notStateful(string $guard): self
    {
        return new self(sprintf(
            'The [%s] guard is not supported because it is not a stateful session guard. Mirror only supports Laravel session guards.',
            $guard,
        ));
    }

    public static function noAuthenticatedSessionGuard(): self
    {
        return new self('Mirror could not find an authenticated stateful session guard for the impersonator. Authenticate the user with a session guard before starting impersonation.');
    }
}
