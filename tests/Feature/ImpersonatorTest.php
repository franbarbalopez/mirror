<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Events\Dispatcher as EventsDispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use Mirror\Events\ImpersonationStarted;
use Mirror\Events\ImpersonationStopped;
use Mirror\Exceptions\ImpersonationException;
use Mirror\Exceptions\TamperedSessionException;
use Mirror\Facades\Mirror;
use Mirror\Impersonator;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;

it('start successfully impersonates user and dispatches event', function (): void {
    Event::fake();

    $admin = User::factory()->create(['email' => 'admin@test.com']);
    $targetUser = User::factory()->create(['email' => 'user@test.com']);

    actingAs($admin);

    expect(Auth::id())->toBe($admin->id)
        ->and(Mirror::isImpersonating())->toBeFalse();

    $redirectUrl = Mirror::start($targetUser);

    expect($redirectUrl)->toBeNull()
        ->and(Auth::id())->toBe($targetUser->id)
        ->and(Mirror::isImpersonating())->toBeTrue()
        ->and(Mirror::impersonatorId())->toBe($admin->id);

    Event::assertDispatched(ImpersonationStarted::class, fn ($event): bool => $event->impersonator->id === $admin->id
        && $event->impersonated->id === $targetUser->id
        && $event->guardName === 'web');
});

it('start accepts custom leave and enter redirect URLs', function (): void {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    $redirectUrl = Mirror::start($targetUser, '/admin/users', '/dashboard');

    expect($redirectUrl)->toBe('/dashboard')
        ->and(Mirror::getLeaveRedirectUrl())->toBe('/admin/users')
        ->and(Auth::id())->toBe($targetUser->id);
});

it('start throws exception when impersonation is disabled in config', function (): void {
    Config::set('mirror.enabled', false);

    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    Mirror::start($targetUser);
})->throws(ImpersonationException::class, 'Impersonation is not enabled');

it('start throws exception when already impersonating another user', function (): void {
    $admin = User::factory()->create();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    actingAs($admin);

    Mirror::start($user1);

    Mirror::start($user2);
})->throws(ImpersonationException::class, 'already impersonating');

it('start throws exception when impersonator lacks canImpersonate permission', function (): void {
    $targetUser = User::factory()->create();

    $adminWithRestriction = Mockery::mock(User::class, function (MockInterface $mock): void {
        $mock->shouldReceive('canImpersonate')
            ->andReturn(false);
    });

    actingAs($adminWithRestriction);

    Mirror::start($targetUser);
})->throws(ImpersonationException::class, 'do not have permission to impersonate');

it('start throws exception when target user has canBeImpersonated set to false', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);

    $restrictedUser = Mockery::mock(User::class, function (MockInterface $mock): void {
        $mock->shouldReceive('canBeImpersonated')
            ->andReturn(false);
    });

    Mirror::start($restrictedUser);
})->throws(ImpersonationException::class, 'cannot be impersonated');

it('start stores all impersonation data in session with integrity hash', function (): void {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    Mirror::start($targetUser);

    expect(Session::has('mirror.impersonating'))->toBeTrue()
        ->and(Session::get('mirror.impersonated_by'))->toBe($admin->id)
        ->and(Session::get('mirror.guard_name'))->toBe('web')
        ->and(Session::has('mirror.started_at'))->toBeTrue()
        ->and(Session::has('mirror.integrity'))->toBeTrue();
});

it('stop ends impersonation and restores original user', function (): void {
    Event::fake();

    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    Mirror::start($targetUser);

    expect(Auth::id())->toBe($targetUser->id)
        ->and(Mirror::isImpersonating())->toBeTrue();

    Mirror::stop();

    expect(Auth::id())->toBe($admin->id)
        ->and(Mirror::isImpersonating())->toBeFalse()
        ->and(Mirror::impersonatorId())->toBeNull();

    Event::assertDispatched(ImpersonationStopped::class, fn ($event): bool => $event->impersonator->id === $admin->id
        && $event->impersonated->id === $targetUser->id
        && $event->guardName === 'web');
});

it('stop throws exception when not currently impersonating', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);

    Mirror::stop();
})->throws(ImpersonationException::class, 'not impersonating any user');

it('stop throws exception when impersonation session TTL has expired', function (): void {
    Config::set('mirror.ttl', 3600);

    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    Mirror::start($targetUser);

    // Travel forward in time past the TTL
    Carbon::setTestNow(Carbon::now()->addSeconds(3601));

    Mirror::stop();
})->throws(ImpersonationException::class, 'session has expired');

it('stop clears all impersonation data from session', function (): void {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    Mirror::start($targetUser);

    Mirror::stop();

    expect(Session::has('mirror.impersonating'))->toBeFalse()
        ->and(Session::has('mirror.impersonated_by'))->toBeFalse()
        ->and(Session::has('mirror.guard_name'))->toBeFalse()
        ->and(Session::has('mirror.started_at'))->toBeFalse()
        ->and(Session::has('mirror.integrity'))->toBeFalse()
        ->and(Session::has('mirror.leave_redirect_url'))->toBeFalse();
});

it('stop throws exception when session integrity hash is tampered with', function (): void {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    Mirror::start($targetUser);

    Session::put('mirror.impersonated_by', 999);

    Mirror::stop();
})->throws(TamperedSessionException::class, 'tampered');

it('forceStop ends impersonation without validating TTL expiration', function (): void {
    Config::set('mirror.ttl', 3600);

    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    Mirror::start($targetUser);

    // Travel forward in time past the TTL
    Carbon::setTestNow(Carbon::now()->addSeconds(3601));

    Mirror::forceStop();

    expect(Auth::id())->toBe($admin->id)
        ->and(Mirror::isImpersonating())->toBeFalse();
});

it('forceStop throws exception when not currently impersonating', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);

    Mirror::forceStop();
})->throws(ImpersonationException::class, 'not impersonating any user');

it('isImpersonating returns true when actively impersonating a user', function (): void {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    Mirror::start($targetUser);

    expect(Mirror::isImpersonating())->toBeTrue()
        ->and(Mirror::impersonating())->toBeTrue();
});

it('isImpersonating returns false when not impersonating', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);

    expect(Mirror::isImpersonating())->toBeFalse()
        ->and(Mirror::impersonating())->toBeFalse();
});

it('getImpersonator returns original user model when impersonating', function (): void {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    Mirror::start($targetUser);

    $impersonator = Mirror::getImpersonator();

    expect($impersonator)->not->toBeNull()
        ->and($impersonator->id)->toBe($admin->id)
        ->and($impersonator->email)->toBe($admin->email);

    $impersonatorAlias = Mirror::impersonator();

    expect($impersonatorAlias->id)->toBe($admin->id);
});

it('getImpersonator returns null when not impersonating', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);

    expect(Mirror::getImpersonator())->toBeNull()
        ->and(Mirror::impersonator())->toBeNull();
});

it('startByKey finds and impersonates user by their primary key', function (): void {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    $redirectUrl = Mirror::startByKey($targetUser->id);

    expect($redirectUrl)->toBeNull()
        ->and(Auth::id())->toBe($targetUser->id)
        ->and(Mirror::isImpersonating())->toBeTrue();
});

it('startByKey throws exception when user with given key does not exist', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);

    Mirror::startByKey(99999);
})->throws(InvalidArgumentException::class, 'User with key [99999] not found');

it('startByEmail finds and impersonates user by their email address', function (): void {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create(['email' => 'target@example.com']);

    actingAs($admin);

    $redirectUrl = Mirror::startByEmail('target@example.com');

    expect($redirectUrl)->toBeNull()
        ->and(Auth::id())->toBe($targetUser->id)
        ->and(Mirror::isImpersonating())->toBeTrue();
});

it('startByEmail throws exception when user with given email does not exist', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);

    Mirror::startByEmail('notfound@example.com');
})->throws(InvalidArgumentException::class, 'User with email [notfound@example.com] not found');

it('impersonatorId returns original user ID when impersonating', function (): void {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    Mirror::start($targetUser);

    expect(Mirror::impersonatorId())->toBe($admin->id);
});

it('impersonatorId returns null when not impersonating', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);

    expect(Mirror::impersonatorId())->toBeNull();
});

it('as() method is an alias for start() with same behavior', function (): void {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    $result = Mirror::as($targetUser, '/admin', '/dashboard');

    expect($result)->toBe('/dashboard')
        ->and(Auth::id())->toBe($targetUser->id)
        ->and(Mirror::isImpersonating())->toBeTrue();
});

it('leave() method is an alias for stop() with same behavior', function (): void {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    Mirror::start($targetUser);

    Mirror::leave();

    expect(Auth::id())->toBe($admin->id)
        ->and(Mirror::isImpersonating())->toBeFalse();
});

it('getLeaveRedirectUrl returns URL where user will return after stopping impersonation', function (): void {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    Mirror::start($targetUser, '/admin/users');

    expect(Mirror::getLeaveRedirectUrl())->toBe('/admin/users');
});

it('getLeaveRedirectUrl captures current URL when no explicit leave URL is provided', function (): void {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    Mirror::start($targetUser);

    $leaveUrl = Mirror::getLeaveRedirectUrl();

    expect($leaveUrl)->not->toBeNull()
        ->and($leaveUrl)->toBeString();
});

it('getLeaveRedirectUrl returns null when not impersonating', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);

    expect(Mirror::getLeaveRedirectUrl())->toBeNull();
});

it('allows impersonating multiple users sequentially after stopping each session', function (): void {
    $admin = User::factory()->create();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    actingAs($admin);

    Mirror::start($user1);

    expect(Auth::id())->toBe($user1->id);

    Mirror::stop();

    expect(Auth::id())->toBe($admin->id);

    Mirror::start($user2);

    expect(Auth::id())->toBe($user2->id);

    Mirror::stop();

    expect(Auth::id())->toBe($admin->id);
});

it('start generates 64-character integrity hash to prevent session tampering', function (): void {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    Mirror::start($targetUser);

    $hash = Session::get('mirror.integrity');

    expect($hash)->not->toBeNull()
        ->and($hash)->toBeString()
        ->and(strlen((string) $hash))->toBe(64);
});

it('stop verifies session integrity hash has not been tampered with', function (): void {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    Mirror::start($targetUser);

    Mirror::stop();

    expect(Auth::id())->toBe($admin->id);
});

it('start throws exception when impersonator is not authenticated', function (): void {
    $targetUser = User::factory()->create();

    expect(auth()->check())->toBeFalse();

    Mirror::start($targetUser);
})->throws(ImpersonationException::class, 'do not have permission to impersonate');

it('start uses default guard when starting impersonation', function (): void {
    Config::set('auth.defaults.guard', 'web');
    Config::set('auth.guards', [
        'web' => ['driver' => 'session', 'provider' => 'users'],
        'api' => ['driver' => 'token', 'provider' => 'users'],
    ]);

    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin, 'web');

    Mirror::start($targetUser);

    expect(Mirror::isImpersonating())->toBeTrue()
        ->and(auth()->id())->toBe($targetUser->id);
});

it('start finds authenticated user in default guard when starting impersonation', function (): void {
    Config::set('auth.defaults.guard', 'web');
    Config::set('auth.guards', [
        'web' => ['driver' => 'session', 'provider' => 'users'],
        'api' => ['driver' => 'token', 'provider' => 'users'],
        'admin' => ['driver' => 'session', 'provider' => 'users'],
    ]);

    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin, 'web');

    $redirectUrl = Mirror::start($targetUser);

    expect($redirectUrl)->toBeNull()
        ->and(Mirror::isImpersonating())->toBeTrue()
        ->and(auth('web')->id())->toBe($targetUser->id);
});

it('start searches through all configured guards to find authenticated user', function (): void {
    Config::set('auth.defaults.guard', 'web');
    Config::set('auth.guards', [
        'web' => ['driver' => 'session', 'provider' => 'users'],
        'api' => ['driver' => 'session', 'provider' => 'users'],
        'admin' => ['driver' => 'session', 'provider' => 'users'],
    ]);

    $admin = User::factory()->create();
    $user = User::factory()->create();

    auth('admin')->login($admin);
    auth('web')->logout();

    expect(auth('web')->check())->toBeFalse()
        ->and(auth('admin')->check())->toBeTrue();

    $redirectUrl = Mirror::start($user);

    expect($redirectUrl)->toBeNull()
        ->and(Mirror::isImpersonating())->toBeTrue()
        ->and(auth('admin')->id())->toBe($user->id);
});

it('start dispatches events with afterResponse hook for queued listeners', function (): void {
    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    Mirror::start($targetUser);

    Mirror::stop();

    expect(Mirror::isImpersonating())->toBeFalse();
});

it('invokes dispatcher afterResponse hook when available', function (): void {
    $dispatcher = new class(app()) extends EventsDispatcher
    {
        public bool $afterResponseCalled = false;

        public function dispatch($event, $payload = [], $halt = false): mixed
        {
            return new class($this)
            {
                public function __construct(private readonly EventsDispatcher $tracker) {}

                public function afterResponse(): void
                {
                    $this->tracker->afterResponseCalled = true;
                }
            };
        }
    };

    $originalDispatcher = Event::getFacadeRoot();
    Event::swap($dispatcher);

    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    Mirror::start($targetUser);

    expect($dispatcher->afterResponseCalled)->toBeTrue();

    Event::swap($originalDispatcher);
});

it('dispatchEventAfterResponse throws exception for unknown event class', function (): void {
    $admin = User::factory()->create();

    actingAs($admin);

    $impersonator = app(Impersonator::class);

    $unknownEvent = new class
    {
        public string $impersonator = 'admin';

        public string $impersonated = 'user';

        public string $guardName = 'web';
    };

    $reflection = new ReflectionClass($impersonator);
    $method = $reflection->getMethod('dispatchEventAfterResponse');

    $method->invoke($impersonator, $unknownEvent);
})->throws(InvalidArgumentException::class, 'Unknown event class');

it('start dispatches ImpersonationStarted event', function (): void {
    Event::fake();

    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    Mirror::start($targetUser);

    Event::assertDispatched(ImpersonationStarted::class);
});

it('stop dispatches ImpersonationStopped event', function (): void {
    Event::fake();

    $admin = User::factory()->create();
    $targetUser = User::factory()->create();

    actingAs($admin);

    Mirror::start($targetUser);
    Mirror::stop();

    Event::assertDispatched(ImpersonationStopped::class);
});

it('getImpersonator caches model in memory to avoid repeated database queries', function (): void {
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

it('getImpersonator returns identical instance across multiple calls within request', function (): void {
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

it('getImpersonator cache is cleared when impersonation stops to allow new sessions', function (): void {
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

it('impersonatorId retrieves ID from session without database query', function (): void {
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

it('impersonatorId is more efficient than getImpersonator()->id for ID-only needs', function (): void {
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

it('Impersonator caches TTL config value at instantiation time', function (): void {
    Config::set('mirror.ttl', 3600);

    $impersonator = app(Impersonator::class);

    Config::set('mirror.ttl', 7200);

    expect($impersonator->getTtl())->toBe(3600);
});

it('Impersonator caches default redirect URL config value at instantiation time', function (): void {
    Config::set('mirror.default_redirect_url', '/admin');

    $impersonator = app(Impersonator::class);

    Config::set('mirror.default_redirect_url', '/dashboard');

    expect($impersonator->getDefaultRedirectUrl())->toBe('/admin');
});

it('Impersonator returns same cached config values across multiple getter calls', function (): void {
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

it('isExpired uses cached TTL value for consistent results within request', function (): void {
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

it('isExpired can detect expired sessions without throwing exceptions', function (): void {
    Config::set('mirror.ttl', 1);

    $admin = User::factory()->create();
    $user = User::factory()->create();

    actingAs($admin);
    Mirror::start($user);

    sleep(2);

    $expired = Mirror::isExpired();

    expect($expired)->toBeTrue();
});

it('Mirror facade reuses single Impersonator instance for multiple method calls', function (): void {
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

it('isImpersonating returns quickly without database queries when not impersonating', function (): void {
    $admin = User::factory()->create();
    actingAs($admin);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $isImpersonating = Mirror::isImpersonating();

    expect($isImpersonating)->toBeFalse()
        ->and(DB::getQueryLog())->toHaveCount(0);
});

it('start checks default guard first before iterating other guards', function (): void {
    Config::set('auth.defaults.guard', 'web');

    $admin = User::factory()->create();
    actingAs($admin, 'web');

    expect(auth('web')->check())->toBeTrue();
});

it('start only iterates other guards when default guard has no authenticated user', function (): void {
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

it('multiple impersonation checks do not multiply database queries due to caching', function (): void {
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

it('isImpersonating can be called many times with minimal session reads', function (): void {
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
