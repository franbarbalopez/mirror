<?php

declare(strict_types=1);

use Mirror\Recaller;

it('restores the remembered login state when the recaller matches the impersonator', function (): void {
    $guard = recallerGuard('remember_web', '123|token|password-hash');

    expect(Recaller::shouldRemember($guard, 123))->toBeTrue();
});

it('does not restore remembered login state when the recaller is missing', function (): void {
    $guard = recallerGuard('remember_web', null);

    expect(Recaller::shouldRemember($guard, 123))->toBeFalse();
});

it('does not restore remembered login state when the recaller is malformed', function (): void {
    $guard = recallerGuard('remember_web', 'invalid');

    expect(Recaller::shouldRemember($guard, 123))->toBeFalse();
});

it('does not restore remembered login state when the recaller belongs to another user', function (): void {
    $guard = recallerGuard('remember_web', '456|token|password-hash');

    expect(Recaller::shouldRemember($guard, 123))->toBeFalse();
});
