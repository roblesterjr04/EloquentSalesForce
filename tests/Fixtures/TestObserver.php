<?php

namespace Lester\EloquentSalesForce\Tests\Fixtures;

class TestObserver
{
    public function creating(TestLead $lead)
    {
        $lead->Company = 'Test Company';
    }
}
