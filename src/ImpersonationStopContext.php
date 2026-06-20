<?php

declare(strict_types=1);

namespace Mirror;

final readonly class ImpersonationStopContext
{
    public function __construct(private bool $force = false) {}

    public function force(): bool
    {
        return $this->force;
    }
}
