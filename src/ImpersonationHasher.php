<?php

declare(strict_types=1);

namespace Mirror;

use JsonException;

final readonly class ImpersonationHasher
{
    /**
     * @throws JsonException
     */
    public function sign(ImpersonationPayload $payload): string
    {
        return hash_hmac('sha256', $this->serialize($payload), (string) config('app.key'));
    }

    public function verify(ImpersonationPayload $payload, string $signature): bool
    {
        return hash_equals($this->sign($payload), $signature);
    }

    /**
     * @throws JsonException
     */
    private function serialize(ImpersonationPayload $payload): string
    {
        return json_encode($payload->toArray(), JSON_THROW_ON_ERROR);
    }
}
