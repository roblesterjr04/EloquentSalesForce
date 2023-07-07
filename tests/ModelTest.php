<?php

namespace Lester\EloquentSalesForce\Tests;

use Orchestra\Testbench\TestCase;
use Lester\EloquentSalesForce\Facades\SalesForce;
use Lester\EloquentSalesForce\Tests\Fixtures\Lead;
use Lester\EloquentSalesForce\Tests\Fixtures\SyncedLead;
use Lester\EloquentSalesForce\Tests\Fixtures\TestModel;

class ModelTest extends TestCase
{
    use CreatesApplication;

    public function test_that_model_gets_authenticated()
    {
        SalesForce::fake();

        $lead = Lead::get();

        SalesForce::assertAuthenticated();
    }

    public function test_that_model_can_be_found()
    {
        SalesForce::fake();

        $lead = Lead::find('SalesForceIdString');

        $this->assertNotNull($lead);
    }

    public function test_that_model_can_be_inserted()
    {
        SalesForce::fake();

        $lead = Lead::create([
            'Email' => fake()->safeEmail(),
            'Phone' => fake()->e164PhoneNumber(),
            'Company' => 'Test Company',
        ]);

        SalesForce::assertModelCreated('Lead', $lead->toArray());
    }

    public function test_that_model_can_be_updated()
    {
        SalesForce::fake();

        $lead = Lead::create([
            'Email' => fake()->safeEmail(),
            'Phone' => fake()->e164PhoneNumber(),
            'Company' => 'Test Company',
        ]);

        $lead->Phone = null;
        $lead->save();

        SalesForce::assertModelUpdated('Lead', $lead->toArray());
    }

    public function test_that_model_can_be_deleted()
    {
        SalesForce::fake();

        $lead = Lead::create([
            'Email' => fake()->safeEmail(),
            'Phone' => fake()->e164PhoneNumber(),
            'Company' => 'Test Company',
        ]);

        $lead->delete();

        SalesForce::assertModelDeleted('Lead', $lead->toArray());
    }

    public function test_that_model_can_use_sync_trait()
    {
        SalesForce::fake();

        /*TestModel::create([
            'email' => fake()->safeEmail(),
            'name' => fake()->name(),
            'company' => fake()->company(),
        ]);*/

        $email = fake()->safeEmail();
        $model = new SyncedLead();
        $model->email = $email;
        $model->name = fake()->name();
        $model->company = fake()->company();
        $model->save();

        $this->assertTrue($model->syncWith() !== null);
        $this->assertEquals($email, $model->refresh()->email);

        Salesforce::assertModelCreated("Lead", $model->syncWith()->toArray());

    }
}
