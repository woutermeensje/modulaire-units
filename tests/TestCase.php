<?php

namespace Tests;

use App\Http\Middleware\EnsureTrailingSlash;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Laravel's test client strips trailing slashes from URLs, so the slash redirect is tested separately.
        $this->withoutMiddleware(EnsureTrailingSlash::class);
    }
}
