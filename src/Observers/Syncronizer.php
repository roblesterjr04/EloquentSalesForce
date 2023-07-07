<?php

namespace Lester\EloquentSalesForce\Observers;

class Syncronizer
{
    public function creating($model)
    {
        $model->syncWithSalesforce();
        if ($model->shouldSync == true) {
            return 'sync';
        }
    }

    public function updating($model)
    {
        $model->syncWithSalesforce();
        if ($model->shouldSync == true) {
            return 'sync';
        }
    }
}
