<?php

namespace Lester\EloquentSalesForce\Tests;

use Lester\EloquentSalesForce\ServiceProvider;
use Lester\EloquentSalesForce\TestLead;
use Lester\EloquentSalesForce\TestTask;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Config;
use Lester\EloquentSalesForce\Facades\SObjects;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use \Mockery;

class MockedObjectTest extends TestCase
{

    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    public function test_example()
    {
        SObjects::fake();

        $leads = collect([
            new TestLead()
        ]);

        $update = SObjects::update($leads);

        SObjects::assertMassUpdate($leads);
    }



}
