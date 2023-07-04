<?php

namespace Lester\EloquentSalesForce\Tests;

use Lester\EloquentSalesForce\ServiceProvider;
use Lester\EloquentSalesForce\SalesForceObject;
use Lester\EloquentSalesForce\TestLead;
use Lester\EloquentSalesForce\TestModel;
use Lester\EloquentSalesForce\TestTask;
use Lester\EloquentSalesForce\TestUser;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Config;
use Lester\EloquentSalesForce\Facades\SalesForce;
use Lester\EloquentSalesForce\Database\SOQLBatch;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Error\Notice;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\WithFaker;
use Forrest;
use GuzzleHttp\Client;
use Carbon\Carbon;
use Lester\EloquentSalesForce\Exceptions\MalformedQueryException;
use Lester\EloquentSalesForce\Exceptions\RestAPIException;
//use Illuminate\Foundation\Testing\TestCase;

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
