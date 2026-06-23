<?php

use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Mirror\Recaller;

it('remembers the user when the recaller is valid and matches the user id', function (): void {
    $guard = recallerGuard('remember_web', '123|token|password-hash');

    expect(Recaller::shouldRemember($guard, 123))->toBeTrue();
});

it('does not remember the user when the recaller is missing', function (): void {
    $guard = recallerGuard('remember_web', null);

    expect(Recaller::shouldRemember($guard, 123))->toBeFalse();
});

it('does not remember the user when the recaller is malformed', function (): void {
    $guard = recallerGuard('remember_web', 'invalid');

    expect(Recaller::shouldRemember($guard, 123))->toBeFalse();
});

it('does not remember the user when the recaller belongs to another user', function (): void {
    $guard = recallerGuard('remember_web', '456|token|password-hash');

    expect(Recaller::shouldRemember($guard, 123))->toBeFalse();
});

function recallerGuard(string $recallerName, ?string $recaller): SessionGuard
{
    $guard = Mockery::mock(SessionGuard::class);
    $guard->shouldReceive('getRequest')
        ->andReturn(Request::create('/', 'GET', cookies: array_filter([
            $recallerName => $recaller,
        ])));
    $guard->shouldReceive('getRecallerName')
        ->andReturn($recallerName);

    return $guard;
}
