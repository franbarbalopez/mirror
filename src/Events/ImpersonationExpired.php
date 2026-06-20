<?php

declare(strict_types=1);

namespace Mirror\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Mirror\ImpersonationPayload;

final class ImpersonationExpired
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public ImpersonationPayload $payload,
    ) {}
}
