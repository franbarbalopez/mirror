<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use Mirror\Contracts\Mirror as MirrorContract;
use Mirror\Events\ImpersonationStarted;
use Mirror\Events\ImpersonationStopped;
use Mirror\Exceptions\CanNotBeImpersonated;
use Mirror\Exceptions\CanNotImpersonate;
use Mirror\Exceptions\ImpersonationAlreadyActive;
use Mirror\Exceptions\ImpersonationNotActive;
use Mirror\Exceptions\TamperedImpersonationState;
use Mirror\Exceptions\UnsupportedGuard;
use Mirror\Facades\Mirror;
use Mirror\Guard;
use Mirror\ImpersonationManager;
use Mirror\ImpersonationPayload;
use Mirror\SessionImpersonationStore;

use function Pest\Laravel\actingAs;

it('starts impersonation with a signed payload and dispatches an event', function (): void {
    Event::fake();

    $admin = User::factory()->create();
    $target = User::factory()->create();

    actingAs($admin);

    Mirror::impersonate($target, context: [
        'reason' => 'support',
        'ticket_id' => 123,
    ]);

    expect(Auth::id())->toBe($target->id)
        ->and(app(ImpersonationManager::class)->active())->toBeTrue()
        ->and(app(ImpersonationManager::class)->impersonator()?->getAuthIdentifier())->toBe($admin->id)
        ->and(app(ImpersonationManager::class)->context())->toBe([
            'reason' => 'support',
            'ticket_id' => 123,
        ])
        ->and(Session::has('mirror.impersonation.payload'))->toBeTrue()
        ->and(Session::has('mirror.impersonation.signature'))->toBeTrue();

    $payload = app(SessionImpersonationStore::class)->get();

    expect($payload)->toBeInstanceOf(ImpersonationPayload::class)
        ->and($payload->impersonatorGuard)->toBe('web')
        ->and($payload->impersonatedGuard)->toBe('web')
        ->and($payload->impersonatedId)->toBe($target->id)
        ->and($payload->context)->toBe([
            'reason' => 'support',
            'ticket_id' => 123,
        ]);

    Event::assertDispatched(ImpersonationStarted::class, fn (ImpersonationStarted $event): bool => $event->impersonator->is($admin)
        && $event->impersonated->is($target)
        && $event->context === [
            'reason' => 'support',
            'ticket_id' => 123,
        ]);
});

it('stops impersonation and restores the original user', function (): void {
    Event::fake();

    $admin = User::factory()->create();
    $target = User::factory()->create();

    actingAs($admin);

    Mirror::impersonate($target, context: [
        'reason' => 'support',
    ]);

    $context = Mirror::leave();

    expect(Auth::id())->toBe($admin->id)
        ->and($context)->toBe([
            'reason' => 'support',
        ])
        ->and(app(ImpersonationManager::class)->active())->toBeFalse()
        ->and(Session::has('mirror.impersonation.payload'))->toBeFalse()
        ->and(Session::has('mirror.impersonation.signature'))->toBeFalse();

    Event::assertDispatched(ImpersonationStopped::class, fn (ImpersonationStopped $event): bool => $event->impersonator->is($admin)
        && $event->impersonated->is($target)
        && $event->context === [
            'reason' => 'support',
        ]);
});

it('returns the impersonator and current impersonated user', function (): void {
    $admin = User::factory()->create();
    $target = User::factory()->create();

    actingAs($admin);
    Mirror::impersonate($target);

    expect(app(ImpersonationManager::class)->impersonator()?->is($admin))->toBeTrue()
        ->and(app(ImpersonationManager::class)->impersonated()?->is($target))->toBeTrue();
});

it('caches the impersonator model in memory for the current request', function (): void {
    $admin = User::factory()->create();
    $target = User::factory()->create();

    actingAs($admin);
    Mirror::impersonate($target);

    $first = Mirror::impersonator();
    $second = Mirror::impersonator();

    expect($first)->toBe($second);
});

it('returns null values when no impersonation is active', function (): void {
    expect(app(ImpersonationManager::class)->active())->toBeFalse()
        ->and(app(ImpersonationManager::class)->expired())->toBeFalse()
        ->and(app(ImpersonationManager::class)->impersonator())->toBeNull()
        ->and(app(ImpersonationManager::class)->impersonated())->toBeNull()
        ->and(app(ImpersonationManager::class)->context())->toBe([]);
});

it('checks whether active impersonation has expired', function (): void {
    Config::set('mirror.ttl', 60);
    Carbon::setTestNow(Carbon::createFromTimestamp(100));

    $admin = User::factory()->create();

    actingAs($admin);
    Mirror::impersonate(User::factory()->create());

    expect(Mirror::expired())->toBeFalse();

    Carbon::setTestNow(Carbon::createFromTimestamp(161));

    expect(Mirror::expired())->toBeTrue();
});

it('does not expire impersonation when ttl is disabled', function (): void {
    Config::set('mirror.ttl');
    Carbon::setTestNow(Carbon::createFromTimestamp(100));

    $admin = User::factory()->create();

    actingAs($admin);
    Mirror::impersonate(User::factory()->create());

    Carbon::setTestNow(Carbon::createFromTimestamp(100000));

    expect(Mirror::expired())->toBeFalse();
});

it('supports explicit guard configuration and custom session keys', function (): void {
    Config::set('mirror.session.key', 'custom.impersonation');

    $admin = User::factory()->create();
    $target = User::factory()->create();

    actingAs($admin);
    Mirror::impersonate($target);

    expect(Session::has('custom.impersonation.payload'))->toBeTrue()
        ->and(Session::has('custom.impersonation.signature'))->toBeTrue();
});

it('rejects nested impersonation', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);

    Mirror::impersonate(User::factory()->create());
    Mirror::impersonate(User::factory()->create());
})->throws(ImpersonationAlreadyActive::class);

it('requires an impersonator that can impersonate', function (): void {
    $admin = new class extends User
    {
        public function canImpersonate(): bool
        {
            return false;
        }
    };

    $admin->forceFill(['id' => 1, 'email' => 'admin@test.com']);

    actingAs($admin);

    Mirror::impersonate(User::factory()->create());
})->throws(CanNotImpersonate::class);

it('requires a target that can be impersonated', function (): void {
    $target = new class extends User
    {
        public function canBeImpersonated(): bool
        {
            return false;
        }
    };

    $target->forceFill(['id' => 2, 'email' => 'target@test.com']);

    $admin = User::factory()->create();

    actingAs($admin);

    Mirror::impersonate($target);
})->throws(CanNotBeImpersonated::class);

it('throws when stopping without active impersonation', function (): void {
    Mirror::leave();
})->throws(ImpersonationNotActive::class);

it('detects tampered payloads', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);
    Mirror::impersonate(User::factory()->create());

    $payload = Session::get('mirror.impersonation.payload');
    $payload['impersonator_id'] = 999;
    Session::put('mirror.impersonation.payload', $payload);

    Mirror::context();
})->throws(TamperedImpersonationState::class);

it('detects a missing signature', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);
    Mirror::impersonate(User::factory()->create());

    Session::forget('mirror.impersonation.signature');

    Mirror::context();
})->throws(TamperedImpersonationState::class);

it('detects a missing signature when checking active state', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);
    Mirror::impersonate(User::factory()->create());

    Session::forget('mirror.impersonation.signature');

    Mirror::active();
})->throws(TamperedImpersonationState::class);

it('detects tampered payloads when checking expiration', function (): void {
    Config::set('mirror.ttl', 60);

    $admin = User::factory()->create();

    actingAs($admin);
    Mirror::impersonate(User::factory()->create());

    $payload = Session::get('mirror.impersonation.payload');
    $payload['started_at'] = 1;
    Session::put('mirror.impersonation.payload', $payload);

    Mirror::expired();
})->throws(TamperedImpersonationState::class);

it('rejects unsupported guards without the session driver', function (): void {
    Config::set('auth.guards.api', [
        'driver' => 'token',
        'provider' => 'users',
    ]);

    $admin = User::factory()->create();

    actingAs($admin);

    Mirror::impersonate(User::factory()->create(), guard: 'api');
})->throws(UnsupportedGuard::class);

it('infers the target guard from the target model', function (): void {
    $target = User::factory()->create();

    expect(Guard::from($target))->toBe('web')
        ->and(app(ImpersonationManager::class))->toBeInstanceOf(ImpersonationManager::class)
        ->and(app(MirrorContract::class))->toBeInstanceOf(ImpersonationManager::class);
});

it('resolves the contract and facade alias to the same manager instance', function (): void {
    $manager = app(MirrorContract::class);

    expect($manager)->toBeInstanceOf(ImpersonationManager::class)
        ->and(app('mirror'))->toBe($manager);
});

it('uses the target model guardName method before provider inference', function (): void {
    Config::set('auth.guards.customer', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $target = new class extends User
    {
        public function guardName(): string
        {
            return 'customer';
        }
    };

    expect(Guard::from($target))->toBe('customer');
});

it('uses the target model guard_name attribute before provider inference', function (): void {
    Config::set('auth.guards.customer', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $target = User::factory()->make();
    $target->forceFill(['guard_name' => 'customer']);

    expect(Guard::from($target))->toBe('customer');
});

it('uses the target model guard_name default property before provider inference', function (): void {
    Config::set('auth.guards.customer', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $target = new class extends User
    {
        protected string $guard_name = 'customer';
    };

    expect(Guard::from($target))->toBe('customer');
});

it('uses the first guard when the target model defines multiple guards', function (): void {
    Config::set('auth.guards.customer', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $target = new class extends User
    {
        /**
         * @return list<string>
         */
        public function guardName(): array
        {
            return ['web', 'customer'];
        }
    };

    expect(Guard::from($target))->toBe('web');
});

it('rejects guards without the session driver defined by the target model', function (): void {
    Config::set('auth.guards.api', [
        'driver' => 'token',
        'provider' => 'users',
    ]);

    $target = new class extends User
    {
        public function guardName(): string
        {
            return 'api';
        }
    };

    Guard::from($target);
})->throws(UnsupportedGuard::class);

it('rejects impersonation when no guard using the session driver is authenticated', function (): void {
    Config::set('auth.guards.api', [
        'driver' => 'token',
        'provider' => 'users',
    ]);

    Mirror::impersonate(User::factory()->create());
})->throws(UnsupportedGuard::class);

it('throws when the target guard cannot be inferred', function (): void {
    Config::set('auth.guards.api', [
        'driver' => 'token',
        'provider' => 'users',
    ]);
    Config::set('auth.guards.providerless', [
        'driver' => 'session',
        'provider' => null,
    ]);
    Config::set('auth.providers.users.model', stdClass::class);

    Guard::from(User::factory()->create());
})->throws(UnsupportedGuard::class);

it('respects an explicit target guard', function (): void {
    Config::set('auth.guards.admin', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $admin = User::factory()->create();
    $target = User::factory()->create();

    actingAs($admin, 'admin');
    Auth::shouldUse('web');

    Mirror::impersonate($target, guard: 'web');

    $payload = app(SessionImpersonationStore::class)->get();

    expect($payload?->impersonatorGuard)->toBe('admin')
        ->and($payload?->impersonatedGuard)->toBe('web');
});

it('uses the first inferred target guard when multiple guards match', function (): void {
    Config::set('auth.guards.customer', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    actingAs(User::factory()->create());

    Mirror::impersonate(User::factory()->create());

    expect(app(SessionImpersonationStore::class)->get()?->impersonatedGuard)->toBe('web');
});

it('uses model inference without a config guard fallback', function (): void {
    Config::set('auth.guards.customer', [
        'driver' => 'session',
        'provider' => 'users',
    ]);
    actingAs(User::factory()->create());

    Mirror::impersonate(User::factory()->create());

    expect(app(SessionImpersonationStore::class)->get()?->impersonatedGuard)->toBe('web');
});

it('resolves the impersonator guard from the authenticated guard', function (): void {
    Config::set('auth.guards.admin', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    actingAs(User::factory()->create(), 'admin');
    Auth::shouldUse('web');

    Mirror::impersonate(User::factory()->create(), guard: 'web');

    expect(app(SessionImpersonationStore::class)->get()?->impersonatorGuard)->toBe('admin');
});

it('returns null when retrieving a user from a guard without provider', function (): void {
    Config::set('auth.guards.providerless', [
        'driver' => 'session',
        'provider' => null,
    ]);

    app(SessionImpersonationStore::class)->put(new ImpersonationPayload(
        impersonatorId: 1,
        impersonatorGuard: 'providerless',
        impersonatedId: 2,
        impersonatedGuard: 'web',
        startedAt: (int) Carbon::now()->timestamp,
    ));

    expect(Mirror::impersonator())->toBeNull();
});

it('returns null when reading the impersonated user from a guard without an authenticated user', function (): void {
    Config::set('auth.guards.api', [
        'driver' => 'token',
        'provider' => 'users',
    ]);

    app(SessionImpersonationStore::class)->put(new ImpersonationPayload(
        impersonatorId: 1,
        impersonatorGuard: 'web',
        impersonatedId: 2,
        impersonatedGuard: 'api',
        startedAt: (int) Carbon::now()->timestamp,
    ));

    expect(Mirror::impersonated())->toBeNull();
});
