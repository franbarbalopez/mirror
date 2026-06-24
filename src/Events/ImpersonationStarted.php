<?php

declare(strict_types=1);

namespace Mirror\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Queue\SerializesModels;
use Mirror\ImpersonationPayload;

readonly class ImpersonationStarted
{
    use SerializesModels;

    public function __construct(
        public Authenticatable $impersonator,
        public Authenticatable $impersonated,
        public ImpersonationPayload $payload,
    ) {}
}
