<?php

use Illuminate\Foundation\Auth\User as Authenticatable;
use Mirror\Concerns\Impersonatable;
use Mirror\Exceptions\ImpersonationException;
use Mirror\Facades\Mirror;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;

describe('Impersonatable trait', function (): void {
    it('provides canImpersonate method that returns true by default', function (): void {
        $user = User::factory()->create();

        expect($user->canImpersonate())->toBeTrue();
    });

    it('provides canBeImpersonated method that returns true by default', function (): void {
        $user = User::factory()->create();

        expect($user->canBeImpersonated())->toBeTrue();
    });

    it('allows user to impersonate when canImpersonate returns true', function (): void {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        actingAs($admin);

        expect($admin->canImpersonate())->toBeTrue();

        Mirror::start($targetUser);

        expect(Mirror::isImpersonating())->toBeTrue();
    });

    it('allows user to be impersonated when canBeImpersonated returns true', function (): void {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        actingAs($admin);

        expect($targetUser->canBeImpersonated())->toBeTrue();

        Mirror::start($targetUser);

        expect(auth()->id())->toBe($targetUser->id);
    });

    it('can be overridden to restrict impersonation', function (): void {
        $restrictedUser = new class extends Authenticatable
        {
            use Impersonatable;

            protected $guarded = [];

            public function canImpersonate(): bool
            {
                return false;
            }
        };

        $user = User::factory()->create();

        $restrictedUser->forceFill(['id' => $user->id, 'email' => $user->email])->exists = true;

        expect($restrictedUser->canImpersonate())->toBeFalse();
    });

    it('can be overridden to prevent being impersonated', function (): void {
        $protectedUser = new class extends Authenticatable
        {
            use Impersonatable;

            protected $guarded = [];

            public function canBeImpersonated(): bool
            {
                return false;
            }
        };

        $user = User::factory()->create();

        $protectedUser->forceFill(['id' => $user->id, 'email' => $user->email])->exists = true;

        expect($protectedUser->canBeImpersonated())->toBeFalse();
    });

    it('both methods can be overridden independently', function (): void {
        $user = new class extends Authenticatable
        {
            use Impersonatable;

            protected $guarded = [];

            public function canImpersonate(): bool
            {
                return true;
            }

            public function canBeImpersonated(): bool
            {
                return false;
            }
        };

        expect($user->canImpersonate())->toBeTrue()
            ->and($user->canBeImpersonated())->toBeFalse();
    });

    it('integrates with full impersonation flow', function (): void {
        $admin = User::factory()->create();

        $regularUser = User::factory()->create();

        $protectedUser = new class extends Authenticatable
        {
            use Impersonatable;

            protected $guarded = [];

            public function canBeImpersonated(): bool
            {
                return false;
            }
        };
        $protectedUserData = User::factory()->create();

        $protectedUser->forceFill($protectedUserData->toArray())->exists = true;

        actingAs($admin);

        expect($admin->canImpersonate())->toBeTrue()
            ->and($regularUser->canBeImpersonated())->toBeTrue();

        Mirror::start($regularUser);

        expect(Mirror::isImpersonating())->toBeTrue();

        Mirror::stop();

        expect($protectedUser->canBeImpersonated())->toBeFalse();

        try {
            Mirror::start($protectedUser);
            expect(false)->toBeTrue();
        } catch (ImpersonationException $impersonationException) {
            expect($impersonationException->getMessage())->toContain('cannot be impersonated');
        }
    });
});
