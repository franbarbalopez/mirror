<?php

declare(strict_types=1);

namespace Mirror;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;
use Mirror\Exceptions\TamperedImpersonationState;

final readonly class SessionImpersonationStore
{
    public function __construct(
        private Session $session,
        private ImpersonationHasher $hasher,
    ) {}

    public function put(ImpersonationPayload $payload): void
    {
        $this->session->put($this->payloadKey(), $payload->toArray());
        $this->session->put($this->signatureKey(), $this->hasher->sign($payload));
    }

    public function get(): ?ImpersonationPayload
    {
        /** @var null|array{impersonator_id: int|string, impersonator_guard: string, impersonated_id: int|string, impersonated_guard: string, started_at: int, leave_url?: ?string} $storedPayload */
        $storedPayload = $this->session->get($this->payloadKey());

        if ($storedPayload === null) {
            return null;
        }

        /** @var ?string $signature */
        $signature = $this->session->get($this->signatureKey());

        if ($signature === null) {
            $this->forget();

            throw TamperedImpersonationState::missingSignature();
        }

        $payload = ImpersonationPayload::fromArray($storedPayload);

        if (! $this->hasher->verify($payload, $signature)) {
            $this->forget();

            throw TamperedImpersonationState::invalidSignature();
        }

        return $payload;
    }

    public function forget(): void
    {
        $this->session->forget([
            $this->payloadKey(),
            $this->signatureKey(),
        ]);
    }

    public function active(): bool
    {
        return $this->session->has($this->payloadKey());
    }

    public function expired(?int $ttl): bool
    {
        if ($ttl === null) {
            return false;
        }

        $payload = $this->get();

        if (! $payload instanceof ImpersonationPayload) {
            return false;
        }

        return ((int) Carbon::now()->timestamp - $payload->startedAt) > $ttl;
    }

    private function payloadKey(): string
    {
        return config('mirror.session.key').'.payload';
    }

    private function signatureKey(): string
    {
        return config('mirror.session.key').'.signature';
    }
}
