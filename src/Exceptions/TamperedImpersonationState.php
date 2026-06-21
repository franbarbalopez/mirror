<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

final class TamperedImpersonationState extends MirrorException
{
    public static function missingSignature(): self
    {
        return new self('The impersonation session signature is missing. The impersonation state has been cleared for security reasons.');
    }

    public static function invalidSignature(): self
    {
        return new self('The impersonation session signature is invalid. The impersonation state has been cleared for security reasons.');
    }
}
