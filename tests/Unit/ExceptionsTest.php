<?php

declare(strict_types=1);

use App\Models\User;
use Mirror\Exceptions\CanNotBeImpersonated;
use Mirror\Exceptions\CanNotImpersonate;
use Mirror\Exceptions\CannotInferTargetGuard;
use Mirror\Exceptions\CannotLeaveImpersonation;
use Mirror\Exceptions\CannotReadImpersonationState;
use Mirror\Exceptions\CannotStartImpersonation;
use Mirror\Exceptions\GuardDoesNotUseSessionDriver;
use Mirror\Exceptions\ImpersonationAlreadyActive;
use Mirror\Exceptions\ImpersonationNotActive;
use Mirror\Exceptions\InvalidImpersonationSignature;
use Mirror\Exceptions\MirrorException;
use Mirror\Exceptions\MissingAuthenticatedSessionGuard;
use Mirror\Exceptions\MissingImpersonationSignature;

it('groups all mirror domain exceptions under a single base exception', function (): void {
    $user = User::factory()->make();

    $exceptions = [
        CanNotBeImpersonated::make($user),
        CanNotImpersonate::make($user),
        CannotInferTargetGuard::make($user),
        GuardDoesNotUseSessionDriver::make('api'),
        ImpersonationAlreadyActive::make(),
        ImpersonationNotActive::make(),
        InvalidImpersonationSignature::make(),
        MissingAuthenticatedSessionGuard::make(),
        MissingImpersonationSignature::make(),
    ];

    foreach ($exceptions as $exception) {
        expect($exception)->toBeInstanceOf(MirrorException::class)
            ->and($exception->getMessage())->not->toBeEmpty();
    }
});

it('groups domain exceptions by impersonation phase', function (): void {
    $user = User::factory()->make();

    $startFailures = [
        CanNotBeImpersonated::make($user),
        CanNotImpersonate::make($user),
        CannotInferTargetGuard::make($user),
        GuardDoesNotUseSessionDriver::make('api'),
        ImpersonationAlreadyActive::make(),
        MissingAuthenticatedSessionGuard::make(),
    ];

    foreach ($startFailures as $exception) {
        expect($exception)->toBeInstanceOf(CannotStartImpersonation::class);
    }

    $tamperedStateFailures = [
        InvalidImpersonationSignature::make(),
        MissingImpersonationSignature::make(),
    ];

    expect(ImpersonationNotActive::make())->toBeInstanceOf(CannotLeaveImpersonation::class);

    foreach ($tamperedStateFailures as $exception) {
        expect($exception)->toBeInstanceOf(CannotLeaveImpersonation::class)
            ->and($exception)->toBeInstanceOf(CannotReadImpersonationState::class);
    }
});

it('describes the exact reason for guard failures', function (): void {
    $user = User::factory()->make();

    expect(CannotInferTargetGuard::make($user)->getMessage())->toContain("Could not infer a guard using Laravel's [session] driver")
        ->and(GuardDoesNotUseSessionDriver::make('api')->getMessage())->toContain('[api]', "does not use Laravel's [session] driver", 'Supported guards')
        ->and(MissingAuthenticatedSessionGuard::make()->getMessage())->toContain("Could not find an authenticated guard using Laravel's [session] driver");
});

it('describes the exact reason for tampered session failures', function (): void {
    expect(MissingImpersonationSignature::make()->getMessage())->toContain('signature is missing')
        ->and(InvalidImpersonationSignature::make()->getMessage())->toContain('signature is invalid');
});
