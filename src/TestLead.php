<?php

namespace Lester\EloquentSalesForce;

class TestLead extends Model
{
	protected $table = 'Lead';

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

    public static function booted()
    {
        static::creating(function ($lead) {
            $lead->Phone = '1231231234';
        });
    }
}
