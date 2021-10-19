<?php

namespace Lester\EloquentSalesForce\Traits;

use Lester\EloquentSalesForce\Facades\SObjects;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Lester\EloquentSalesForce\SalesForceObject;

trait SyncsWithSalesforce
{

    public static function booted()
    {
        static::creating(function ($model) {
            $model->syncWithSalesforce();
        });

        static::updating(function ($model) {
            $model->syncWithSalesforce();
        });
    }

    private function syncWithSalesforce()
    {
        $sfModel = new SalesForceObject([
            'attributes' => [
                'type' => $this->getSalesforceObjectName()
            ]
        ]);
        $sfModel->updateOrCreate(
            [
                'Id' => $this->{$this->getSalesforceIdField()},
            ],
            $this->getSalesforceSyncedValues()
        );
        return $this;
    }

    public function getSalesforceObjectName()
    {
        return $this->salesForceObject ?? Str::afterLast(self::class, '\\');
    }

    public function getSalesforceMapping()
    {
        return $this->salesForceFieldMap ?? [];
    }

    public function getSalesforceSyncedValues()
    {
        $values = collect([]);
        foreach ($this->getSalesforceMapping() as $modelKey => $sfKey) {
            $values->put($sfKey, $this->$modelKey);
        }
        return $values->toArray();
    }

    public function getSalesforceIdField()
    {
        return $this->salesForceIdField ?? 'salesforce';
    }
}
