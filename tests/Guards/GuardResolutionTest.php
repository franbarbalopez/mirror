<?php

declare(strict_types=1);

use App\Models\User;
use Mirror\Exceptions\CannotInferTargetGuard;
use Mirror\Exceptions\GuardDoesNotUseSessionDriver;
use Mirror\Facades\Mirror;
use Mirror\Guard;
use Mirror\SessionImpersonationStore;
use Mirror\Tests\TestSupport\Models\ApiGuardUser;
use Mirror\Tests\TestSupport\Models\DefaultGuardUser;
use Mirror\Tests\TestSupport\Models\GuardNamedUser;
use Mirror\Tests\TestSupport\Models\MultiGuardUser;

use function Pest\Laravel\actingAs;

it('infers the target guard from the target model provider', function (): void {
    $target = User::factory()->create();

    expect(Guard::from($target))->toBe('web');
});

it('uses the target model guardName method before provider inference', function (): void {
    config()->set('auth.guards.customer', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    expect(Guard::from(new GuardNamedUser))->toBe('customer');
});

it('uses the target model guard_name attribute before provider inference', function (): void {
    config()->set('auth.guards.customer', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $target = User::factory()->make();
    $target->forceFill(['guard_name' => 'customer']);

    expect(Guard::from($target))->toBe('customer');
});

it('uses the target model guard_name default property before provider inference', function (): void {
    config()->set('auth.guards.customer', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    expect(Guard::from(new DefaultGuardUser))->toBe('customer');
});

it('uses the first guard when the target model defines multiple guards', function (): void {
    config()->set('auth.guards.customer', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    expect(Guard::from(new MultiGuardUser))->toBe('web');
});

it('rejects guards without the session driver defined by the target model', function (): void {
    config()->set('auth.guards.api', [
        'driver' => 'token',
        'provider' => 'users',
    ]);

    Guard::from(new ApiGuardUser);
})->throws(GuardDoesNotUseSessionDriver::class);

it('rejects unsupported explicit target guards', function (): void {
    config()->set('auth.guards.api', [
        'driver' => 'token',
        'provider' => 'users',
    ]);

    actingAs(User::factory()->create());

    Mirror::impersonate(User::factory()->create(), guard: 'api');
})->throws(GuardDoesNotUseSessionDriver::class);

it('throws when the target guard cannot be inferred', function (): void {
    config()->set('auth.guards.api', [
        'driver' => 'token',
        'provider' => 'users',
    ]);
    config()->set('auth.guards.providerless', [
        'driver' => 'session',
        'provider' => null,
    ]);
    config()->set('auth.providers.users.model', stdClass::class);

    Guard::from(User::factory()->create());
})->throws(CannotInferTargetGuard::class);

it('respects an explicit target guard', function (): void {
    config()->set('auth.guards.admin', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    $admin = User::factory()->create();
    $target = User::factory()->create();

    actingAs($admin, 'admin');

    Mirror::impersonate($target, guard: 'web');

    $payload = app(SessionImpersonationStore::class)->get();

    expect($payload?->impersonatorGuard)->toBe('admin')
        ->and($payload?->impersonatedGuard)->toBe('web');
});

it('uses the first inferred target guard when multiple guards match', function (): void {
    config()->set('auth.guards.customer', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    actingAs(User::factory()->create());

    Mirror::impersonate(User::factory()->create());

    expect(app(SessionImpersonationStore::class)->get()?->impersonatedGuard)->toBe('web');
});

it('resolves the impersonator guard from the authenticated guard', function (): void {
    config()->set('auth.guards.admin', [
        'driver' => 'session',
        'provider' => 'users',
    ]);

    actingAs(User::factory()->create(), 'admin');

    Mirror::impersonate(User::factory()->create(), guard: 'web');

    expect(app(SessionImpersonationStore::class)->get()?->impersonatorGuard)->toBe('admin');
});
