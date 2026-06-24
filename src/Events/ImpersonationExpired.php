<?php

declare(strict_types=1);

namespace Mirror\Events;

use Mirror\ImpersonationPayload;

readonly class ImpersonationExpired
{
    public function __construct(
        public ImpersonationPayload $payload,
    ) {}
}
