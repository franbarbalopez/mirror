<?php

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Blade;
use Mirror\Concerns\Impersonatable;
use Mirror\Facades\Mirror;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;

describe('@impersonating directive', function (): void {
    it('shows content when impersonating', function (): void {
        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        Mirror::start($user);

        $blade = <<<'BLADE'
        @impersonating
            <div class="impersonation-banner">You are impersonating</div>
        @endimpersonating
        BLADE;

        $rendered = Blade::render($blade);

        expect($rendered)->toContain('impersonation-banner')
            ->and($rendered)->toContain('You are impersonating');
    });

    it('hides content when not impersonating', function (): void {
        $admin = User::factory()->create();

        actingAs($admin);

        $blade = <<<'BLADE'
        @impersonating
            <div class="impersonation-banner">You are impersonating</div>
        @endimpersonating
        BLADE;

        $rendered = Blade::render($blade);

        expect($rendered)->not->toContain('impersonation-banner')
            ->and($rendered)->not->toContain('You are impersonating');
    });

    it('works with else clause when not impersonating', function (): void {
        $admin = User::factory()->create();

        actingAs($admin);

        $blade = <<<'BLADE'
        @impersonating
            <div class="impersonating">Impersonating</div>
        @else
            <div class="not-impersonating">Not impersonating</div>
        @endimpersonating
        BLADE;

        $rendered = Blade::render($blade);

        expect($rendered)->not->toContain('Impersonating')
            ->and($rendered)->toContain('not-impersonating')
            ->and($rendered)->toContain('Not impersonating');
    });

    it('works with else clause when impersonating', function (): void {
        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        Mirror::start($user);

        $blade = <<<'BLADE'
        @impersonating
            <div class="impersonating">Impersonating</div>
        @else
            <div class="not-impersonating">Not impersonating</div>
        @endimpersonating
        BLADE;

        $rendered = Blade::render($blade);

        expect($rendered)->toContain('impersonating')
            ->and($rendered)->toContain('Impersonating')
            ->and($rendered)->not->toContain('not-impersonating');
    });

    it('works with guard parameter', function (): void {
        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin, 'web');

        Mirror::start($user);

        $blade = <<<'BLADE'
        @impersonating('web')
            <div class="web-impersonating">Impersonating on web guard</div>
        @endimpersonating
        BLADE;

        $rendered = Blade::render($blade);

        expect($rendered)->toContain('web-impersonating');
    });
});

describe('@canImpersonate directive', function (): void {
    it('shows content when user can impersonate', function (): void {
        $admin = User::factory()->create();

        actingAs($admin);

        $blade = <<<'BLADE'
        @canImpersonate
            <button class="impersonate-btn">Impersonate User</button>
        @endcanImpersonate
        BLADE;

        $rendered = Blade::render($blade);

        expect($rendered)->toContain('impersonate-btn')
            ->and($rendered)->toContain('Impersonate User');
    });

    it('hides content when user cannot impersonate', function (): void {
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

        actingAs($restrictedUser);

        $blade = <<<'BLADE'
        @canImpersonate
            <button class="impersonate-btn">Impersonate User</button>
        @endcanImpersonate
        BLADE;

        $rendered = Blade::render($blade);

        expect($rendered)->not->toContain('impersonate-btn')
            ->and($rendered)->not->toContain('Impersonate User');
    });

    it('hides content when not authenticated', function (): void {
        $blade = <<<'BLADE'
        @canImpersonate
            <button class="impersonate-btn">Impersonate User</button>
        @endcanImpersonate
        BLADE;

        $rendered = Blade::render($blade);

        expect($rendered)->not->toContain('impersonate-btn');
    });

    it('works with else clause when cannot impersonate', function (): void {
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

        actingAs($restrictedUser);

        $blade = <<<'BLADE'
        @canImpersonate
            <button>Can Impersonate</button>
        @else
            <span class="no-permission">No permission to impersonate</span>
        @endcanImpersonate
        BLADE;

        $rendered = Blade::render($blade);

        expect($rendered)->not->toContain('Can Impersonate')
            ->and($rendered)->toContain('no-permission')
            ->and($rendered)->toContain('No permission to impersonate');
    });

    it('works with guard parameter', function (): void {
        $user = User::factory()->create();

        actingAs($user, 'web');

        $blade = <<<'BLADE'
        @canImpersonate('web')
            <button class="web-impersonate">Impersonate on web</button>
        @endcanImpersonate
        BLADE;

        $rendered = Blade::render($blade);

        expect($rendered)->toContain('web-impersonate');
    });
});

describe('@canBeImpersonated directive', function (): void {
    it('shows content when user can be impersonated', function (): void {
        $user = User::factory()->create();

        actingAs($user);

        $blade = <<<'BLADE'
        @canBeImpersonated
            <span class="can-impersonate-badge">This user can be impersonated</span>
        @endcanBeImpersonated
        BLADE;

        $rendered = Blade::render($blade);

        expect($rendered)->toContain('can-impersonate-badge')
            ->and($rendered)->toContain('can be impersonated');
    });

    it('hides content when user cannot be impersonated', function (): void {
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

        actingAs($protectedUser);

        $blade = <<<'BLADE'
        @canBeImpersonated
            <span class="can-impersonate-badge">This user can be impersonated</span>
        @endcanBeImpersonated
        BLADE;

        $rendered = Blade::render($blade);

        expect($rendered)->not->toContain('can-impersonate-badge')
            ->and($rendered)->not->toContain('can be impersonated');
    });

    it('works with else clause', function (): void {
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

        actingAs($protectedUser);

        $blade = <<<'BLADE'
        @canBeImpersonated
            <span class="allowed">Can be impersonated</span>
        @else
            <span class="protected-badge">Protected User</span>
        @endcanBeImpersonated
        BLADE;

        $rendered = Blade::render($blade);

        expect($rendered)->not->toContain('allowed')
            ->and($rendered)->toContain('protected-badge')
            ->and($rendered)->toContain('Protected User');
    });

    it('works with specific user parameter', function (): void {
        $admin = User::factory()->create();

        actingAs($admin);

        $protectedUser = new class extends Authenticatable
        {
            use Impersonatable;

            protected $guarded = [];

            public function canBeImpersonated(): bool
            {
                return false;
            }
        };
        $user = User::factory()->create(['name' => 'Protected User']);
        $protectedUser->forceFill($user->toArray())->exists = true;

        $blade = <<<'BLADE'
        <div class="user-normal">Normal User Status</div>
        <div class="user-protected">Protected User Status</div>
        BLADE;

        $rendered = Blade::render($blade);

        expect($rendered)->toContain('user-normal')
            ->and($rendered)->toContain('user-protected');
    });

    it('returns false when no user is authenticated', function (): void {
        expect(auth()->check())->toBeFalse();

        $blade = <<<'BLADE'
        @canBeImpersonated
            <span class="can-impersonate">Can be impersonated</span>
        @else
            <span class="not-authenticated">Not authenticated</span>
        @endcanBeImpersonated
        BLADE;

        $rendered = Blade::render($blade);

        expect($rendered)->not->toContain('can-impersonate')
            ->and($rendered)->toContain('not-authenticated');
    });
});
