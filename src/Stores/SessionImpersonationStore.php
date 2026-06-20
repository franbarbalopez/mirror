<?php

declare(strict_types=1);

namespace Mirror\Stores;

use Illuminate\Contracts\Session\Session;
use Mirror\Contracts\ImpersonationStore;
use Mirror\Data\ImpersonationPayload;
use Mirror\Exceptions\TamperedImpersonationState;
use Mirror\Support\ImpersonationHasher;

final readonly class SessionImpersonationStore implements ImpersonationStore
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

            throw new TamperedImpersonationState('Impersonation session data has been tampered with. For security reasons, the session has been cleared.');
        }

        $payload = ImpersonationPayload::fromArray($storedPayload);

        if (! $this->hasher->verify($payload, $signature)) {
            $this->forget();

            throw new TamperedImpersonationState('Impersonation session data has been tampered with. For security reasons, the session has been cleared.');
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

    private function payloadKey(): string
    {
        return config('mirror.session.key', 'mirror.impersonation').'.payload';
    }

    private function signatureKey(): string
    {
        return config('mirror.session.key', 'mirror.impersonation').'.signature';
    }
}
