<?php

namespace Mirror;

use Illuminate\Auth\AuthManager;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Mirror\Events\ImpersonationStarted;
use Mirror\Events\ImpersonationStopped;
use Mirror\Exceptions\ImpersonationException;
use Mirror\Exceptions\TamperedSessionException;

readonly class Impersonator
{
    public function __construct(
        private AuthManager $auth,
        private ImpersonationSession $impersonationSession,
        private Repository $config,
        private Request $request,
    ) {}

    /**
     * Get the name of the guard currently being used.
     */
    protected function getCurrentGuardName(): string
    {
        /** @var array<string> $guards */
        $guards = $this->config->get('auth.guards', []);

        foreach (array_keys($guards) as $guardName) {
            if ($this->auth->guard($guardName)->check()) {
                return $guardName;
            }
        }

        return $this->auth->getDefaultDriver();
    }

    /**
     * Get the authenticatable class name.
     */
    protected function getAuthenticatableClass(): string
    {
        $defaultGuard = $this->config->get('auth.defaults.guard');
        $guardProvider = $this->config->get(sprintf('auth.guards.%s.provider', $defaultGuard));

        return $this->config->get(sprintf('auth.providers.%s.model', $guardProvider));
    }

    /**
     * Start impersonating another user.
     *
     * @throws ImpersonationException
     */
    public function start(Authenticatable $user, ?string $leaveRedirectUrl = null, ?string $startRedirectUrl = null): ?string
    {
        $this->ensureImpersonationIsEnabled();
        $this->ensureNotAlreadyImpersonating();

        $guardName = $this->getCurrentGuardName();
        $guard = $this->auth->guard($guardName);
        /** @var Authenticatable $impersonator */
        $impersonator = $guard->user();

        $this->ensureCanImpersonate($impersonator);
        $this->ensureCanBeImpersonated($user);

        $this->impersonationSession
            ->setGuardName($guardName)
            ->setImpersonator($impersonator->getAuthIdentifier())
            ->setStartedAt((int) Carbon::now()->timestamp)
            ->setLeaveRedirectUrl($leaveRedirectUrl ?? $this->request->fullUrl())
            ->markAsImpersonating()
            ->generateIntegrityHash();

        $guard->login($user);

        ImpersonationStarted::dispatch($impersonator, $user, $guardName);

        return $startRedirectUrl;
    }

    /**
     * Stop impersonating and restore the original user.
     *
     * @throws ImpersonationException
     * @throws TamperedSessionException
     */
    public function stop(): void
    {
        $this->ensureIsImpersonating();
        $this->impersonationSession->verifyIntegrity();
        $this->ensureNotExpired();

        $this->performStop();
    }

    /**
     * Force stop impersonation without checking TTL.
     *
     * @throws ImpersonationException
     * @throws TamperedSessionException
     */
    public function forceStop(): void
    {
        $this->ensureIsImpersonating();
        $this->impersonationSession->verifyIntegrity();

        $this->performStop();
    }

    /**
     * Perform the actual stop operation - restore the original user.
     */
    protected function performStop(): void
    {
        $session = $this->impersonationSession;
        $impersonatorId = $session->getImpersonator();
        /** @var string $guardName */
        $guardName = $session->getGuardName();

        $guard = $this->auth->guard($guardName);
        /** @var Authenticatable $impersonatedUser */
        $impersonatedUser = $guard->user();

        $session->clear();

        $guard->logout();
        $guard->loginUsingId($impersonatorId);

        /** @var Authenticatable $impersonatorUser */
        $impersonatorUser = $guard->user();

        ImpersonationStopped::dispatch($impersonatorUser, $impersonatedUser, $guardName);
    }

    /**
     * Check if currently impersonating another user.
     */
    public function isImpersonating(): bool
    {
        return $this->impersonationSession->isImpersonating();
    }

    /**
     * Get the original user who initiated the impersonation.
     */
    public function getImpersonator(): ?Authenticatable
    {
        $impersonatorId = $this->impersonationSession->getImpersonator();

        if (! $impersonatorId) {
            return null;
        }

        $class = $this->getAuthenticatableClass();

        return $class::find($impersonatorId);
    }

    /**
     * Start impersonating a user by their primary key.
     *
     * @throws ImpersonationException
     * @throws \InvalidArgumentException
     */
    public function startByKey(int|string $key, ?string $leaveRedirectUrl = null, ?string $startRedirectUrl = null): ?string
    {
        $class = $this->getAuthenticatableClass();

        $user = $class::find($key);

        if (! $user) {
            throw new \InvalidArgumentException(sprintf('User with key [%s] not found.', $key));
        }

        return $this->start($user, $leaveRedirectUrl, $startRedirectUrl);
    }

    /**
     * Start impersonating a user by their email address.
     *
     * @throws ImpersonationException
     * @throws \InvalidArgumentException
     */
    public function startByEmail(string $email, ?string $leaveRedirectUrl = null, ?string $startRedirectUrl = null): ?string
    {
        $class = $this->getAuthenticatableClass();

        $user = $class::query()->where('email', $email)->first();

        if (! $user) {
            throw new \InvalidArgumentException(sprintf('User with email [%s] not found.', $email));
        }

        return $this->start($user, $leaveRedirectUrl, $startRedirectUrl);
    }

    /**
     * Get the impersonator's ID.
     */
    public function impersonatorId(): int|string|null
    {
        return $this->impersonationSession->getImpersonator();
    }

    /**
     * Alias for start().
     *
     * @throws ImpersonationException
     */
    public function as(Authenticatable $user, ?string $leaveRedirectUrl = null, ?string $startRedirectUrl = null): ?string
    {
        return $this->start($user, $leaveRedirectUrl, $startRedirectUrl);
    }

    /**
     * Alias for stop().
     *
     * @throws ImpersonationException
     * @throws TamperedSessionException
     */
    public function leave(): void
    {
        $this->stop();
    }

    /**
     * Alias for isImpersonating().
     */
    public function impersonating(): bool
    {
        return $this->isImpersonating();
    }

    /**
     * Alias for getImpersonator().
     */
    public function impersonator(): ?Authenticatable
    {
        return $this->getImpersonator();
    }

    /**
     * Get the URL to redirect to when leaving impersonation.
     */
    public function getLeaveRedirectUrl(): ?string
    {
        return $this->impersonationSession->getLeaveRedirectUrl();
    }

    /**
     * Ensure impersonation is enabled in config.
     *
     * @throws ImpersonationException
     */
    protected function ensureImpersonationIsEnabled(): void
    {
        if (! $this->config->get('mirror.enabled', true)) {
            throw ImpersonationException::notEnabled();
        }
    }

    /**
     * Ensure the user is not already impersonating.
     *
     * @throws ImpersonationException
     */
    protected function ensureNotAlreadyImpersonating(): void
    {
        if ($this->isImpersonating()) {
            throw ImpersonationException::alreadyImpersonating();
        }
    }

    /**
     * Ensure the user is currently impersonating.
     *
     * @throws ImpersonationException
     */
    protected function ensureIsImpersonating(): void
    {
        if (! $this->isImpersonating()) {
            throw ImpersonationException::notImpersonating();
        }
    }

    /**
     * Ensure the impersonation has not expired.
     *
     * @throws ImpersonationException
     */
    protected function ensureNotExpired(): void
    {
        $ttl = $this->config->get('mirror.ttl');

        if ($this->impersonationSession->isExpired($ttl)) {
            $this->impersonationSession->clear();
            throw ImpersonationException::expired();
        }
    }

    /**
     * Ensure the impersonator can impersonate other users.
     *
     * @throws ImpersonationException
     */
    protected function ensureCanImpersonate(?Authenticatable $impersonator): void
    {
        if (! $impersonator instanceof Authenticatable) {
            throw ImpersonationException::cannotImpersonate();
        }

        // @phpstan-ignore-next-line function.alreadyNarrowedType
        if (method_exists($impersonator, 'canImpersonate') && ! $impersonator->canImpersonate()) {
            throw ImpersonationException::cannotImpersonate();
        }
    }

    /**
     * Ensure the target user can be impersonated.
     *
     * @throws ImpersonationException
     */
    protected function ensureCanBeImpersonated(Authenticatable $user): void
    {
        // @phpstan-ignore-next-line function.alreadyNarrowedType
        if (method_exists($user, 'canBeImpersonated') && ! $user->canBeImpersonated()) {
            throw ImpersonationException::cannotBeImpersonated();
        }
    }
}
