<?php

namespace Lester\EloquentSalesForce\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Lester\EloquentSalesForce\Traits\InteractsWithSalesforce;
use Lester\EloquentSalesForce\Traits\SyncsWithSalesforce;

class SyncedLead extends Model
{
    use InteractsWithSalesforce, SyncsWithSalesforce;

	protected $salesForceObject = 'Lead';

    public $columns = [
        'Custom_Text_Field__c',
        'Email',
        'FirstName',
        'LastName',
        'Company',
        'Custom_Date_Field__c',
        'Id',
        'CreatedDate',
        'LastModifiedDate',
        'IsDeleted',
    ];

    protected $dates = [
        'Custom_Date_Field__c',
        'CreatedDate',
        'LastModifiedDate',
    ];

    protected $shortDates = [
        'Custom_Date_Field__c'
    ];

    public function tasks()
	{
		return $this->hasMany(TestTask::class, 'WhoId');
	}

}
