<?php

use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Mirror\Facades\Mirror;

use function Pest\Laravel\actingAs;

it('renders impersonating directives', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);
    Mirror::impersonate(User::factory()->create());

    $rendered = Blade::render('@impersonating yes @endimpersonating @notImpersonating no @endnotImpersonating');

    expect($rendered)->toContain('yes')
        ->not->toContain('no');
});

it('renders capability directives', function (): void {
    $user = User::factory()->create();

    actingAs($user);

    $rendered = Blade::render('@canImpersonate yes @endcanImpersonate @canBeImpersonated($user) target @endcanBeImpersonated', [
        'user' => $user,
    ]);

    expect($rendered)->toContain('yes')
        ->and($rendered)->toContain('target');
});

it('supports guard-specific impersonating directives', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);
    Mirror::impersonate(User::factory()->create());

    $rendered = Blade::render('@impersonating("web") web @endimpersonating @impersonating("admin") admin @endimpersonating');

    expect($rendered)->toContain('web')
        ->not->toContain('admin');
});

it('hides capability directives for guests', function (): void {
    $rendered = Blade::render('@canImpersonate yes @endcanImpersonate @canBeImpersonated no @endcanBeImpersonated');

    expect($rendered)->not->toContain('yes')
        ->not->toContain('no');
});
