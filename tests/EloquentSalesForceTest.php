<?php

namespace Lester\EloquentSalesForce\Tests;

use Lester\EloquentSalesForce\ServiceProvider;
use Lester\EloquentSalesForce\TestModel;
use Orchestra\Testbench\TestCase;

class EloquentSalesForceTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    public function testObject()
    {
	    
	    $lead = TestModel::first();
	    
        $this->assertEquals(1, 1);
    }
}
