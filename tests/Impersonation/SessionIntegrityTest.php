<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Session;
use Mirror\Exceptions\InvalidImpersonationSignature;
use Mirror\Exceptions\MissingImpersonationSignature;
use Mirror\Facades\Mirror;
use Mirror\ImpersonationHasher;
use Mirror\ImpersonationPayload;
use Mirror\SessionImpersonationStore;

use function Pest\Laravel\actingAs;

it('supports custom session keys', function (): void {
    config()->set('mirror.session.key', 'custom.impersonation');

    actingAs(User::factory()->create());

    Mirror::impersonate(User::factory()->create());

    expect(Session::has('custom.impersonation.payload'))->toBeTrue()
        ->and(Session::has('custom.impersonation.signature'))->toBeTrue();
});

it('stores signed impersonation context', function (): void {
    $payload = new ImpersonationPayload(
        impersonatorId: 1,
        impersonatorGuard: 'web',
        impersonatedId: 2,
        impersonatedGuard: 'web',
        startedAt: 90,
        context: ['reason' => 'support'],
    );

    $store = app(SessionImpersonationStore::class);
    $store->put($payload);

    expect($store->get()?->context)->toBe(['reason' => 'support']);
});

it('hydrates signed session payloads without custom context', function (): void {
    $payload = new ImpersonationPayload(
        impersonatorId: 1,
        impersonatorGuard: 'web',
        impersonatedId: 2,
        impersonatedGuard: 'web',
        startedAt: 90,
    );

    Session::put('mirror.impersonation.payload', [
        'impersonator_id' => 1,
        'impersonator_guard' => 'web',
        'impersonated_id' => 2,
        'impersonated_guard' => 'web',
        'started_at' => 90,
    ]);
    Session::put('mirror.impersonation.signature', app(ImpersonationHasher::class)->sign($payload));

    expect(app(SessionImpersonationStore::class)->get()?->context)->toBe([]);
});

it('detects tampered payloads', function (): void {
    actingAs(User::factory()->create());

    Mirror::impersonate(User::factory()->create());

    $payload = Session::get('mirror.impersonation.payload');
    $payload['impersonator_id'] = 999;
    Session::put('mirror.impersonation.payload', $payload);

    Mirror::context();
})->throws(InvalidImpersonationSignature::class);

it('detects a missing signature', function (): void {
    actingAs(User::factory()->create());

    Mirror::impersonate(User::factory()->create());

    Session::forget('mirror.impersonation.signature');

    Mirror::context();
})->throws(MissingImpersonationSignature::class);

it('detects a missing signature when checking active state', function (): void {
    actingAs(User::factory()->create());

    Mirror::impersonate(User::factory()->create());
    Session::forget('mirror.impersonation.signature');

    Mirror::active();
})->throws(MissingImpersonationSignature::class);

it('detects tampered payloads when checking expiration', function (): void {
    config()->set('mirror.ttl', 60);

    actingAs(User::factory()->create());

    Mirror::impersonate(User::factory()->create());

    $payload = Session::get('mirror.impersonation.payload');
    $payload['started_at'] = 1;
    Session::put('mirror.impersonation.payload', $payload);

    Mirror::expired();
})->throws(InvalidImpersonationSignature::class);

it('clears session state when the signature is missing', function (): void {
    actingAs(User::factory()->create());

    Mirror::impersonate(User::factory()->create());
    Session::forget('mirror.impersonation.signature');

    try {
        Mirror::active();
    } catch (MissingImpersonationSignature) {
        expect(Session::has('mirror.impersonation.payload'))->toBeFalse()
            ->and(Session::has('mirror.impersonation.signature'))->toBeFalse();

        return;
    }

    $this->fail('Expected missing impersonation signature.');
});
