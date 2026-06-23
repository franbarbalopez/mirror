<?php

declare(strict_types=1);

namespace Mirror;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class ImpersonationPayload implements Arrayable
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public int|string $impersonatorId,
        public string $impersonatorGuard,
        public int|string $impersonatedId,
        public string $impersonatedGuard,
        public int $startedAt,
        public array $context = [],
    ) {}

    /**
     * @return array{impersonator_id: int|string, impersonator_guard: string, impersonated_id: int|string, impersonated_guard: string, started_at: int, context: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'impersonator_id' => $this->impersonatorId,
            'impersonator_guard' => $this->impersonatorGuard,
            'impersonated_id' => $this->impersonatedId,
            'impersonated_guard' => $this->impersonatedGuard,
            'started_at' => $this->startedAt,
            'context' => $this->context,
        ];
    }

    /**
     * @param  array{impersonator_id: int|string, impersonator_guard: string, impersonated_id: int|string, impersonated_guard: string, started_at: int, context?: array<string, mixed>}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            impersonatorId: $payload['impersonator_id'],
            impersonatorGuard: $payload['impersonator_guard'],
            impersonatedId: $payload['impersonated_id'],
            impersonatedGuard: $payload['impersonated_guard'],
            startedAt: $payload['started_at'],
            context: $payload['context'] ?? [],
        );
    }
}
