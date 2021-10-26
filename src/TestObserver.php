<?php

namespace Lester\EloquentSalesForce;

class TestObserver
{
    public function creating(TestLead $lead)
    {
        $lead->Company = 'Test Company';
    }
}
