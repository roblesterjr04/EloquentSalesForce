<?php

namespace Lester\EloquentSalesForce\Tests;

use Orchestra\Testbench\TestCase;
use Lester\EloquentSalesForce\Facades\SalesForce;
use Lester\EloquentSalesForce\TestLead;

class ModelTest extends TestCase
{
    use CreatesApplication;

    public function test_that_model_gets_authenticated()
    {
        SalesForce::fake();

        $lead = TestLead::find('testsfid');

        SalesForce::assertAuthenticated();
    }

    public function test_that_model_can_be_inserted()
    {
        SalesForce::fake();

        $lead = TestLead::create([
            'Email' => fake()->safeEmail(),
            'Phone' => '1231231234',
            'Company' => 'Test Company',
        ]);

        SalesForce::assertModelCreated('Lead', $lead->toArray());
    }
}
