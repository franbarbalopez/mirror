<?php

declare(strict_types=1);

use App\Models\User;
use Mirror\Contexts\ImpersonationStartContext;

it('exposes its initial values through getters', function (): void {
    $target = User::factory()->make();
    $context = new ImpersonationStartContext($target, 'web', '/users');

    expect($context->target())->toBe($target)
        ->and($context->requestedGuard())->toBe('web')
        ->and($context->leaveUrl())->toBe('/users');
});

it('sets and exposes resolved impersonation values', function (): void {
    $target = User::factory()->make(['id' => 2]);
    $impersonator = User::factory()->make(['id' => 1]);
    $context = new ImpersonationStartContext($target);

    $context->setImpersonatorGuard('web');
    $context->setTargetGuard('admin');
    $context->setImpersonator($impersonator);

    expect($context->impersonatorGuard())->toBe('web')
        ->and($context->targetGuard())->toBe('admin')
        ->and($context->impersonator())->toBe($impersonator);
});
