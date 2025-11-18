<?php

use App\Models\User;
use Mockery\MockInterface;

it('provides canImpersonate method that returns true by default', function (): void {
    $user = User::factory()->make();

    expect($user->canImpersonate())->toBeTrue();
});

it('provides canBeImpersonated method that returns true by default', function (): void {
    $user = User::factory()->make();

    expect($user->canBeImpersonated())->toBeTrue();
});

it('can be overridden to restrict impersonation', function (): void {
    $user = Mockery::mock(User::class, function (MockInterface $mock): void {
        $mock->shouldReceive('canImpersonate')
            ->andReturn(false);
    });

    expect($user->canImpersonate())->toBeFalse();
});

it('can be overridden to prevent being impersonated', function (): void {
    $user = Mockery::mock(User::class, function (MockInterface $mock): void {
        $mock->shouldReceive('canBeImpersonated')
            ->andReturn(false);
    });

    expect($user->canBeImpersonated())->toBeFalse();
});

it('both methods can be overridden independently', function (): void {
    $user = Mockery::mock(User::class, function (MockInterface $mock): void {
        $mock->shouldReceive('canBeImpersonated')
            ->andReturn(false);

        $mock->shouldReceive('canImpersonate')
            ->andReturn(true);
    });

    expect($user->canImpersonate())->toBeTrue()
        ->and($user->canBeImpersonated())->toBeFalse();
});
