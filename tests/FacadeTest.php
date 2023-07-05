<?php

namespace Lester\EloquentSalesForce\Tests;

use Orchestra\Testbench\TestCase;
use Lester\EloquentSalesForce\Facades\SalesForce;
use Lester\EloquentSalesForce\Tests\Fixtures\Lead;

class FacadeTest extends TestCase
{
    use CreatesApplication;

    public function test_that_facade_can_mock_requests()
    {
        SalesForce::fake();

        Lead::create([]);

        SalesForce::assertHistoryAltered();

    }

}
