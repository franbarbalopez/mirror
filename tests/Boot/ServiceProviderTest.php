<?php

declare(strict_types=1);

use Mirror\BladeDirectivesRegistrar;

it('does not resolve the session store while booting', function (): void {
    expect(app()->resolved('session.store'))->toBeFalse();
});

it('registers the blade directives without resolving the session store', function (): void {
    app(BladeDirectivesRegistrar::class)->register();

    expect(app()->resolved('session.store'))->toBeFalse();
});
