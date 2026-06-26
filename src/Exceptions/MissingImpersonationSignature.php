<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

/** @phpstan-consistent-constructor */
class MissingImpersonationSignature extends MirrorException implements CannotLeaveImpersonation, CannotReadImpersonationState
{
    public static function make(): static
    {
        return new static('The impersonation session signature is missing. The impersonation state has been cleared for security reasons.');
    }
}
