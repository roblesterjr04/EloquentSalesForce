<?php

namespace Lester\EloquentSalesForce;

class TestModel extends Model
{
	protected $table = 'Lead';
	
    public function tasks()
    {
	    return $this->hasMany('Task', 'WhoID');
    }
}