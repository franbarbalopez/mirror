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
     * @throws CanNotBeImpersonated
     * @throws CanNotImpersonate
     * @throws ImpersonationAlreadyActive
     * @throws UnsupportedGuard
     */
    public function impersonate(
        Authenticatable $target,
        ?string $guard = null,
        array $context = [],
    ): void {
        Pipeline::send(new ImpersonationStartContext($target, $guard, $context))
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
            context: $context->context(),
        );

        $this->store->put($payload);

        $this->auth->guard($context->targetGuard())->login($context->target());

        ImpersonationStarted::dispatch($context->impersonator(), $context->target(), $payload);
    }

    /**
     * @throws ImpersonationNotActive
     * @throws TamperedImpersonationState
     */
    public function leave(): ImpersonationPayload
    {
        if (! $this->active()) {
            throw ImpersonationNotActive::make();
        }

        return $this->revert();
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

    public function expiredRedirectUrl(): string
    {
        return (string) config('mirror.redirects.expired');
    }

    private function revert(): ImpersonationPayload
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

        ImpersonationStopped::dispatch($impersonator, $impersonated, $payload);

        return $payload;
    }
}
