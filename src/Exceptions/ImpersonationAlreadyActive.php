<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

final class ImpersonationAlreadyActive extends MirrorException implements CannotStartImpersonation
{
    public static function make(): self
    {
        return new self('An impersonation is already active. Leave the current impersonation before starting a new one.');
    }
}
