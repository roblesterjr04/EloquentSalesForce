<?php

namespace Lester\EloquentSalesForce\Tests;

use Orchestra\Testbench\TestCase;
use Lester\EloquentSalesForce\Facades\SalesForce;
use Lester\EloquentSalesForce\Tests\Fixtures\TestLead;

class FacadeTest extends TestCase
{
    use CreatesApplication;

    public function test_that_facade_can_mock_requests()
    {
        SalesForce::fake();

        TestLead::create([]);

        SalesForce::assertHistoryAltered();

    }

}
