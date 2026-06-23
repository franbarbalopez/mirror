<?php

declare(strict_types=1);

namespace Mirror;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, int|string|null>
 */
final readonly class ImpersonationPayload implements Arrayable
{
    public function __construct(
        public int|string $impersonatorId,
        public string $impersonatorGuard,
        public int|string $impersonatedId,
        public string $impersonatedGuard,
        public int $startedAt,
        public ?string $leaveUrl = null,
    ) {}

    /**
     * @return array{impersonator_id: int|string, impersonator_guard: string, impersonated_id: int|string, impersonated_guard: string, started_at: int, leave_url: ?string}
     */
    public function toArray(): array
    {
        return [
            'impersonator_id' => $this->impersonatorId,
            'impersonator_guard' => $this->impersonatorGuard,
            'impersonated_id' => $this->impersonatedId,
            'impersonated_guard' => $this->impersonatedGuard,
            'started_at' => $this->startedAt,
            'leave_url' => $this->leaveUrl,
        ];
    }

    /**
     * @param  array{impersonator_id: int|string, impersonator_guard: string, impersonated_id: int|string, impersonated_guard: string, started_at: int, leave_url?: ?string}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            impersonatorId: $payload['impersonator_id'],
            impersonatorGuard: $payload['impersonator_guard'],
            impersonatedId: $payload['impersonated_id'],
            impersonatedGuard: $payload['impersonated_guard'],
            startedAt: $payload['started_at'],
            leaveUrl: $payload['leave_url'] ?? null,
        );
    }
}
