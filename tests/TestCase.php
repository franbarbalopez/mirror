<?php

declare(strict_types=1);

namespace Mirror\Tests;

use Orchestra\Testbench\Attributes\WithMigration;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as BaseTestCase;

#[WithMigration]
abstract class TestCase extends BaseTestCase
{
    use WithWorkbench;
}
