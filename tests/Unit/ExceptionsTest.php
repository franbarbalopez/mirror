<?php

declare(strict_types=1);

use App\Models\User;
use Mirror\Exceptions\CanNotBeImpersonated;
use Mirror\Exceptions\CanNotImpersonate;
use Mirror\Exceptions\ImpersonationAlreadyActive;
use Mirror\Exceptions\ImpersonationExpired;
use Mirror\Exceptions\ImpersonationNotActive;
use Mirror\Exceptions\MirrorException;
use Mirror\Exceptions\TamperedImpersonationState;
use Mirror\Exceptions\UnsupportedGuard;

it('groups all mirror domain exceptions under a single base exception', function (): void {
    $user = User::factory()->make();

    $exceptions = [
        CanNotBeImpersonated::targetIsNotAllowed($user),
        CanNotImpersonate::userIsNotAllowed($user),
        ImpersonationAlreadyActive::make(),
        ImpersonationExpired::make(),
        ImpersonationNotActive::make(),
        TamperedImpersonationState::missingSignature(),
        TamperedImpersonationState::invalidSignature(),
        UnsupportedGuard::cannotInferFor($user),
        UnsupportedGuard::notStateful('api'),
        UnsupportedGuard::noAuthenticatedSessionGuard(),
    ];

    foreach ($exceptions as $exception) {
        expect($exception)->toBeInstanceOf(MirrorException::class)
            ->and($exception->getMessage())->not->toBeEmpty();
    }
});

it('describes the exact reason for guard failures', function (): void {
    $user = User::factory()->make();

    expect(UnsupportedGuard::cannotInferFor($user)->getMessage())->toContain('could not infer')
        ->and(UnsupportedGuard::notStateful('api')->getMessage())->toContain('[api]', 'not a stateful session guard')
        ->and(UnsupportedGuard::noAuthenticatedSessionGuard()->getMessage())->toContain('could not find an authenticated stateful session guard');
});

it('describes the exact reason for tampered session failures', function (): void {
    expect(TamperedImpersonationState::missingSignature()->getMessage())->toContain('signature is missing')
        ->and(TamperedImpersonationState::invalidSignature()->getMessage())->toContain('signature is invalid');
});
