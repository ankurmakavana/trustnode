<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // TenantContext is static, so it survives between tests in one PHP process. Reset it around every
    // test so one test's tenant cannot leak into another.
    protected function setUp(): void
    {
        parent::setUp();

        \App\Services\TenantContext::clear();
    }

    protected function tearDown(): void
    {
        \App\Services\TenantContext::clear();

        parent::tearDown();
    }
}
