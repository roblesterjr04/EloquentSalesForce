<?php

namespace Lester\EloquentSalesForce\Tests\Fixtures;

class TestTask extends Model
{
	protected $table = 'Task';

    public $columns = [
        'WhoId',
        'Subject',
    ];

	public function lead()
	{
		return $this->belongsTo(TestLead::class, 'WhoId');
	}
}
