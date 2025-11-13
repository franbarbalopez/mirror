<?php

use Illuminate\Support\Facades\Route;
use Mirror\Facades\Mirror;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

describe('PreventImpersonation middleware', function (): void {
    it('allows request when not impersonating', function (): void {
        Route::middleware('mirror.prevent')->get('/sensitive-action', fn () => response()->json(['status' => 'ok']));

        $admin = User::factory()->create();

        actingAs($admin);

        get('/sensitive-action')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    });

    it('blocks request with 403 when impersonating', function (): void {
        Route::middleware('mirror.prevent')->get('/sensitive-action', fn () => response()->json(['status' => 'ok']));

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        Mirror::start($user);

        get('/sensitive-action')
            ->assertForbidden();
    });

    it('returns correct error message', function (): void {
        Route::middleware('mirror.prevent')->get('/sensitive-action', fn () => response()->json(['status' => 'ok']));

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        Mirror::start($user);

        $response = get('/sensitive-action');

        $response->assertForbidden();

        expect($response->exception->getMessage())->toContain('This action is not allowed while impersonating another user');
    });

    it('works with route groups', function (): void {
        Route::middleware('mirror.prevent')->group(function (): void {
            Route::get('/billing', fn () => response()->json(['page' => 'billing']));
            Route::post('/billing/update', fn () => response()->json(['updated' => true]));
            Route::delete('/billing/cancel', fn () => response()->json(['cancelled' => true]));
        });

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        get('/billing')
            ->assertOk();

        post('/billing/update')
            ->assertOk();

        delete('/billing/cancel')
            ->assertOk();

        Mirror::start($user);

        get('/billing')
            ->assertForbidden();

        post('/billing/update')
            ->assertForbidden();

        delete('/billing/cancel')
            ->assertForbidden();
    });

    it('can be used with other middlewares', function (): void {
        Route::middleware(['auth', 'mirror.prevent'])->post('/critical-action', fn () => response()->json(['success' => true]));

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        Mirror::start($user);

        post('/critical-action')
            ->assertForbidden();
    });

    it('allows access after stopping impersonation', function (): void {
        Route::middleware('mirror.prevent')->get('/protected', fn () => response()->json(['status' => 'ok']));

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        Mirror::start($user);

        get('/protected')
            ->assertForbidden();

        Mirror::stop();

        get('/protected')
            ->assertOk();
    });
});

describe('PreventImpersonation middleware combinations', function (): void {
    it('works with multiple middleware layers', function (): void {
        Route::middleware(['auth', 'verified', 'mirror.prevent'])->post('/secure', fn () => response()->json(['data' => 'sensitive']));

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);

        Mirror::start($user);

        post('/secure')
            ->assertForbidden();
    });
});

describe('PreventImpersonation edge cases', function (): void {
    it('handles unauthenticated requests', function (): void {
        Route::middleware('mirror.prevent')->get('/protected', fn () => response()->json(['status' => 'ok']));

        get('/protected')
            ->assertOk();
    });
});
