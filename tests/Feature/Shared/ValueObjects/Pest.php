<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| ValueObject Unit Tests Configuration
|--------------------------------------------------------------------------
|
| ValueObject tests are unit tests that don't require database access
| or application refresh. They should use TestCase but NOT RefreshDatabase
| to avoid memory exhaustion when running multiple tests.
|
*/

uses(TestCase::class)
    ->beforeEach(function () {
        $this->withoutMiddleware(PreventRequestForgery::class);
    });
