<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Most tests post short sample articles; WritingCheckTest turns this rule back on.
        config(['admin.require_job_notes' => false]);
    }
}
