<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Mirror\Data\ImpersonationPayload;

it('determines whether it has expired', function (): void {
    Carbon::setTestNow(Carbon::createFromTimestamp(100));

    $payload = new ImpersonationPayload(
        impersonatorId: 1,
        impersonatorGuard: 'web',
        impersonatedId: 2,
        impersonatedGuard: 'web',
        startedAt: 90,
    );

    expect($payload->expired(null))->toBeFalse()
        ->and($payload->expired(10))->toBeFalse()
        ->and($payload->expired(9))->toBeTrue();
});
