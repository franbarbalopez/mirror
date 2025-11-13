<?php

use Illuminate\Support\Facades\Route;
use Mirror\Facades\Mirror;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

describe('RequireImpersonation middleware', function (): void {
    it('blocks request with 403 when not impersonating', function (): void {
        Route::middleware('mirror.require')->get('/impersonation-only', fn () => response()->json(['status' => 'ok']));

        $admin = User::factory()->create();

        actingAs($admin);

        get('/impersonation-only')
            ->assertForbidden();
    });

    it('allows request when impersonating', function (): void {
        Route::middleware('mirror.require')->get('/impersonation-only', fn () => response()->json(['status' => 'ok']));

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        Mirror::start($user);

        get('/impersonation-only')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    });

    it('returns correct error message', function (): void {
        Route::middleware('mirror.require')->get('/impersonation-only', fn () => response()->json(['status' => 'ok']));

        $admin = User::factory()->create();

        actingAs($admin);

        $response = get('/impersonation-only');

        $response->assertForbidden();

        expect($response->exception->getMessage())->toContain('This action requires active impersonation');
    });

    it('works with route groups', function (): void {
        Route::middleware('mirror.require')->group(function (): void {
            Route::get('/debug/session', fn () => response()->json(['session' => 'data']));
            Route::get('/debug/cache', fn () => response()->json(['cache' => 'data']));
            Route::post('/debug/clear', fn () => response()->json(['cleared' => true]));
        });

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        get('/debug/session')
            ->assertForbidden();

        get('/debug/cache')
            ->assertForbidden();

        post('/debug/clear')
            ->assertForbidden();

        Mirror::start($user);

        get('/debug/session')
            ->assertOk();

        get('/debug/cache')
            ->assertOk();

        post('/debug/clear')
            ->assertOk();
    });

    it('can be used with other middlewares', function (): void {
        Route::middleware(['auth', 'mirror.require'])->get('/special', fn () => response()->json(['user_id' => auth()->id()]));

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        Mirror::start($user);

        get('/special')
            ->assertOk()
            ->assertJson(['user_id' => $user->id]);
    });

    it('blocks access after stopping impersonation', function (): void {
        Route::middleware('mirror.require')->get('/require-impersonation', fn () => response()->json(['status' => 'ok']));

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        Mirror::start($user);

        get('/require-impersonation')
            ->assertOk();

        Mirror::stop();

        get('/require-impersonation')
            ->assertForbidden();
    });
});

describe('RequireImpersonation middleware combinations', function (): void {
    it('works with multiple middleware layers', function (): void {
        Route::middleware(['auth', 'mirror.require'])->get('/restricted', fn () => response()->json(['data' => 'sensitive']));

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        get('/restricted')
            ->assertForbidden();

        Mirror::start($user);

        get('/restricted')
            ->assertOk();
    });
});

describe('RequireImpersonation edge cases', function (): void {
    it('handles unauthenticated requests', function (): void {
        Route::middleware('mirror.require')->get('/protected', fn () => response()->json(['status' => 'ok']));

        get('/protected')
            ->assertForbidden();
    });
});
