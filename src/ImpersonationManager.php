<?php

declare(strict_types=1);

namespace Mirror;

use Illuminate\Auth\AuthManager;
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
     * @throws CanNotBeImpersonated
     * @throws CanNotImpersonate
     * @throws ImpersonationAlreadyActive
     * @throws UnsupportedGuard
     */
    public function impersonate(
        Authenticatable $target,
        ?string $guard = null,
        ?string $leaveUrl = null,
    ): void {
        Pipeline::send(new ImpersonationStartContext($target, $guard, $leaveUrl))
            ->through([
                EnsureImpersonationIsNotStarted::class,
                ResolveImpersonatorGuard::class,
                EnsureImpersonatorCanImpersonate::class,
                EnsureTargetCanBeImpersonated::class,
                ResolveTargetGuard::class,
            ])
            ->then(function (ImpersonationStartContext $context): void {
                $this->start($context);
            });
    }

    private function start(ImpersonationStartContext $context): void
    {
        $payload = new ImpersonationPayload(
            impersonatorId: $context->impersonator()->getAuthIdentifier(),
            impersonatorGuard: $context->impersonatorGuard(),
            impersonatedId: $context->target()->getAuthIdentifier(),
            impersonatedGuard: $context->targetGuard(),
            startedAt: (int) Carbon::now()->timestamp,
            leaveUrl: $context->leaveUrl(),
        );

        $this->store->put($payload);

        auth($context->targetGuard())->login($context->target());

        ImpersonationStarted::dispatch($context->impersonator(), $context->target(), $payload);
    }

    /**
     * @throws ImpersonationNotActive
     */
    public function leave(): void
    {
        if (! $this->active()) {
            throw ImpersonationNotActive::make();
        }

        $this->revert();
    }

    public function active(): bool
    {
        return $this->store->active();
    }

    public function expired(): bool
    {
        /** @var ?int $ttl */
        $ttl = config('mirror.ttl');

        if ($ttl === null) {
            return false;
        }

        return $this->store->expired($ttl);
    }

    /**
     * @throws TamperedImpersonationState
     */
    public function payload(): ?ImpersonationPayload
    {
        return $this->store->get();
    }

    /**
     * @throws TamperedImpersonationState
     */
    public function impersonator(): ?Authenticatable
    {
        if ($this->impersonator instanceof Authenticatable) {
            return $this->impersonator;
        }

        $payload = $this->payload();

        if (! $payload instanceof ImpersonationPayload) {
            return null;
        }

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
    public function impersonatorId(): int|string|null
    {
        return $this->payload()?->impersonatorId;
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

        return auth($payload->impersonatedGuard)->user();
    }

    /**
     * @throws TamperedImpersonationState
     */
    public function leaveUrl(): ?string
    {
        return $this->payload()?->leaveUrl;
    }

    public function expiredRedirectUrl(): string
    {
        return (string) config('mirror.redirects.expired', '/');
    }

    private function revert(): void
    {
        /** @var ImpersonationPayload $payload */
        $payload = $this->payload();
        /** @var Authenticatable $impersonated */
        $impersonated = auth($payload->impersonatedGuard)->user();

        $this->store->forget();

        auth($payload->impersonatedGuard)->logout();
        auth($payload->impersonatorGuard)->loginUsingId($payload->impersonatorId);

        $this->impersonator = null;

        /** @var Authenticatable $impersonator */
        $impersonator = auth($payload->impersonatorGuard)->user();

        ImpersonationStopped::dispatch($impersonator, $impersonated, $payload);
    }
}
