<?php

declare(strict_types=1);

namespace Mirror;

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Pipeline;
use Mirror\Contracts\Mirror;
use Mirror\Events\ImpersonationStarted;
use Mirror\Events\ImpersonationStopped;
use Mirror\Exceptions\CannotLeaveImpersonation;
use Mirror\Exceptions\CannotReadImpersonationState;
use Mirror\Exceptions\CannotStartImpersonation;
use Mirror\Exceptions\ImpersonationNotActive;
use Mirror\Preconditions\EnsureImpersonationIsNotStarted;
use Mirror\Preconditions\EnsureImpersonatorCanImpersonate;
use Mirror\Preconditions\EnsureTargetCanBeImpersonated;
use Mirror\Resolvers\ResolveImpersonatorGuard;
use Mirror\Resolvers\ResolveTargetGuard;

class ImpersonationManager implements Mirror
{
    protected ?Authenticatable $impersonator = null;

    /**
     * @var list<class-string>
     */
    protected array $startPipeline = [
        EnsureImpersonationIsNotStarted::class,
        ResolveImpersonatorGuard::class,
        EnsureImpersonatorCanImpersonate::class,
        EnsureTargetCanBeImpersonated::class,
        ResolveTargetGuard::class,
    ];

    public function __construct(
        protected SessionImpersonationStore $store,
        protected AuthManager $auth,
    ) {}

    /**
     * Start impersonating the given target user.
     *
     * @param  array<string, mixed>  $context
     *
     * @throws CannotStartImpersonation
     */
    public function impersonate(
        Authenticatable $target,
        ?string $guard = null,
        array $context = [],
    ): void {
        Pipeline::send(new PendingImpersonation($target, $guard, $context))
            ->through($this->startPipeline)
            ->then(function (PendingImpersonation $pending): void {
                $this->start($pending);
            });
    }

    /**
     * Persist the resolved impersonation payload and log in as the target user.
     */
    protected function start(PendingImpersonation $pending): void
    {
        $payload = new ImpersonationPayload(
            impersonatorId: $pending->impersonator()->getAuthIdentifier(),
            impersonatorGuard: $pending->impersonatorGuard(),
            impersonatedId: $pending->target()->getAuthIdentifier(),
            impersonatedGuard: $pending->targetGuard(),
            startedAt: (int) Carbon::now()->timestamp,
            context: $pending->context(),
        );

        $this->store->put($payload);

        $this->auth->guard($pending->targetGuard())->login($pending->target());

        event(new ImpersonationStarted($pending->impersonator(), $pending->target(), $payload->context));
    }

    /**
     * Stop the active impersonation and return the signed context that started it.
     *
     * @return array<string, mixed>
     *
     * @throws CannotLeaveImpersonation
     */
    public function leave(): array
    {
        if (! $this->active()) {
            throw ImpersonationNotActive::make();
        }

        $ended = $this->revert();

        $this->impersonator = null;

        event(new ImpersonationStopped(...$ended));

        return $ended['context'];
    }

    /**
     * Determine whether the current session contains a valid impersonation payload.
     *
     * @throws CannotReadImpersonationState
     */
    public function active(): bool
    {
        return $this->store->active();
    }

    /**
     * Restore the original impersonator guard and clear the stored payload.
     *
     * @return array{impersonator: Authenticatable, impersonated: Authenticatable, context: array<string, mixed>}
     *
     * @throws CannotLeaveImpersonation
     */
    protected function revert(): array
    {
        /** @var ImpersonationPayload $payload */
        $payload = $this->payload();
        $impersonatedGuard = $this->auth->guard($payload->impersonatedGuard);
        /** @var SessionGuard $impersonatorGuard */
        $impersonatorGuard = $this->auth->guard($payload->impersonatorGuard);

        /** @var Authenticatable $impersonated */
        $impersonated = $impersonatedGuard->user();
        $remember = Recaller::shouldRemember($impersonatorGuard, $payload->impersonatorId);

        $this->store->forget();

        $impersonatedGuard->logout();
        $impersonatorGuard->loginUsingId($payload->impersonatorId, $remember);

        $this->impersonator = null;

        /** @var Authenticatable $impersonator */
        $impersonator = $impersonatorGuard->user();

        return [
            'impersonator' => $impersonator,
            'impersonated' => $impersonated,
            'context' => $payload->context,
        ];
    }

    /**
     * Determine whether the active impersonation is older than the configured TTL.
     *
     * @throws CannotReadImpersonationState
     */
    public function expired(): bool
    {
        $payload = $this->payload();

        if (! $payload instanceof ImpersonationPayload) {
            return false;
        }

        /** @var ?int $ttl */
        $ttl = config('mirror.ttl');

        if ($ttl === null) {
            return false;
        }

        return ((int) Carbon::now()->timestamp - $payload->startedAt) > $ttl;
    }

    /**
     * Read and verify the current impersonation payload from storage.
     *
     * @throws CannotReadImpersonationState
     */
    protected function payload(): ?ImpersonationPayload
    {
        return $this->store->get();
    }

    /**
     * Return the original impersonator model for the active impersonation.
     *
     * @throws CannotReadImpersonationState
     */
    public function impersonator(): ?Authenticatable
    {
        if (! $this->active()) {
            $this->impersonator = null;

            return null;
        }

        if ($this->impersonator instanceof Authenticatable) {
            return $this->impersonator;
        }

        /** @var ImpersonationPayload $payload */
        $payload = $this->payload();

        $provider = $this->auth->createUserProvider(config(sprintf('auth.guards.%s.provider', $payload->impersonatorGuard)));

        if ($provider === null) {
            return null;
        }

        $this->impersonator = $provider->retrieveById($payload->impersonatorId);

        return $this->impersonator;
    }

    /**
     * Return the currently impersonated model.
     *
     * @throws CannotReadImpersonationState
     */
    public function impersonated(): ?Authenticatable
    {
        $payload = $this->payload();

        if (! $payload instanceof ImpersonationPayload) {
            return null;
        }

        return $this->auth->guard($payload->impersonatedGuard)->user();
    }

    /**
     * Return the custom context attached to the active impersonation.
     *
     * @return array<string, mixed>
     *
     * @throws CannotReadImpersonationState
     */
    public function context(): array
    {
        $payload = $this->payload();

        if (! $payload instanceof ImpersonationPayload) {
            return [];
        }

        return $payload->context;
    }
}
