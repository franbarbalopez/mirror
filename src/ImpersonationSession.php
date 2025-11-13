<?php

namespace Mirror;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;
use Mirror\Exceptions\TamperedSessionException;

readonly class ImpersonationSession
{
    /**
     * The session key prefix for impersonation data.
     */
    private const PREFIX = 'mirror.';

    /**
     * Session key for storing the impersonator's ID.
     */
    private const IMPERSONATED_BY_KEY = self::PREFIX.'impersonated_by';

    /**
     * Session key for the impersonation flag.
     */
    private const IMPERSONATING_KEY = self::PREFIX.'impersonating';

    /**
     * Session key for storing the guard name being used.
     */
    private const GUARD_NAME_KEY = self::PREFIX.'guard_name';

    /**
     * Session key for storing the integrity hash.
     */
    private const INTEGRITY_KEY = self::PREFIX.'integrity';

    /**
     * Session key for storing the start timestamp.
     */
    private const STARTED_AT_KEY = self::PREFIX.'started_at';

    /**
     * Session key for storing the URL to redirect to when leaving impersonation.
     */
    private const LEAVE_REDIRECT_URL_KEY = self::PREFIX.'leave_redirect_url';

    public function __construct(
        private Session $session,
        private string $appKey
    ) {}

    /**
     * Store the impersonator's identifier in the session.
     */
    public function setImpersonator(int|string $impersonatorId): self
    {
        $this->session->put(self::IMPERSONATED_BY_KEY, $impersonatorId);

        return $this;
    }

    /**
     * Get the impersonator's identifier from the session.
     */
    public function getImpersonator(): int|string|null
    {
        return $this->session->get(self::IMPERSONATED_BY_KEY);
    }

    /**
     * Mark the session as currently impersonating.
     */
    public function markAsImpersonating(): self
    {
        $this->session->put(self::IMPERSONATING_KEY, true);

        return $this;
    }

    /**
     * Check if currently impersonating.
     */
    public function isImpersonating(): bool
    {
        return $this->session->get(self::IMPERSONATING_KEY, false) === true;
    }

    /**
     * Clear the impersonation flag from the session.
     */
    public function clearImpersonatingFlag(): void
    {
        $this->session->forget(self::IMPERSONATING_KEY);
    }

    /**
     * Clear the impersonator identifier from the session.
     */
    public function clearImpersonator(): void
    {
        $this->session->forget(self::IMPERSONATED_BY_KEY);
    }

    /**
     * Store the guard name being used for impersonation.
     */
    public function setGuardName(string $guardName): self
    {
        $this->session->put(self::GUARD_NAME_KEY, $guardName);

        return $this;
    }

    /**
     * Get the guard name used for impersonation.
     */
    public function getGuardName(): ?string
    {
        return $this->session->get(self::GUARD_NAME_KEY);
    }

    /**
     * Clear the guard name from the session.
     */
    public function clearGuardName(): void
    {
        $this->session->forget(self::GUARD_NAME_KEY);
    }

    /**
     * Clear all impersonation data from the session.
     */
    public function clear(): void
    {
        $this->clearImpersonatingFlag();
        $this->clearImpersonator();
        $this->clearGuardName();
        $this->clearIntegrityHash();
        $this->clearStartedAt();
        $this->clearLeaveRedirectUrl();
    }

    /**
     * Generate and store the integrity hash for the current session data.
     */
    public function generateIntegrityHash(): self
    {
        $payload = $this->buildIntegrityPayload();
        $hash = hash_hmac('sha256', $payload, $this->appKey);

        $this->session->put(self::INTEGRITY_KEY, $hash);

        return $this;
    }

    /**
     * Verify the integrity of the session data.
     *
     * @throws TamperedSessionException
     */
    public function verifyIntegrity(): void
    {
        if (! $this->isImpersonating()) {
            return;
        }

        $storedHash = $this->session->get(self::INTEGRITY_KEY);

        if (! $storedHash) {
            $this->clear();

            throw TamperedSessionException::detected();
        }

        $expectedHash = hash_hmac('sha256', $this->buildIntegrityPayload(), $this->appKey);

        if (! hash_equals($storedHash, $expectedHash)) {
            $this->clear();

            throw TamperedSessionException::detected();
        }
    }

    /**
     * Build the payload for integrity hash verification.
     */
    protected function buildIntegrityPayload(): string
    {
        return json_encode([
            'impersonator' => $this->getImpersonator(),
            'guard_name' => $this->getGuardName(),
            'started_at' => $this->getStartedAt(),
            'leave_redirect_url' => $this->getLeaveRedirectUrl(),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Clear the integrity hash from the session.
     */
    protected function clearIntegrityHash(): void
    {
        $this->session->forget(self::INTEGRITY_KEY);
    }

    /**
     * Set the impersonation start timestamp.
     */
    public function setStartedAt(int $timestamp): self
    {
        $this->session->put(self::STARTED_AT_KEY, $timestamp);

        return $this;
    }

    /**
     * Get the impersonation start timestamp.
     */
    public function getStartedAt(): ?int
    {
        /** @var ?int $value */
        $value = $this->session->get(self::STARTED_AT_KEY);

        return $value;
    }

    /**
     * Clear the start timestamp from the session.
     */
    protected function clearStartedAt(): void
    {
        $this->session->forget(self::STARTED_AT_KEY);
    }

    /**
     * Check if the impersonation has expired based on TTL.
     */
    public function isExpired(?int $ttl): bool
    {
        if ($ttl === null || ! $this->isImpersonating()) {
            return false;
        }

        $startedAt = $this->getStartedAt();

        if ($startedAt === null) {
            return true;
        }

        /** @var int $timestamp */
        $timestamp = Carbon::now()->timestamp;

        return ($timestamp - $startedAt) > $ttl;
    }

    /**
     * Set the URL to redirect to when leaving impersonation.
     */
    public function setLeaveRedirectUrl(?string $url): self
    {
        if ($url !== null) {
            $this->session->put(self::LEAVE_REDIRECT_URL_KEY, $url);
        }

        return $this;
    }

    /**
     * Get the URL to redirect to when leaving impersonation.
     */
    public function getLeaveRedirectUrl(): ?string
    {
        return $this->session->get(self::LEAVE_REDIRECT_URL_KEY);
    }

    /**
     * Clear the leave redirect URL from the session.
     */
    protected function clearLeaveRedirectUrl(): void
    {
        $this->session->forget(self::LEAVE_REDIRECT_URL_KEY);
    }
}
