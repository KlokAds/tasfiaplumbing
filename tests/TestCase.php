<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Admin pages run from the billing app open only in a session it signed in; tests start as if it had.
        $this->withSession([\App\Http\Middleware\FromBilling::KEY => time() + 86400]);
    }
}
