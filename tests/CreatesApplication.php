<?php

namespace Lester\EloquentSalesForce\Tests;

use Lester\EloquentSalesForce\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

trait CreatesApplication
{
    public function createApplication()
    {
        if (getenv('GIT_TEST')) return parent::createApplication();

        if (file_exists(__DIR__.'/../.env')) {
            $env = file_get_contents(__DIR__.'/../.env');
        } else {
            $env = "";

            //$ex = new \Exception("Local testing requires a .env file!");
            //throw $ex;
        }



        $lines = explode("\n", $env);

        foreach ($lines as $line) {
            if ($line) putenv(trim($line));
        }

        return parent::createApplication();

    }

    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_models', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('email')->unique();
            $table->string('salesforce')->nullable();
            $table->string('name');
            $table->string('company');
            $table->timestamps();
        });
    }

}
