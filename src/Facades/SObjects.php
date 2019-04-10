<?php

namespace Lester\EloquentSalesForce\Facades;

use Illuminate\Support\Facades\Facade;

class SObjects extends Facade
{
	protected static function getFacadeAccessor()
	{
		return 'sobjects';
	}
}
