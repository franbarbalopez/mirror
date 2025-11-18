<?php

use App\Models\User;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;

it('shows content when user can impersonate', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);

    $blade = <<<'BLADE'
        @canImpersonate
            <button>Impersonate User</button>
        @endcanImpersonate
    BLADE;

    $rendered = Blade::render($blade);

    expect($rendered)->toContain('Impersonate User');
});

it('hides content when user cannot impersonate', function (): void {
    $user = Mockery::mock(User::class, function (MockInterface $mock): void {
        $mock->shouldReceive('canImpersonate')
            ->andReturn(false);
    });

    actingAs($user);

    $blade = <<<'BLADE'
        @canImpersonate
            <button>Impersonate User</button>
        @endcanImpersonate
    BLADE;

    $rendered = Blade::render($blade);

    expect($rendered)->not->toContain('Impersonate User');
});

it('hides content when not authenticated', function (): void {
    $blade = <<<'BLADE'
        @canImpersonate
            <button>Impersonate User</button>
        @endcanImpersonate
    BLADE;

    $rendered = Blade::render($blade);

    expect($rendered)->not->toContain('Impersonate User');
});

it('works with else clause when cannot impersonate', function (): void {
    $user = Mockery::mock(User::class, function (MockInterface $mock): void {
        $mock->shouldReceive('canImpersonate')
            ->andReturn(false);
    });

    actingAs($user);

    $blade = <<<'BLADE'
        @canImpersonate
            <button>Can Impersonate</button>
        @else
            <span>No permission to impersonate</span>
        @endcanImpersonate
    BLADE;

    $rendered = Blade::render($blade);

    expect($rendered)->not->toContain('Can Impersonate')
        ->and($rendered)->toContain('No permission to impersonate');
});

it('works with guard parameter', function (): void {
    $user = User::factory()->create();

    actingAs($user, 'web');

    $blade = <<<'BLADE'
        @canImpersonate('web')
            <button>Impersonate on web</button>
        @endcanImpersonate
    BLADE;

    $rendered = Blade::render($blade);

    expect($rendered)->toContain('Impersonate on web');
});
