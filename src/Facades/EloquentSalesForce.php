<?php

namespace RobLesterJr04\EloquentSalesForce\Facades;

use Illuminate\Support\Facades\Facade;

class EloquentSalesForce extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'eloquent-sales-force';
    }
}
