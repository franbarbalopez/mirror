<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Mirror\Events\ImpersonationExpired;
use Mirror\Facades\Mirror;
use Mirror\ImpersonationManager;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('requires active impersonation', function (): void {
    Route::middleware('mirror.require')->get('/requires-impersonation', fn (): string => 'ok');

    get('/requires-impersonation')->assertForbidden();
});

it('prevents access during impersonation', function (): void {
    Route::middleware('mirror.prevent')->get('/forbidden-while-impersonating', fn (): string => 'ok');

    $admin = User::factory()->create();

    actingAs($admin);
    Mirror::impersonate(User::factory()->create());

    get('/forbidden-while-impersonating')->assertForbidden();
});

it('stops expired impersonation and redirects', function (): void {
    Event::fake();
    Config::set('mirror.ttl', 60);

    Route::middleware('mirror.ttl')->get('/ttl', fn (): string => 'ok');

    $admin = User::factory()->create();

    actingAs($admin);
    Config::set('mirror.redirects.expired', '/expired');

    Mirror::impersonate(User::factory()->create(), context: [
        'reason' => 'support',
        'redirect' => '/admin',
    ]);

    Carbon::setTestNow(Carbon::now()->addSeconds(61));

    get('/ttl')->assertRedirect('/expired')->assertSessionHas('warning');

    expect(app(ImpersonationManager::class)->active())->toBeFalse()
        ->and(auth()->id())->toBe($admin->id);

    Event::assertDispatched(ImpersonationExpired::class);
});

it('uses the configured expired redirect', function (): void {
    Config::set('mirror.ttl', 60);
    Config::set('mirror.redirects.expired', '/expired');

    Route::middleware('mirror.ttl')->get('/ttl-expired-default', fn (): string => 'ok');

    $admin = User::factory()->create();

    actingAs($admin);
    Mirror::impersonate(User::factory()->create());

    Carbon::setTestNow(Carbon::now()->addSeconds(61));

    get('/ttl-expired-default')->assertRedirect('/expired');
});

it('allows requests when impersonation is active but not expired', function (): void {
    Config::set('mirror.ttl', 60);

    Route::middleware('mirror.ttl')->get('/ttl-valid', fn (): string => 'ok');

    $admin = User::factory()->create();

    actingAs($admin);
    Mirror::impersonate(User::factory()->create());

    get('/ttl-valid')->assertOk()->assertSee('ok');
});

it('allows requests when no impersonation is active', function (): void {
    Route::middleware('mirror.ttl')->get('/ttl-inactive', fn (): string => 'ok');

    get('/ttl-inactive')->assertOk()->assertSee('ok');
});

it('allows access when impersonation is required and active', function (): void {
    Route::middleware('mirror.require')->get('/requires-active-impersonation', fn (): string => 'ok');

    $admin = User::factory()->create();

    actingAs($admin);
    Mirror::impersonate(User::factory()->create());

    get('/requires-active-impersonation')->assertOk()->assertSee('ok');
});

it('allows access when impersonation is prevented and inactive', function (): void {
    Route::middleware('mirror.prevent')->get('/allows-regular-users', fn (): string => 'ok');

    get('/allows-regular-users')->assertOk()->assertSee('ok');
});
