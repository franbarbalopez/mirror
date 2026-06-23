<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Mirror\ImpersonationPayload;
use Mirror\SessionImpersonationStore;

it('stores impersonation expiration state', function (): void {
    Carbon::setTestNow(Carbon::createFromTimestamp(100));

    $payload = new ImpersonationPayload(
        impersonatorId: 1,
        impersonatorGuard: 'web',
        impersonatedId: 2,
        impersonatedGuard: 'web',
        startedAt: 90,
    );

    $store = app(SessionImpersonationStore::class);

    expect($store->expired(9))->toBeFalse();

    $store->put($payload);

    expect($store->expired(null))->toBeFalse()
        ->and($store->expired(10))->toBeFalse()
        ->and($store->expired(9))->toBeTrue();
});
