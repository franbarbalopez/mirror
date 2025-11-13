<?php

use Carbon\Carbon;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use Mirror\Concerns\Impersonatable;
use Mirror\Events\ImpersonationStarted;
use Mirror\Events\ImpersonationStopped;
use Mirror\Exceptions\ImpersonationException;
use Mirror\Exceptions\TamperedSessionException;
use Mirror\Facades\Mirror;
use Workbench\App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Config::set('mirror.enabled', true);
    Config::set('mirror.ttl');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('start impersonation', function (): void {
    it('starts impersonation successfully with real users', function (): void {
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

    it('starts impersonation with custom redirect URLs', function (): void {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        actingAs($admin);

        $redirectUrl = Mirror::start($targetUser, '/admin/users', '/dashboard');

        expect($redirectUrl)->toBe('/dashboard')
            ->and(Mirror::getLeaveRedirectUrl())->toBe('/admin/users')
            ->and(Auth::id())->toBe($targetUser->id);
    });

    it('throws exception when impersonation is disabled', function (): void {
        Config::set('mirror.enabled', false);

        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        actingAs($admin);

        Mirror::start($targetUser);
    })->throws(ImpersonationException::class, 'Impersonation is not enabled');

    it('throws exception when already impersonating', function (): void {
        $admin = User::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        actingAs($admin);

        Mirror::start($user1);

        Mirror::start($user2);
    })->throws(ImpersonationException::class, 'already impersonating');

    it('throws exception when user cannot impersonate', function (): void {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        $adminWithRestriction = new class extends Authenticatable
        {
            use Impersonatable;

            protected $guarded = [];

            public function canImpersonate(): bool
            {
                return false;
            }
        };

        $adminWithRestriction->forceFill(['id' => $admin->id, 'email' => $admin->email])->exists = true;

        actingAs($adminWithRestriction);

        Mirror::start($targetUser);
    })->throws(ImpersonationException::class, 'do not have permission to impersonate');

    it('throws exception when target user cannot be impersonated', function (): void {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        actingAs($admin);

        $restrictedUser = new class extends Authenticatable
        {
            use Impersonatable;

            protected $guarded = [];

            public function canBeImpersonated(): bool
            {
                return false;
            }
        };

        $restrictedUser->forceFill(['id' => $targetUser->id, 'email' => $targetUser->email])->exists = true;

        Mirror::start($restrictedUser);
    })->throws(ImpersonationException::class, 'cannot be impersonated');

    it('stores impersonation data in session', function (): void {
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
});

describe('stop impersonation', function (): void {
    it('stops impersonation successfully', function (): void {
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

    it('throws exception when not impersonating', function (): void {
        $admin = User::factory()->create();

        actingAs($admin);

        Mirror::stop();
    })->throws(ImpersonationException::class, 'not impersonating any user');

    it('throws exception when session has expired', function (): void {
        Config::set('mirror.ttl', 3600);

        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        actingAs($admin);

        Mirror::start($targetUser);

        // Travel forward in time past the TTL
        Carbon::setTestNow(Carbon::now()->addSeconds(3601));

        Mirror::stop();
    })->throws(ImpersonationException::class, 'session has expired');

    it('clears session data after stopping', function (): void {
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

    it('throws exception when session integrity is compromised', function (): void {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        actingAs($admin);

        Mirror::start($targetUser);

        Session::put('mirror.impersonated_by', 999);

        Mirror::stop();
    })->throws(TamperedSessionException::class, 'tampered');
});

describe('force stop impersonation', function (): void {
    it('force stops impersonation without checking TTL', function (): void {
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

    it('throws exception when not impersonating', function (): void {
        $admin = User::factory()->create();

        actingAs($admin);

        Mirror::forceStop();
    })->throws(ImpersonationException::class, 'not impersonating any user');
});

describe('check impersonation status', function (): void {
    it('returns true when impersonating', function (): void {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        actingAs($admin);

        Mirror::start($targetUser);

        expect(Mirror::isImpersonating())->toBeTrue()
            ->and(Mirror::impersonating())->toBeTrue();
    });

    it('returns false when not impersonating', function (): void {
        $admin = User::factory()->create();

        actingAs($admin);

        expect(Mirror::isImpersonating())->toBeFalse()
            ->and(Mirror::impersonating())->toBeFalse();
    });
});

describe('get impersonator', function (): void {
    it('returns impersonator user when impersonating', function (): void {
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

    it('returns null when not impersonating', function (): void {
        $admin = User::factory()->create();

        actingAs($admin);

        expect(Mirror::getImpersonator())->toBeNull()
            ->and(Mirror::impersonator())->toBeNull();
    });
});

describe('start impersonation by key', function (): void {
    it('starts impersonation by user ID successfully', function (): void {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        actingAs($admin);

        $redirectUrl = Mirror::startByKey($targetUser->id);

        expect($redirectUrl)->toBeNull()
            ->and(Auth::id())->toBe($targetUser->id)
            ->and(Mirror::isImpersonating())->toBeTrue();
    });

    it('throws exception when user not found by key', function (): void {
        $admin = User::factory()->create();

        actingAs($admin);

        Mirror::startByKey(99999);
    })->throws(InvalidArgumentException::class, 'User with key [99999] not found');
});

describe('start impersonation by email', function (): void {
    it('starts impersonation by email successfully', function (): void {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create(['email' => 'target@example.com']);

        actingAs($admin);

        $redirectUrl = Mirror::startByEmail('target@example.com');

        expect($redirectUrl)->toBeNull()
            ->and(Auth::id())->toBe($targetUser->id)
            ->and(Mirror::isImpersonating())->toBeTrue();
    });

    it('throws exception when user not found by email', function (): void {
        $admin = User::factory()->create();

        actingAs($admin);

        Mirror::startByEmail('notfound@example.com');
    })->throws(InvalidArgumentException::class, 'User with email [notfound@example.com] not found');
});

describe('impersonator ID', function (): void {
    it('returns impersonator ID when impersonating', function (): void {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        actingAs($admin);

        Mirror::start($targetUser);

        expect(Mirror::impersonatorId())->toBe($admin->id);
    });

    it('returns null when not impersonating', function (): void {
        $admin = User::factory()->create();

        actingAs($admin);

        expect(Mirror::impersonatorId())->toBeNull();
    });
});

describe('method aliases', function (): void {
    it('as() is an alias for start()', function (): void {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        actingAs($admin);

        $result = Mirror::as($targetUser, '/admin', '/dashboard');

        expect($result)->toBe('/dashboard')
            ->and(Auth::id())->toBe($targetUser->id)
            ->and(Mirror::isImpersonating())->toBeTrue();
    });

    it('leave() is an alias for stop()', function (): void {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        actingAs($admin);

        Mirror::start($targetUser);

        Mirror::leave();

        expect(Auth::id())->toBe($admin->id)
            ->and(Mirror::isImpersonating())->toBeFalse();
    });
});

describe('leave redirect URL', function (): void {
    it('returns leave redirect URL', function (): void {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        actingAs($admin);

        Mirror::start($targetUser, '/admin/users');

        expect(Mirror::getLeaveRedirectUrl())->toBe('/admin/users');
    });

    it('uses current URL when no leave redirect URL provided', function (): void {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        actingAs($admin);

        Mirror::start($targetUser);

        $leaveUrl = Mirror::getLeaveRedirectUrl();

        expect($leaveUrl)->not->toBeNull()
            ->and($leaveUrl)->toBeString();
    });

    it('returns null when not impersonating', function (): void {
        $admin = User::factory()->create();

        actingAs($admin);

        expect(Mirror::getLeaveRedirectUrl())->toBeNull();
    });
});

describe('multiple impersonation flow', function (): void {
    it('can impersonate multiple users in sequence', function (): void {
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
});

describe('session integrity', function (): void {
    it('generates integrity hash on start', function (): void {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        actingAs($admin);

        Mirror::start($targetUser);

        $hash = Session::get('mirror.integrity');

        expect($hash)->not->toBeNull()
            ->and($hash)->toBeString()
            ->and(strlen($hash))->toBe(64);
    });

    it('verifies integrity on stop', function (): void {
        $admin = User::factory()->create();
        $targetUser = User::factory()->create();

        actingAs($admin);

        Mirror::start($targetUser);

        Mirror::stop();

        expect(Auth::id())->toBe($admin->id);
    });
});

describe('edge cases and coverage', function (): void {
    it('throws exception when trying to impersonate without being logged in', function (): void {
        $targetUser = User::factory()->create();

        expect(auth()->check())->toBeFalse();

        Mirror::start($targetUser);
    })->throws(ImpersonationException::class, 'do not have permission to impersonate');

    it('uses default guard when no guard is authenticated', function (): void {
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
});
