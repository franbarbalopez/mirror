<?php

declare(strict_types=1);

namespace Mirror\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Queue\SerializesModels;

class ImpersonationStarted
{
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly Authenticatable $impersonator,
        public readonly Authenticatable $impersonated,
        public readonly array $context,
    ) {}
}
