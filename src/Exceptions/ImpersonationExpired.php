<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

final class ImpersonationExpired extends MirrorException
{
    public static function make(): self
    {
        return new self('The impersonation session has expired. Use [forceLeave()] to leave an expired impersonation safely.');
    }
}
