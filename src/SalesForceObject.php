<?php

namespace Lester\EloquentSalesForce;

class SalesForceObject extends Model
{
    public function __construct(Array $attributes)
    {
        if (!isset($attributes['attributes'])) {
            parent::__construct($attributes);
        } else {
            $this->setTable($attributes['attributes']['type']);
            parent::__construct($attributes);
        }
    }

    public function setTable($tableName)
    {
        $this->table = $tableName;
        return $this;
    }

}
