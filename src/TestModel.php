<?php

namespace Lester\EloquentSalesForce;

use Illuminate\Database\Eloquent\Model;
use Lester\EloquentSalesForce\Traits\SyncsWithSalesforce;

class TestModel extends Model
{
    use SyncsWithSalesforce;

    protected $salesForceObject = 'Lead';

    protected $fillable = [
        'email',
        'firstName',
        'lastName',
        'company'
    ];

    protected $salesForceFieldMap = [
        'email' => 'Email',
        'firstName' => 'FirstName',
        'lastName' => 'LastName',
        'company' => 'Company',
    ];
}
