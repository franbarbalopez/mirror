<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

use Throwable;

/**
 * Thrown when a new impersonation cannot be safely started.
 */
interface CannotStartImpersonation extends Throwable {}
