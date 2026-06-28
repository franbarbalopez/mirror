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
        protected Session $session,
        protected ImpersonationHasher $hasher,
    ) {}

    /**
     * Store the payload and its signature in the session.
     */
    public function put(ImpersonationPayload $payload): void
    {
        $this->session->put($this->payloadKey(), $payload->toArray());
        $this->session->put($this->signatureKey(), $this->hasher->sign($payload));
    }

    /**
     * Retrieve and verify the payload stored in the session.
     *
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

        $payload = ImpersonationPayload::fromSessionPayload($storedPayload);

        if (! $this->hasher->verify($payload, $signature)) {
            $this->forget();

            throw InvalidImpersonationSignature::make();
        }

        return $payload;
    }

    /**
     * Remove all impersonation state from the session.
     */
    public function forget(): void
    {
        $this->session->forget([
            $this->payloadKey(),
            $this->signatureKey(),
        ]);
    }

    /**
     * Determine whether the session contains a valid impersonation payload.
     *
     * @throws CannotReadImpersonationState
     */
    public function active(): bool
    {
        return $this->get() instanceof ImpersonationPayload;
    }

    /**
     * Return the configured session key used for the payload data.
     */
    protected function payloadKey(): string
    {
        return config('mirror.session.key').'.payload';
    }

    /**
     * Return the configured session key used for the payload signature.
     */
    protected function signatureKey(): string
    {
        return config('mirror.session.key').'.signature';
    }
}
