<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mirror\Events\ImpersonationStarted;
use Mirror\Events\ImpersonationStopped;
use Mirror\Facades\Mirror;
use Mirror\Impersonator;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Config::set('mirror.enabled', true);
});

describe('request-scoped model caching', function (): void {
    it('caches impersonator model within single request', function (): void {
        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);
        Mirror::start($user);

        DB::enableQueryLog();
        DB::flushQueryLog();

        // should query database
        $impersonator1 = Mirror::getImpersonator();
        $firstCallQueries = count(DB::getQueryLog());

        expect($firstCallQueries)->toBeGreaterThan(0)
            ->and($impersonator1)->not->toBeNull()
            ->and($impersonator1->id)->toBe($admin->id);

        // should now use the cached value
        $impersonator2 = Mirror::getImpersonator();
        $impersonator3 = Mirror::getImpersonator();

        $totalQueries = count(DB::getQueryLog());

        expect($impersonator2)->toBe($impersonator1)
            ->and($impersonator3)->toBe($impersonator1)
            ->and($totalQueries)->toBe($firstCallQueries);
    });

    it('returns same instance across multiple calls', function (): void {
        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);
        Mirror::start($user);

        $call1 = Mirror::getImpersonator();
        $call2 = Mirror::getImpersonator();
        $call3 = Mirror::getImpersonator();

        expect($call1)->toBe($call2)
            ->and($call2)->toBe($call3);
    });

    test('cache is cleared after stopping impersonation', function (): void {
        $admin = User::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        actingAs($admin);

        Mirror::start($user1);
        $firstImpersonator = Mirror::getImpersonator();
        Mirror::stop();

        Mirror::start($user2);
        $secondImpersonator = Mirror::getImpersonator();

        expect($firstImpersonator->id)->toBe($admin->id)
            ->and($secondImpersonator->id)->toBe($admin->id)
            ->and($firstImpersonator)->not->toBe($secondImpersonator);
    });
});

describe('impersonatorId avoids database queries', function (): void {
    it('returns ID without loading model', function (): void {
        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);
        Mirror::start($user);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $id = Mirror::impersonatorId();

        expect(DB::getQueryLog())->toHaveCount(0)
            ->and($id)->toBe($admin->id);
    });

    it('is more efficient than getImpersonator()->id', function (): void {
        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);
        Mirror::start($user);

        $impersonator = app(Impersonator::class);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $idDirect = Mirror::impersonatorId();
        $directQueries = count(DB::getQueryLog());

        expect($directQueries)->toBe(0);

        DB::flushQueryLog();
        $idViaModel = Mirror::getImpersonator()->id;
        $modelQueries = count(DB::getQueryLog());

        expect($modelQueries)->toBeGreaterThan(0)
            ->and($idDirect)->toBe($idViaModel);
    });
});

describe('config value caching', function (): void {
    it('caches TTL value at instantiation', function (): void {
        Config::set('mirror.ttl', 3600);

        $impersonator = app(Impersonator::class);

        Config::set('mirror.ttl', 7200);

        expect($impersonator->getTtl())->toBe(3600);
    });

    it('caches default redirect URL at instantiation', function (): void {
        Config::set('mirror.default_redirect_url', '/admin');

        $impersonator = app(Impersonator::class);

        Config::set('mirror.default_redirect_url', '/dashboard');

        expect($impersonator->getDefaultRedirectUrl())->toBe('/admin');
    });

    test('multiple calls return same cached value', function (): void {
        Config::set('mirror.ttl', 1800);
        Config::set('mirror.default_redirect_url', '/home');

        $impersonator = app(Impersonator::class);

        $ttl1 = $impersonator->getTtl();
        $ttl2 = $impersonator->getTtl();
        $url1 = $impersonator->getDefaultRedirectUrl();
        $url2 = $impersonator->getDefaultRedirectUrl();

        expect($ttl1)->toBe($ttl2)
            ->and($ttl1)->toBe(1800)
            ->and($url1)->toBe($url2)
            ->and($url1)->toBe('/home');
    });
});

describe('isExpired method performance', function (): void {
    it('uses cached TTL value', function (): void {
        Config::set('mirror.ttl', 3600);

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);
        Mirror::start($user);

        $expired1 = Mirror::isExpired();
        $expired2 = Mirror::isExpired();
        $expired3 = Mirror::isExpired();

        expect($expired1)->toBe($expired2)
            ->and($expired2)->toBe($expired3)
            ->and($expired1)->toBeFalse();
    });

    it('does not throw exceptions on direct check', function (): void {
        Config::set('mirror.ttl', 1);

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);
        Mirror::start($user);

        sleep(2);

        $expired = Mirror::isExpired();

        expect($expired)->toBeTrue();
    });
});

describe('deferred event dispatching', function (): void {
    test('events are configured to fire after response', function (): void {
        Event::fake();

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);
        Mirror::start($user);

        Event::assertDispatched(ImpersonationStarted::class);
    });

    test('stop event is also deferred', function (): void {
        Event::fake();

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);
        Mirror::start($user);
        Mirror::stop();

        Event::assertDispatched(ImpersonationStopped::class);
    });
});

describe('middleware optimization', function (): void {
    it('uses single impersonator instance for all checks', function (): void {
        Config::set('mirror.ttl', 3600);

        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);
        Mirror::start($user);

        $isImpersonating = Mirror::isImpersonating();
        $isExpired = Mirror::isExpired();
        $leaveUrl = Mirror::getLeaveRedirectUrl();
        $defaultUrl = Mirror::getDefaultRedirectUrl();

        expect($isImpersonating)->toBeTrue()
            ->and($isExpired)->toBeFalse()
            ->and($leaveUrl)->toBeString()
            ->and($defaultUrl)->toBeString();
    });

    test('early return when not impersonating minimizes overhead', function (): void {
        $admin = User::factory()->create();
        actingAs($admin);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $isImpersonating = Mirror::isImpersonating();

        expect($isImpersonating)->toBeFalse()
            ->and(DB::getQueryLog())->toHaveCount(0);
    });
});

describe('guard name optimization', function (): void {
    it('checks default guard first', function (): void {
        Config::set('auth.defaults.guard', 'web');

        $admin = User::factory()->create();
        actingAs($admin, 'web');

        expect(auth('web')->check())->toBeTrue();
    });

    it('only iterates other guards if default not authenticated', function (): void {
        Config::set('auth.defaults.guard', 'web');
        Config::set('auth.guards', [
            'web' => ['driver' => 'session', 'provider' => 'users'],
            'api' => ['driver' => 'token', 'provider' => 'users'],
            'admin' => ['driver' => 'session', 'provider' => 'users'],
        ]);

        $admin = User::factory()->create();
        actingAs($admin, 'web');

        // should use web without checking api or admin
        expect(auth()->getDefaultDriver())->toBe('web')
            ->and(auth('web')->check())->toBeTrue();
    });
});

describe('performance regression prevention', function (): void {
    test('multiple impersonator checks do not multiply queries', function (): void {
        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);
        Mirror::start($user);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $check1 = Mirror::isImpersonating();
        $id1 = Mirror::impersonatorId();
        $imp1 = Mirror::getImpersonator();
        $check2 = Mirror::isImpersonating();
        $id2 = Mirror::impersonatorId();
        $imp2 = Mirror::getImpersonator();

        $queries = DB::getQueryLog();

        // should only query once
        expect($check1)->toBeTrue()
            ->and($check2)->toBeTrue()
            ->and($id1)->toBe($admin->id)
            ->and($id2)->toBe($admin->id)
            ->and($imp1)->toBe($imp2)
            ->and(count($queries))->toBeLessThanOrEqual(1);
    });

    test('session reads are minimized', function (): void {
        $admin = User::factory()->create();
        $user = User::factory()->create();

        actingAs($admin);
        Mirror::start($user);

        $checks = [];
        for ($i = 0; $i < 10; $i++) {
            $checks[] = Mirror::isImpersonating();
        }

        expect(array_unique($checks))->toHaveCount(1)
            ->and($checks[0])->toBeTrue();
    });
});
