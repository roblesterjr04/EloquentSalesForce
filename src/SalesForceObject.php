<?php

namespace Lester\EloquentSalesForce;

use Illuminate\Database\Eloquent\Model;
use Lester\EloquentSalesForce\Traits\InteractsWithSalesforce;

class SalesForceObject extends Model
{
    use InteractsWithSalesforce;

    public $columns = [
        'Id',
    ];

    public function __construct(Array $attributes = [])
    {
        if (isset($attributes['Id'])) {
            $this->exists = true;
        }
        if (!isset($attributes['attributes'])) {
            parent::__construct($attributes);
        } else {
            $this->setTable($attributes['attributes']['type']);
            parent::__construct($attributes);
        }
    }

    public function setTable($tableName)
    {
        $this->attributes['attributes'] = [
            'type' => $tableName,
        ];
        return parent::setTable($tableName);
    }

}
