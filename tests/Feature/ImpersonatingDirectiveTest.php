<?php

use App\Models\User;
use Mirror\Facades\Mirror;

use function Pest\Laravel\actingAs;

it('shows content when impersonating', function (): void {
    $admin = User::factory()->create();
    $user = User::factory()->create();

    actingAs($admin);

    Mirror::start($user);

    $blade = <<<'BLADE'
        @impersonating
            <div>You are impersonating</div>
        @endimpersonating
    BLADE;

    $rendered = Blade::render($blade);

    expect($rendered)->toContain('You are impersonating');
});

it('works with else clause when impersonating', function (): void {
    $admin = User::factory()->create();
    $user = User::factory()->create();

    actingAs($admin);

    Mirror::start($user);

    $blade = <<<'BLADE'
        @impersonating
            <div>Impersonating</div>
        @else
            <div>Not impersonating</div>
        @endimpersonating
    BLADE;

    $rendered = Blade::render($blade);

    expect($rendered)->toContain('Impersonating')
        ->and($rendered)->not->toContain('Not impersonating');
});

it('hides content when not impersonating', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);

    $blade = <<<'BLADE'
        @impersonating
            <div>You are impersonating</div>
        @endimpersonating
    BLADE;

    $rendered = Blade::render($blade);

    expect($rendered)->not->toContain('You are impersonating');
});

it('works with else clause when not impersonating', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);

    $blade = <<<'BLADE'
        @impersonating
            <div>Impersonating</div>
        @else
            <div>Not impersonating</div>
        @endimpersonating
    BLADE;

    $rendered = Blade::render($blade);

    expect($rendered)->not->toContain('Impersonating')
        ->and($rendered)->toContain('Not impersonating');
});

it('works with guard parameter', function (): void {
    $admin = User::factory()->create();
    $user = User::factory()->create();

    actingAs($admin, 'web');

    Mirror::start($user);

    $blade = <<<'BLADE'
        @impersonating('web')
            <div>Impersonating on web guard</div>
        @endimpersonating
    BLADE;

    $rendered = Blade::render($blade);

    expect($rendered)->toContain('Impersonating on web guard');
});
