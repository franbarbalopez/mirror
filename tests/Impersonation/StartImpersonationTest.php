<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use Mirror\Contracts\Mirror as MirrorContract;
use Mirror\Events\ImpersonationStarted;
use Mirror\Exceptions\CanNotBeImpersonated;
use Mirror\Exceptions\CanNotImpersonate;
use Mirror\Exceptions\ImpersonationAlreadyActive;
use Mirror\Exceptions\MissingAuthenticatedSessionGuard;
use Mirror\Facades\Mirror;
use Mirror\ImpersonationManager;
use Mirror\ImpersonationPayload;
use Mirror\SessionImpersonationStore;
use Mirror\Tests\TestSupport\Models\CannotBeImpersonatedUser;
use Mirror\Tests\TestSupport\Models\CannotImpersonateUser;

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

it('rejects nested impersonation', function (): void {
    actingAs(User::factory()->create());

    Mirror::impersonate(User::factory()->create());
    Mirror::impersonate(User::factory()->create());
})->throws(ImpersonationAlreadyActive::class);

it('requires an impersonator that can impersonate', function (): void {
    $admin = new CannotImpersonateUser;
    $admin->forceFill(['id' => 1, 'email' => 'admin@test.com']);

    actingAs($admin);

    Mirror::impersonate(User::factory()->create());
})->throws(CanNotImpersonate::class);

it('requires a target that can be impersonated', function (): void {
    $target = new CannotBeImpersonatedUser;
    $target->forceFill(['id' => 2, 'email' => 'target@test.com']);

    actingAs(User::factory()->create());

    Mirror::impersonate($target);
})->throws(CanNotBeImpersonated::class);

it('rejects impersonation when no guard using the session driver is authenticated', function (): void {
    config()->set('auth.guards.api', [
        'driver' => 'token',
        'provider' => 'users',
    ]);

    Mirror::impersonate(User::factory()->create());
})->throws(MissingAuthenticatedSessionGuard::class);

it('resolves the contract and facade alias to the same manager instance', function (): void {
    $manager = app(MirrorContract::class);

    expect($manager)->toBeInstanceOf(ImpersonationManager::class)
        ->and(app('mirror'))->toBe($manager)
        ->and(app(ImpersonationManager::class))->toBeInstanceOf(ImpersonationManager::class);
});
