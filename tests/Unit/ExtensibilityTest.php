<?php

declare(strict_types=1);

use Mirror\BladeDirectivesRegistrar;
use Mirror\Events\ImpersonationStarted;
use Mirror\Events\ImpersonationStopped;
use Mirror\Exceptions\CanNotBeImpersonated;
use Mirror\Exceptions\CanNotImpersonate;
use Mirror\Exceptions\CannotInferTargetGuard;
use Mirror\Exceptions\GuardDoesNotUseSessionDriver;
use Mirror\Exceptions\ImpersonationAlreadyActive;
use Mirror\Exceptions\ImpersonationNotActive;
use Mirror\Exceptions\InvalidImpersonationSignature;
use Mirror\Exceptions\MirrorException;
use Mirror\Exceptions\MissingAuthenticatedSessionGuard;
use Mirror\Exceptions\MissingImpersonationSignature;
use Mirror\Facades\Mirror as MirrorFacade;
use Mirror\Guard;
use Mirror\ImpersonationHasher;
use Mirror\ImpersonationManager;
use Mirror\ImpersonationPayload;
use Mirror\MirrorServiceProvider;
use Mirror\PendingImpersonation;
use Mirror\Preconditions\EnsureImpersonationIsNotStarted;
use Mirror\Preconditions\EnsureImpersonatorCanImpersonate;
use Mirror\Preconditions\EnsureTargetCanBeImpersonated;
use Mirror\Recaller;
use Mirror\Resolvers\ResolveImpersonatorGuard;
use Mirror\Resolvers\ResolveTargetGuard;
use Mirror\SessionImpersonationStore;

it('keeps package classes open for extension', function (): void {
    /** @var list<class-string> $classes */
    $classes = [
        BladeDirectivesRegistrar::class,
        CanNotBeImpersonated::class,
        CanNotImpersonate::class,
        CannotInferTargetGuard::class,
        EnsureImpersonationIsNotStarted::class,
        EnsureImpersonatorCanImpersonate::class,
        EnsureTargetCanBeImpersonated::class,
        Guard::class,
        GuardDoesNotUseSessionDriver::class,
        ImpersonationAlreadyActive::class,
        ImpersonationHasher::class,
        ImpersonationManager::class,
        ImpersonationNotActive::class,
        ImpersonationPayload::class,
        ImpersonationStarted::class,
        ImpersonationStopped::class,
        InvalidImpersonationSignature::class,
        MirrorException::class,
        MirrorFacade::class,
        MirrorServiceProvider::class,
        MissingAuthenticatedSessionGuard::class,
        MissingImpersonationSignature::class,
        PendingImpersonation::class,
        Recaller::class,
        ResolveImpersonatorGuard::class,
        ResolveTargetGuard::class,
        SessionImpersonationStore::class,
    ];

    foreach ($classes as $class) {
        $reflection = new ReflectionClass($class);

        expect($reflection->isFinal())->toBeFalse()
            ->and($reflection->isReadOnly())->toBeFalse();
    }
});

it('does not declare private extension points in package classes', function (): void {
    /** @var list<class-string> $classes */
    $classes = [
        BladeDirectivesRegistrar::class,
        Guard::class,
        ImpersonationHasher::class,
        ImpersonationManager::class,
        PendingImpersonation::class,
        Recaller::class,
        SessionImpersonationStore::class,
    ];

    foreach ($classes as $class) {
        $reflection = new ReflectionClass($class);

        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            expect($method->isPrivate())->toBeFalse();
        }

        foreach ($reflection->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            expect($property->isPrivate())->toBeFalse();
        }
    }
});

it('uses late static binding for named constructors', function (): void {
    $exception = new class extends ImpersonationNotActive {};
    $exceptionClass = $exception::class;

    expect($exceptionClass::make())->toBeInstanceOf($exceptionClass);

    $payload = new class(impersonatorId: 1, impersonatorGuard: 'web', impersonatedId: 2, impersonatedGuard: 'web', startedAt: 100) extends ImpersonationPayload {};
    $payloadClass = $payload::class;

    expect($payloadClass::fromSessionPayload([
        'impersonator_id' => 1,
        'impersonator_guard' => 'web',
        'impersonated_id' => 2,
        'impersonated_guard' => 'web',
        'started_at' => 100,
    ]))->toBeInstanceOf($payloadClass);
});
