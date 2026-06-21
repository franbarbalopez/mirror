<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

final class ImpersonationNotActive extends MirrorException
{
    public static function make(): self
    {
        return new self('There is no active impersonation to leave.');
    }
}
