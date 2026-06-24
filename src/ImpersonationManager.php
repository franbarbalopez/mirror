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
use Mirror\Exceptions\CanNotBeImpersonated;
use Mirror\Exceptions\CanNotImpersonate;
use Mirror\Exceptions\ImpersonationAlreadyActive;
use Mirror\Exceptions\ImpersonationNotActive;
use Mirror\Exceptions\TamperedImpersonationState;
use Mirror\Exceptions\UnsupportedGuard;
use Mirror\Preconditions\EnsureImpersonationIsNotStarted;
use Mirror\Preconditions\EnsureImpersonatorCanImpersonate;
use Mirror\Preconditions\EnsureTargetCanBeImpersonated;
use Mirror\Resolvers\ResolveImpersonatorGuard;
use Mirror\Resolvers\ResolveTargetGuard;

class ImpersonationManager implements Mirror
{
    private ?Authenticatable $impersonator = null;

    public function __construct(
        private readonly SessionImpersonationStore $store,
        private readonly AuthManager $auth,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     *
     * @throws CanNotBeImpersonated|CanNotImpersonate|ImpersonationAlreadyActive|UnsupportedGuard
     */
    public function impersonate(
        Authenticatable $target,
        ?string $guard = null,
        array $context = [],
    ): void {
        Pipeline::send(new PendingImpersonation($target, $guard, $context))
            ->through([
                EnsureImpersonationIsNotStarted::class,
                ResolveImpersonatorGuard::class,
                EnsureImpersonatorCanImpersonate::class,
                EnsureTargetCanBeImpersonated::class,
                ResolveTargetGuard::class,
            ])
            ->then(function (PendingImpersonation $pending): void {
                $this->start($pending);
            });
    }

    private function start(PendingImpersonation $pending): void
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
     * @return array<string, mixed>
     *
     * @throws ImpersonationNotActive
     * @throws TamperedImpersonationState
     */
    public function leave(): array
    {
        if (! $this->active()) {
            throw ImpersonationNotActive::make();
        }

        $ended = $this->revert();

        $this->impersonator = null;

        event(new ImpersonationStopped($ended->impersonator, $ended->impersonated, $ended->context));

        return $ended->context;
    }

    public function active(): bool
    {
        return $this->store->active();
    }

    /**
     * @throws TamperedImpersonationState
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
     * @throws TamperedImpersonationState
     */
    private function revert(): EndedImpersonation
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

        return new EndedImpersonation($impersonator, $impersonated, $payload->context);
    }

    /**
     * @throws TamperedImpersonationState
     */
    private function payload(): ?ImpersonationPayload
    {
        return $this->store->get();
    }

    /**
     * @throws TamperedImpersonationState
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
     * @throws TamperedImpersonationState
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
     * @return array<string, mixed>
     *
     * @throws TamperedImpersonationState
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
