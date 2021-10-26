<?php

namespace Lester\EloquentSalesForce;

class TestLead extends Model
{
	protected $table = 'Lead';

    protected $dates = [
        'Custom_Date_Field__c',
        'CreatedDate',
        'LastModifiedDate',
    ];

    protected $dateFormats = [
        'Custom_Date_Field__c' => 'toDateString'
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
