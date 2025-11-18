<?php

use App\Models\User;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;

it('shows content when user can be impersonated', function (): void {
    $user = User::factory()->create();

    actingAs($user);

    $blade = <<<'BLADE'
        @canBeImpersonated
            <span>This user can be impersonated</span>
        @endcanBeImpersonated
    BLADE;

    $rendered = Blade::render($blade);

    expect($rendered)->toContain('This user can be impersonated');
});

it('hides content when user cannot be impersonated', function (): void {
    $user = Mockery::mock(User::class, function (MockInterface $mock): void {
        $mock->shouldReceive('canBeImpersonated')
            ->andReturn(false);
    });

    actingAs($user);

    $blade = <<<'BLADE'
        @canBeImpersonated
            <span>This user can be impersonated</span>
        @endcanBeImpersonated
    BLADE;

    $rendered = Blade::render($blade);

    expect($rendered)->not->toContain('This user can be impersonated');
});

it('works with else clause', function (): void {
    $user = Mockery::mock(User::class, function (MockInterface $mock): void {
        $mock->shouldReceive('canBeImpersonated')
            ->andReturn(false);
    });

    actingAs($user);

    $blade = <<<'BLADE'
        @canBeImpersonated
            <span>Can be impersonated</span>
        @else
            <span>Protected User</span>
        @endcanBeImpersonated
    BLADE;

    $rendered = Blade::render($blade);

    expect($rendered)->not->toContain('Can be impersonated')
        ->and($rendered)->toContain('Protected User');
});

it('works with specific user parameter', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);

    $user = User::factory()->create(['name' => 'Protected User']);

    $blade = <<<'BLADE'
        @canBeImpersonated($user)
            <div>Protected User Status</div>
        @else
            <div>Normal User Status</div>
        @endcanBeImpersonated
    BLADE;

    $rendered = Blade::render($blade, ['user' => $user]);

    expect($rendered)->not->toContain('Normal User Status')
        ->and($rendered)->toContain('Protected User Status');
});

it('returns false when no user is authenticated', function (): void {
    $blade = <<<'BLADE'
        @canBeImpersonated
            <span>Can be impersonated</span>
        @else
            <span>Not authenticated</span>
        @endcanBeImpersonated
    BLADE;

    $rendered = Blade::render($blade);

    expect($rendered)->not->toContain('Can be impersonate')
        ->and($rendered)->toContain('Not authenticated');
});
