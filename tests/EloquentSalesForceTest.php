<?php

namespace RobLesterJr04\EloquentSalesForce\Tests;

use RobLesterJr04\EloquentSalesForce\Facades\EloquentSalesForce;
use RobLesterJr04\EloquentSalesForce\ServiceProvider;
use Orchestra\Testbench\TestCase;

class EloquentSalesForceTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return [
            'eloquent-sales-force' => EloquentSalesForce::class,
        ];
    }

    public function testExample()
    {
        $this->assertEquals(1, 1);
    }
}
