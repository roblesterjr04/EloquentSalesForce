<?php

namespace Lester\EloquentSalesForce\Tests;

use Orchestra\Testbench\TestCase;
use Lester\EloquentSalesForce\Facades\SalesForce;
use Lester\EloquentSalesForce\Tests\Fixtures\TestLead;

class ModelTest extends TestCase
{
    use CreatesApplication;

    public function test_that_model_gets_authenticated()
    {
        SalesForce::fake();

        $lead = TestLead::get();

        SalesForce::assertAuthenticated();
    }

    public function test_that_model_can_be_found()
    {
        SalesForce::fake();

        $lead = TestLead::find('SalesForceIdString');

        $this->assertNotNull($lead);
    }

    public function test_that_model_can_be_inserted()
    {
        SalesForce::fake();

        $lead = TestLead::create([
            'Email' => fake()->safeEmail(),
            'Phone' => fake()->e164PhoneNumber(),
            'Company' => 'Test Company',
        ]);

        SalesForce::assertModelCreated('Lead', $lead->toArray());
    }

    public function test_that_model_can_be_updated()
    {
        SalesForce::fake();

        $lead = TestLead::create([
            'Email' => fake()->safeEmail(),
            'Phone' => fake()->e164PhoneNumber(),
            'Company' => 'Test Company',
        ]);

        $lead->Phone = null;

        $lead->save();

        SalesForce::assertModelUpdated('Lead', $lead->toArray());
    }
}
