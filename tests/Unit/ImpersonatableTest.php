<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Mirror\Contracts\Impersonatable;

it('defines the impersonation capability methods', function (): void {
    $user = new class extends User implements Impersonatable
    {
        public function canImpersonate(): bool
        {
            return true;
        }

        public function canBeImpersonated(): bool
        {
            return true;
        }
    };

    expect($user)->toBeInstanceOf(Impersonatable::class)
        ->and($user->canImpersonate())->toBeTrue()
        ->and($user->canBeImpersonated())->toBeTrue()
        ->and(method_exists($user, 'impersonate'))->toBeFalse()
        ->and(method_exists($user, 'leaveImpersonation'))->toBeFalse()
        ->and(method_exists($user, 'isImpersonating'))->toBeFalse();
});
