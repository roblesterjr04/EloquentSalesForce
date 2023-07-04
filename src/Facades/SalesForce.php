<?php

namespace Lester\EloquentSalesForce\Facades;

use Illuminate\Support\Facades\Facade;
use Lester\EloquentSalesForce\Fakers\FakeFacade;

class SalesForce extends Facade
{
    public static function fake($jobsToFake = [])
    {
        static::swap($fake = new FakeFacade(static::getFacadeRoot()));
        return $fake;
    }

    protected static function getFacadeAccessor()
    {
        return 'salesforce';
    }
}
