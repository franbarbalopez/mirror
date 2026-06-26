<?php

use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Session;
use Illuminate\View\ViewException;
use Mirror\Exceptions\MissingImpersonationSignature;
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

it('renders not impersonating directives when no impersonation is active', function (): void {
    $rendered = Blade::render('@impersonating yes @endimpersonating @notImpersonating no @endnotImpersonating');

    expect($rendered)->toContain('no')
        ->not->toContain('yes');
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

it('detects a missing signature when rendering impersonating directives', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);
    Mirror::impersonate(User::factory()->create());

    Session::forget('mirror.impersonation.signature');

    try {
        Blade::render('@impersonating yes @endimpersonating');
    } catch (ViewException $viewException) {
        expect($viewException->getPrevious())->toBeInstanceOf(MissingImpersonationSignature::class);

        return;
    }

    $this->fail('Expected the Blade render to fail when the impersonation signature is missing.');
});

it('hides capability directives for guests', function (): void {
    $rendered = Blade::render('@canImpersonate yes @endcanImpersonate @canBeImpersonated no @endcanBeImpersonated');

    expect($rendered)->not->toContain('yes')
        ->not->toContain('no');
});
