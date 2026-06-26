<?php

declare(strict_types=1);

namespace Mirror;

use Illuminate\Contracts\Session\Session;
use Mirror\Exceptions\CannotReadImpersonationState;
use Mirror\Exceptions\InvalidImpersonationSignature;
use Mirror\Exceptions\MissingImpersonationSignature;

class SessionImpersonationStore
{
    public function __construct(
        protected readonly Session $session,
        protected readonly ImpersonationHasher $hasher,
    ) {}

    public function put(ImpersonationPayload $payload): void
    {
        $this->session->put($this->payloadKey(), $payload->toArray());
        $this->session->put($this->signatureKey(), $this->hasher->sign($payload));
    }

    /**
     * @throws CannotReadImpersonationState
     */
    public function get(): ?ImpersonationPayload
    {
        /** @var null|array{impersonator_id: int|string, impersonator_guard: string, impersonated_id: int|string, impersonated_guard: string, started_at: int, context?: array<string, mixed>} $storedPayload */
        $storedPayload = $this->session->get($this->payloadKey());

        if ($storedPayload === null) {
            return null;
        }

        /** @var ?string $signature */
        $signature = $this->session->get($this->signatureKey());

        if ($signature === null) {
            $this->forget();

            throw MissingImpersonationSignature::make();
        }

        $payload = ImpersonationPayload::fromArray($storedPayload);

        if (! $this->hasher->verify($payload, $signature)) {
            $this->forget();

            throw InvalidImpersonationSignature::make();
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

    /**
     * @throws CannotReadImpersonationState
     */
    public function active(): bool
    {
        return $this->get() instanceof ImpersonationPayload;
    }

    protected function payloadKey(): string
    {
        return config('mirror.session.key').'.payload';
    }

    protected function signatureKey(): string
    {
        return config('mirror.session.key').'.signature';
    }
}
