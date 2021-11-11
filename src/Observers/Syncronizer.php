<?php

namespace Lester\EloquentSalesForce\Observers;

class Syncronizer
{
    public function creating($model)
    {
        $model->syncWithSalesforce();
    }

    public function updating($model)
    {
        $model->syncWithSalesforce();
    }
}
