<?php

namespace Lester\EloquentSalesForce\Traits;

use Lester\EloquentSalesForce\Facades\SObjects;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Lester\EloquentSalesForce\SalesForceObject;

trait SyncsWithSalesforce
{
    private $tempSyncObject = null;

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
            if ($this->isDirty()) {
                $whatToDo = config('eloquent_sf.syncPriority', 'salesforce');
                $differences = $this->syncDifferences($object);
                switch($whatToDo) {
                    case 'exception':
                        throw new \Exception('Sync Conflict');
                        break;
                    case 'silent':
                        return $this;
                        break;
                    case 'salesforce':
                        $this->fill($object->only($differences));
                        break;
                }

            }
            $this->syncSalesforceToLocal($object);
        }
        return $this->syncLocalToSalesforce();
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

    public function getSalesforceDates()
    {
        return $this->salesForceDates ?? [
            'created_at' => 'CreatedDate',
            'updated_at' => 'LastModifiedDate',
        ];
    }

    private function syncTempObject()
    {
        if ($this->tempSyncObject === null) {

            $sfModel = new SalesForceObject([
                'Id' => $this->{$this->getSalesforceIdField()},
                'attributes' => [
                    'type' => $this->getSalesforceObjectName()
                ]
            ] + $this->getSalesforceSyncedValues());

            $this->tempSyncObject = $sfModel->refresh();
        }

        return $this->tempSyncObject;
    }

    public function syncTwoWay()
    {
        if (config('eloquent_sf.syncTwoWay') && ($id = $this->{$this->getSalesforceIdField()}) !== null) {
            $sfModel = $this->syncTempObject();
            return $this->syncSalesforceIsNewer($sfModel) ? $sfModel : false;
        }
        return false;
    }

    public function syncDifferences($object)
    {
        return array_keys(array_diff($this->getSalesforceSyncedValues(), $object->only(array_values($this->getSalesforceMapping()))));
    }

    public function syncSalesforceIsNewer($object)
    {
        return strtotime($object->LastModifiedDate) > $this->updated_at->timestamp;
    }

}
