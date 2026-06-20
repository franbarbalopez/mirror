<?php

declare(strict_types=1);

namespace Mirror\Contracts;

use Mirror\Data\ImpersonationPayload;
use Mirror\Exceptions\TamperedImpersonationState;

interface ImpersonationStore
{
    public function put(ImpersonationPayload $payload): void;

    /**
     * @throws TamperedImpersonationState
     */
    public function get(): ?ImpersonationPayload;

    public function forget(): void;

    public function active(): bool;
}
