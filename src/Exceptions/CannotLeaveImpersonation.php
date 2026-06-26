<?php

declare(strict_types=1);

namespace Mirror\Exceptions;

use Throwable;

/**
 * Thrown when an active impersonation cannot be safely stopped.
 */
interface CannotLeaveImpersonation extends Throwable {}
