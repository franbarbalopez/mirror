<?php

declare(strict_types=1);

use Mirror\ImpersonationPayload;
use Mirror\SessionImpersonationStore;

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
