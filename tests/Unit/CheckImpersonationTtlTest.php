<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Mirror\Facades\Mirror;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    Config::set('mirror.enabled', true);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('CheckImpersonationTtl middleware', function (): void {
    it('allows request to continue when not impersonating', function (): void {
        Route::middleware('mirror.ttl')->get('/test', fn () => response()->json(['status' => 'ok']));

        $admin = User::factory()->create();

        actingAs($admin);

        get('/test')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    });

    it('allows request to continue when impersonating and not expired', function (): void {
        Config::set('mirror.ttl', 3600);

        Route::middleware('mirror.ttl')->get('/test', fn () => response()->json(['status' => 'ok']));

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        Mirror::start($user);

        get('/test')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    });

    it('allows request when TTL is null (no expiration)', function (): void {
        Config::set('mirror.ttl');

        Route::middleware('mirror.ttl')->get('/test', fn () => response()->json(['status' => 'ok']));

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        Mirror::start($user);

        // Travel forward in time to simulate old session
        Carbon::setTestNow(Carbon::now()->addSeconds(999999));

        get('/test')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    });

    it('redirects and stops impersonation when session has expired', function (): void {
        Config::set('mirror.ttl', 3600);

        Route::middleware('mirror.ttl')->get('/test', fn () => response()->json(['status' => 'ok']));

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        Mirror::start($user, '/admin/users');

        expect(Mirror::isImpersonating())->toBeTrue();

        // Travel forward in time past the TTL
        Carbon::setTestNow(Carbon::now()->addSeconds(3601));

        get('/test')
            ->assertRedirect('/admin/users')
            ->assertSessionHas('warning', 'Your impersonation session has expired and you have been returned to your original account.');

        expect(Mirror::isImpersonating())->toBeFalse()
            ->and(auth()->id())->toBe($admin->id);
    });

    it('redirects to root when no leave redirect URL is set and session expired', function (): void {
        Config::set('mirror.ttl', 3600);

        Route::middleware('mirror.ttl')->get('/test', fn () => response()->json(['status' => 'ok']));

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        Mirror::start($user);

        // Travel forward in time past the TTL
        Carbon::setTestNow(Carbon::now()->addSeconds(3601));

        get('/test')
            ->assertRedirect('/')
            ->assertSessionHas('warning');
    });

    it('can be used with other middlewares', function (): void {
        Config::set('mirror.ttl', 3600);

        Route::middleware(['mirror.ttl', 'auth'])->get('/protected', fn () => response()->json(['user_id' => auth()->id()]));

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        Mirror::start($user);

        get('/protected')
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);
    });
});

describe('CheckImpersonationTtl middleware configuration', function (): void {
    it('reads TTL from config', function (): void {
        Config::set('mirror.ttl', 7200);

        Route::middleware('mirror.ttl')->get('/test', fn () => response()->json(['status' => 'ok']));

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        Mirror::start($user);

        // Travel forward to just under custom TTL
        Carbon::setTestNow(Carbon::now()->addSeconds(7199));

        get('/test')
            ->assertOk();

        // Travel forward to just over custom TTL
        Carbon::setTestNow(Carbon::now()->addSeconds(2));

        get('/test')
            ->assertRedirect();
    });

    it('respects disabled TTL (null)', function (): void {
        Config::set('mirror.ttl');

        Route::middleware('mirror.ttl')->get('/test', fn () => response()->json(['status' => 'ok']));

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        Mirror::start($user);

        // Travel forward in time by a very long period
        Carbon::setTestNow(Carbon::now()->addYears(30));

        get('/test')
            ->assertOk();
    });
});
