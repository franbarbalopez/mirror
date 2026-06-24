<?php

declare(strict_types=1);

use App\Models\User;
use Mirror\PendingImpersonation;

it('exposes its initial values through getters', function (): void {
    $target = User::factory()->make();
    $pending = new PendingImpersonation($target, 'web', ['reason' => 'support']);

    expect($pending->target())->toBe($target)
        ->and($pending->hasTargetGuard())->toBeTrue()
        ->and($pending->targetGuard())->toBe('web')
        ->and($pending->context())->toBe(['reason' => 'support']);
});

it('sets and exposes resolved impersonation values', function (): void {
    $target = User::factory()->make(['id' => 2]);
    $impersonator = User::factory()->make(['id' => 1]);
    $pending = new PendingImpersonation($target);

    expect($pending->hasTargetGuard())->toBeFalse();

    $pending->setImpersonatorGuard('web');
    $pending->setTargetGuard('admin');
    $pending->setImpersonator($impersonator);

    expect($pending->impersonatorGuard())->toBe('web')
        ->and($pending->targetGuard())->toBe('admin')
        ->and($pending->impersonator())->toBe($impersonator);
});
