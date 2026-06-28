<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use Mirror\Events\ImpersonationStopped;
use Mirror\Exceptions\ImpersonationNotActive;
use Mirror\Facades\Mirror;
use Mirror\ImpersonationManager;

use function Pest\Laravel\actingAs;

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

it('throws when stopping without active impersonation', function (): void {
    Mirror::leave();
})->throws(ImpersonationNotActive::class);
