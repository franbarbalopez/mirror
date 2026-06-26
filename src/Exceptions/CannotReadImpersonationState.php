<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

use Throwable;

/**
 * Thrown when stored impersonation state cannot be safely read.
 */
interface CannotReadImpersonationState extends Throwable {}
