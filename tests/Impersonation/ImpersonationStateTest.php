<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;
use Mirror\Facades\Mirror;
use Mirror\ImpersonationPayload;
use Mirror\SessionImpersonationStore;

use function Pest\Laravel\actingAs;

it('returns the impersonator and current impersonated user', function (): void {
    $admin = User::factory()->create();
    $target = User::factory()->create();

    actingAs($admin);
    Mirror::impersonate($target);

    expect(Mirror::impersonator()?->is($admin))->toBeTrue()
        ->and(Mirror::impersonated()?->is($target))->toBeTrue();
});

it('caches the impersonator model in memory for the current request', function (): void {
    actingAs(User::factory()->create());

    Mirror::impersonate(User::factory()->create());

    $first = Mirror::impersonator();
    $second = Mirror::impersonator();

    expect($first)->toBe($second);
});

it('returns null values when no impersonation is active', function (): void {
    expect(Mirror::active())->toBeFalse()
        ->and(Mirror::expired())->toBeFalse()
        ->and(Mirror::impersonator())->toBeNull()
        ->and(Mirror::impersonated())->toBeNull()
        ->and(Mirror::context())->toBe([]);
});

it('checks whether active impersonation has expired', function (): void {
    config()->set('mirror.ttl', 60);
    Carbon::setTestNow(Carbon::createFromTimestamp(100));

    actingAs(User::factory()->create());

    Mirror::impersonate(User::factory()->create());

    expect(Mirror::expired())->toBeFalse();

    Carbon::setTestNow(Carbon::createFromTimestamp(161));

    expect(Mirror::expired())->toBeTrue();
});

it('does not expire impersonation when ttl is disabled', function (): void {
    config()->set('mirror.ttl');
    Carbon::setTestNow(Carbon::createFromTimestamp(100));

    actingAs(User::factory()->create());

    Mirror::impersonate(User::factory()->create());

    Carbon::setTestNow(Carbon::createFromTimestamp(100000));

    expect(Mirror::expired())->toBeFalse();
});

it('returns null when retrieving a user from a guard without provider', function (): void {
    config()->set('auth.guards.providerless', [
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
    config()->set('auth.guards.api', [
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
