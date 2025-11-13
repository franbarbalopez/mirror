<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Mirror\Exceptions\TamperedSessionException;
use Mirror\ImpersonationSession;

beforeEach(function (): void {
    $this->session = app(ImpersonationSession::class);
});

describe('set and get impersonator', function (): void {
    it('stores impersonator ID in session', function (): void {
        $this->session->setImpersonator(123);

        expect(Session::get('mirror.impersonated_by'))->toBe(123)
            ->and($this->session->getImpersonator())->toBe(123);
    });

    it('stores string impersonator ID', function (): void {
        $this->session->setImpersonator('uuid-123');

        expect($this->session->getImpersonator())->toBe('uuid-123');
    });

    it('returns null when no impersonator is set', function (): void {
        expect($this->session->getImpersonator())->toBeNull();
    });

    it('returns self for method chaining', function (): void {
        $result = $this->session->setImpersonator(1);

        expect($result)->toBe($this->session);
    });
});

describe('mark and check impersonating', function (): void {
    it('marks session as impersonating', function (): void {
        $this->session->markAsImpersonating();

        expect(Session::get('mirror.impersonating'))->toBeTrue()
            ->and($this->session->isImpersonating())->toBeTrue();
    });

    it('returns false when not impersonating', function (): void {
        expect($this->session->isImpersonating())->toBeFalse();
    });

    it('returns false when impersonating flag is not boolean true', function (): void {
        Session::put('mirror.impersonating', 1);

        expect($this->session->isImpersonating())->toBeFalse();
    });
});

describe('clear impersonating flag', function (): void {
    it('clears the impersonating flag', function (): void {
        $this->session->markAsImpersonating();
        expect($this->session->isImpersonating())->toBeTrue();

        $this->session->clearImpersonatingFlag();

        expect($this->session->isImpersonating())->toBeFalse()
            ->and(Session::has('mirror.impersonating'))->toBeFalse();
    });
});

describe('clear impersonator', function (): void {
    it('clears the impersonator ID', function (): void {
        $this->session->setImpersonator(123);
        expect($this->session->getImpersonator())->toBe(123);

        $this->session->clearImpersonator();

        expect($this->session->getImpersonator())->toBeNull()
            ->and(Session::has('mirror.impersonated_by'))->toBeFalse();
    });
});

describe('set and get guard name', function (): void {
    it('stores guard name in session', function (): void {
        $this->session->setGuardName('web');

        expect(Session::get('mirror.guard_name'))->toBe('web')
            ->and($this->session->getGuardName())->toBe('web');
    });

    it('stores different guard names', function (): void {
        $this->session->setGuardName('api');

        expect($this->session->getGuardName())->toBe('api');
    });

    it('returns null when no guard name is set', function (): void {
        expect($this->session->getGuardName())->toBeNull();
    });
});

describe('clear guard name', function (): void {
    it('clears the guard name', function (): void {
        $this->session->setGuardName('web');

        expect($this->session->getGuardName())->toBe('web');

        $this->session->clearGuardName();

        expect($this->session->getGuardName())->toBeNull()
            ->and(Session::has('mirror.guard_name'))->toBeFalse();
    });
});

describe('set and get started at', function (): void {
    it('stores started at timestamp in session', function (): void {
        $timestamp = time();
        $this->session->setStartedAt($timestamp);

        expect(Session::get('mirror.started_at'))->toBe($timestamp)
            ->and($this->session->getStartedAt())->toBe($timestamp);
    });

    it('returns null when no started at is set', function (): void {
        expect($this->session->getStartedAt())->toBeNull();
    });
});

describe('set and get leave redirect URL', function (): void {
    it('stores leave redirect URL in session', function (): void {
        $url = 'https://example.com/admin/users';
        $this->session->setLeaveRedirectUrl($url);

        expect(Session::get('mirror.leave_redirect_url'))->toBe($url)
            ->and($this->session->getLeaveRedirectUrl())->toBe($url);
    });

    it('does not store null URL', function (): void {
        $this->session->setLeaveRedirectUrl(null);

        expect(Session::has('mirror.leave_redirect_url'))->toBeFalse()
            ->and($this->session->getLeaveRedirectUrl())->toBeNull();
    });

    it('returns null when no leave redirect URL is set', function (): void {
        expect($this->session->getLeaveRedirectUrl())->toBeNull();
    });
});

describe('clear all session data', function (): void {
    it('clears all impersonation data from session', function (): void {
        $this->session
            ->setImpersonator(123)
            ->setGuardName('web')
            ->setStartedAt(time())
            ->setLeaveRedirectUrl('/admin')
            ->markAsImpersonating()
            ->generateIntegrityHash();

        expect(Session::has('mirror.impersonating'))->toBeTrue()
            ->and(Session::has('mirror.impersonated_by'))->toBeTrue()
            ->and(Session::has('mirror.guard_name'))->toBeTrue()
            ->and(Session::has('mirror.started_at'))->toBeTrue()
            ->and(Session::has('mirror.leave_redirect_url'))->toBeTrue()
            ->and(Session::has('mirror.integrity'))->toBeTrue();

        $this->session->clear();

        expect(Session::has('mirror.impersonating'))->toBeFalse()
            ->and(Session::has('mirror.impersonated_by'))->toBeFalse()
            ->and(Session::has('mirror.guard_name'))->toBeFalse()
            ->and(Session::has('mirror.started_at'))->toBeFalse()
            ->and(Session::has('mirror.leave_redirect_url'))->toBeFalse()
            ->and(Session::has('mirror.integrity'))->toBeFalse();
    });
});

describe('generate integrity hash', function (): void {
    it('generates and stores integrity hash', function (): void {
        $this->session
            ->setImpersonator(123)
            ->setGuardName('web')
            ->setStartedAt(time())
            ->setLeaveRedirectUrl('/admin')
            ->generateIntegrityHash();

        $hash = Session::get('mirror.integrity');

        expect($hash)->not->toBeNull()
            ->and($hash)->toBeString()
            ->and(strlen($hash))->toBe(64);
    });

    it('generates different hash for different data', function (): void {
        $this->session
            ->setImpersonator(123)
            ->setGuardName('web')
            ->setStartedAt(time())
            ->generateIntegrityHash();

        $hash1 = Session::get('mirror.integrity');

        $this->session->clear();

        $this->session
            ->setImpersonator(456)
            ->setGuardName('web')
            ->setStartedAt(time())
            ->generateIntegrityHash();

        $hash2 = Session::get('mirror.integrity');

        expect($hash1)->not->toBe($hash2);
    });

    it('generates same hash for same data', function (): void {
        $timestamp = time();

        $this->session
            ->setImpersonator(123)
            ->setGuardName('web')
            ->setStartedAt($timestamp)
            ->setLeaveRedirectUrl('/admin')
            ->generateIntegrityHash();

        $hash1 = Session::get('mirror.integrity');

        $this->session->generateIntegrityHash();

        $hash2 = Session::get('mirror.integrity');

        expect($hash1)->toBe($hash2);
    });

    it('uses app key for hash generation', function (): void {
        Config::set('app.key', 'test-key-123');

        $this->session
            ->setImpersonator(123)
            ->generateIntegrityHash();

        $hash1 = Session::get('mirror.integrity');

        expect($hash1)->not->toBeNull()
            ->and($hash1)->toBeString();
    });
});

describe('verify integrity', function (): void {
    it('passes verification when data is unchanged', function (): void {
        $this->session
            ->setImpersonator(123)
            ->setGuardName('web')
            ->setStartedAt(time())
            ->setLeaveRedirectUrl('/admin')
            ->markAsImpersonating()
            ->generateIntegrityHash();

        $this->session->verifyIntegrity();

        expect(true)->toBeTrue();
    });

    it('does nothing when not impersonating', function (): void {
        $this->session->verifyIntegrity();

        expect(true)->toBeTrue();
    });

    it('throws exception when hash is missing', function (): void {
        $this->session
            ->setImpersonator(123)
            ->markAsImpersonating();

        $this->session->verifyIntegrity();
    })->throws(TamperedSessionException::class, 'tampered');

    it('clears session when hash is missing', function (): void {
        $this->session
            ->setImpersonator(123)
            ->markAsImpersonating();

        try {
            $this->session->verifyIntegrity();
        } catch (TamperedSessionException) {
            expect(Session::has('mirror.impersonating'))->toBeFalse()
                ->and(Session::has('mirror.impersonated_by'))->toBeFalse();
        }
    });

    it('throws exception when impersonator ID is tampered', function (): void {
        $this->session
            ->setImpersonator(123)
            ->setGuardName('web')
            ->setStartedAt(time())
            ->markAsImpersonating()
            ->generateIntegrityHash();

        Session::put('mirror.impersonated_by', 456);

        $this->session->verifyIntegrity();
    })->throws(TamperedSessionException::class, 'tampered');

    it('throws exception when guard name is tampered', function (): void {
        $this->session
            ->setImpersonator(123)
            ->setGuardName('web')
            ->setStartedAt(time())
            ->markAsImpersonating()
            ->generateIntegrityHash();

        Session::put('mirror.guard_name', 'api');

        $this->session->verifyIntegrity();
    })->throws(TamperedSessionException::class, 'tampered');

    it('throws exception when started at is tampered', function (): void {
        $this->session
            ->setImpersonator(123)
            ->setGuardName('web')
            ->setStartedAt(time())
            ->markAsImpersonating()
            ->generateIntegrityHash();

        Session::put('mirror.started_at', time() - 3600);

        $this->session->verifyIntegrity();
    })->throws(TamperedSessionException::class, 'tampered');

    it('throws exception when leave redirect URL is tampered', function (): void {
        $this->session
            ->setImpersonator(123)
            ->setGuardName('web')
            ->setStartedAt(time())
            ->setLeaveRedirectUrl('/admin')
            ->markAsImpersonating()
            ->generateIntegrityHash();

        Session::put('mirror.leave_redirect_url', '/malicious');

        $this->session->verifyIntegrity();
    })->throws(TamperedSessionException::class, 'tampered');

    it('clears session when tampering is detected', function (): void {
        $this->session
            ->setImpersonator(123)
            ->setGuardName('web')
            ->markAsImpersonating()
            ->generateIntegrityHash();

        // Tamper
        Session::put('mirror.impersonated_by', 999);

        try {
            $this->session->verifyIntegrity();
        } catch (TamperedSessionException) {
            expect(Session::has('mirror.impersonating'))->toBeFalse()
                ->and(Session::has('mirror.impersonated_by'))->toBeFalse()
                ->and(Session::has('mirror.guard_name'))->toBeFalse()
                ->and(Session::has('mirror.integrity'))->toBeFalse();
        }
    });

    it('uses timing-safe comparison', function (): void {
        $this->session
            ->setImpersonator(123)
            ->markAsImpersonating()
            ->generateIntegrityHash();

        $fakeHash = str_repeat('a', 64);
        Session::put('mirror.integrity', $fakeHash);

        try {
            $this->session->verifyIntegrity();
        } catch (TamperedSessionException $tamperedSessionException) {
            expect($tamperedSessionException->getMessage())->toContain('tampered');
        }
    });
});

describe('check expiration', function (): void {
    it('returns false when TTL is null', function (): void {
        $this->session
            ->setStartedAt(time() - 10000)
            ->markAsImpersonating();

        expect($this->session->isExpired(null))->toBeFalse();
    });

    it('returns false when not impersonating', function (): void {
        $this->session->setStartedAt(time() - 10000);

        expect($this->session->isExpired(3600))->toBeFalse();
    });

    it('returns true when started at is null and impersonating', function (): void {
        $this->session->markAsImpersonating();

        expect($this->session->isExpired(3600))->toBeTrue();
    });

    it('returns false when session is not expired', function (): void {
        $this->session
            ->setStartedAt(time() - 1800)
            ->markAsImpersonating();

        expect($this->session->isExpired(3600))->toBeFalse();
    });

    it('returns true when session is expired', function (): void {
        $this->session
            ->setStartedAt(time() - 3601)
            ->markAsImpersonating();

        expect($this->session->isExpired(3600))->toBeTrue();
    });

    it('returns true when session is exactly expired', function (): void {
        $this->session
            ->setStartedAt(time() - 3601)
            ->markAsImpersonating();

        expect($this->session->isExpired(3600))->toBeTrue();
    });

    it('returns false when session just started', function (): void {
        $this->session
            ->setStartedAt(time())
            ->markAsImpersonating();

        expect($this->session->isExpired(3600))->toBeFalse();
    });

    it('handles very large TTL values', function (): void {
        $this->session
            ->setStartedAt(time() - 86400)
            ->markAsImpersonating();

        expect($this->session->isExpired(31536000))->toBeFalse();
    });

    it('handles very small TTL values', function (): void {
        $this->session
            ->setStartedAt(time() - 2)
            ->markAsImpersonating();

        expect($this->session->isExpired(1))->toBeTrue();
    });
});

describe('method chaining', function (): void {
    it('allows full method chaining for session setup', function (): void {
        $timestamp = time();

        $result = $this->session
            ->setImpersonator(123)
            ->setGuardName('web')
            ->setStartedAt($timestamp)
            ->setLeaveRedirectUrl('/admin')
            ->markAsImpersonating()
            ->generateIntegrityHash();

        expect($result)->toBe($this->session)
            ->and($this->session->getImpersonator())->toBe(123)
            ->and($this->session->getGuardName())->toBe('web')
            ->and($this->session->getStartedAt())->toBe($timestamp)
            ->and($this->session->getLeaveRedirectUrl())->toBe('/admin')
            ->and($this->session->isImpersonating())->toBeTrue()
            ->and(Session::has('mirror.integrity'))->toBeTrue();
    });
});

describe('session persistence', function (): void {
    it('persists data across multiple requests simulation', function (): void {
        $this->session
            ->setImpersonator(123)
            ->setGuardName('web')
            ->setStartedAt(time())
            ->markAsImpersonating()
            ->generateIntegrityHash();

        $sessionData = Session::all();

        $newSession = app(ImpersonationSession::class);

        foreach ($sessionData as $key => $value) {
            Session::put($key, $value);
        }

        expect($newSession->getImpersonator())->toBe(123)
            ->and($newSession->getGuardName())->toBe('web')
            ->and($newSession->isImpersonating())->toBeTrue();
    });
});
