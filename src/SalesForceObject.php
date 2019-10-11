<?php

namespace Lester\EloquentSalesForce;

class SalesForceObject extends Model
{
    public function __construct(Array $attributes)
    {
        $this->table = $attributes['attributes']['type'];
        parent::__construct($attributes);
    }
}
