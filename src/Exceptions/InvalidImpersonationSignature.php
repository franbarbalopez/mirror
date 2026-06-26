<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

final class InvalidImpersonationSignature extends MirrorException implements CannotLeaveImpersonation, CannotReadImpersonationState
{
    public static function make(): self
    {
        return new self('The impersonation session signature is invalid. The impersonation state has been cleared for security reasons.');
    }
}
