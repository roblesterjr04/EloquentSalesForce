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

    public function syncWithSalesforce()
    {
        if ($object = $this->syncTwoWay()) {
            $this->syncSalesforceToLocal($object);
        }
        $this->syncLocalToSalesforce();
    }

    public function syncLocalToSalesforce()
    {
        $sfModel = new SalesForceObject([
            'attributes' => [
                'type' => $this->getSalesforceObjectName()
            ]
        ]);
        $sfModel = $sfModel->updateOrCreate(
            [
                'Id' => $this->{$this->getSalesforceIdField()},
            ],
            $this->getSalesforceSyncedValues()
        );
        $this->{$this->getSalesforceIdField()} = $sfModel->Id;
        return $this;
    }

    public function syncSalesforceToLocal($object)
    {
        foreach ($this->getSalesforceMapping() as $modelKey => $sfKey) {
            $this->{$modelKey} = $object->{$sfKey};
        }
        $this->withoutEvents(function() {
            $this->save();
        });
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

    public function getSalesforceDates()
    {
        return $this->salesForceDates ?? [
            'created_at' => 'CreatedDate',
            'updated_at' => 'LastModifiedDate',
        ];
    }

    public function syncTwoWay()
    {
        if (config('eloquent_sf.syncTwoWay') && ($id = $this->{$this->getSalesforceIdField()}) !== null) {
            $sfModel = new SalesForceObject([
                'Id' => $id,
                'attributes' => [
                    'type' => $this->getSalesforceObjectName()
                ]
            ] + $this->getSalesforceSyncedValues());
            $sfModel->exists = true;
            $sfModel->refresh();
            return (strtotime($sfModel->LastModifiedDate) > $this->updated_at->timestamp) ? $sfModel : false;
        }
        return false;
    }

}
