<?php

declare(strict_types=1);

namespace Mirror;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 *
 * @phpstan-consistent-constructor
 */
class ImpersonationPayload implements Arrayable
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly int|string $impersonatorId,
        public readonly string $impersonatorGuard,
        public readonly int|string $impersonatedId,
        public readonly string $impersonatedGuard,
        public readonly int $startedAt,
        public readonly array $context = [],
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
    public static function fromSessionPayload(array $payload): static
    {
        return new static(
            impersonatorId: $payload['impersonator_id'],
            impersonatorGuard: $payload['impersonator_guard'],
            impersonatedId: $payload['impersonated_id'],
            impersonatedGuard: $payload['impersonated_guard'],
            startedAt: $payload['started_at'],
            context: $payload['context'] ?? [],
        );
    }
}
