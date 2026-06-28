<?php

declare(strict_types=1);

use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mirror\Tests\TestSupport\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);

function recallerGuard(string $recallerName, ?string $recaller): SessionGuard
{
    $guard = Mockery::mock(SessionGuard::class);

    $guard->shouldReceive('getRequest')
        ->andReturn(Request::create('/', 'GET', cookies: array_filter([
            $recallerName => $recaller,
        ])));

    $guard->shouldReceive('getRecallerName')
        ->andReturn($recallerName);

    return $guard;
}
