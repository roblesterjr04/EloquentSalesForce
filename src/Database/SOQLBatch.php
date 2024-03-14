<?php

namespace Lester\EloquentSalesForce\Database;

use Session;
use Log;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Lester\EloquentSalesForce\SalesForceObject;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Lester\EloquentSalesForce\Database\SOQLBuilder as Builder;
use Lester\EloquentSalesForce\Database\SOQLHasMany as HasMany;
use Lester\EloquentSalesForce\Database\SOQLHasOne as HasOne;
use Lester\EloquentSalesForce\Facades\SObjects;
use Illuminate\Support\Collection;

class SOQLBatch extends Collection
{

    public function results($key)
    {
        return $this->get($key);
    }

    public function builder($key)
    {
        return parent::get($key, $this->emptyItem())->builder;
    }

    public function class($key)
    {
        return parent::get($key, $this->emptyItem())->class;
    }

    public function get($key, $default = null)
    {
        return parent::get($key, $this->emptyItem())->results;
    }

    private function emptyItem()
    {
        return (object)[
            'class' => null,
			'builder' => null,
			'results' => collect([]),
        ];
    }

    public function query(...$builders)
    {
        $tempColl = null;
        foreach ($builders as $builder) {
            $tempColl = tap($this, function($collection) use ($builder) {
                $collection->batch($builder);
            });
        }
        return $tempColl ?: $this;
    }

    /*public function put($tag, $builder)
    {
        return tap($this, function($collection) use ($builder, $tag) {
            $collection->batch($builder, $tag);
        });
    }*/

    public function batch(Builder $builder, $tag = null)
    {
        $index = $this->count();
        parent::put($tag ?: class_basename($builder->getModel()) . "_$index", (object)[
            'class' => $builder->getModel(),
			'builder' => $builder,
			'results' => collect([]),
		]);
		return $builder;
    }

    public function run()
    {
        if ($this->isEmpty()) return $this;
        $chunkSize = config('eloquent_sf.batch.select.size', 25);
        if ($chunkSize > 25) {
            \SObjects::log('Salesforce will only allow select batches of 25 queries.', [], 'warn');
        }

        $version = config('eloquent_sf.forrest.version') ?: 'v' . collect(\SObjects::versions())->last()['version'];

        foreach ($this->chunk($chunkSize) as $chunk) {
            $results = \SObjects::composite('batch', [
                'method' => 'post',
                'body' => [
                    'batchRequests' => tap($chunk->map(function($query) use ($version) {
                        return [
                            'method' => 'get',
                            'url' => $version . '/query?q=' . urlencode($query->builder->toSql()),
                        ];
                    })->values()->toArray(), function($payload) {
    					\SObjects::log('SOQL Batch Query', $payload);
    				})
                ]
            ]);

            $index = 0;
            foreach ($chunk as $key => $batch) {
                $batch_result = $results['results'][$index];
                if ($batch_result['statusCode'] != 200) {
                    $batch->results = (object)$batch_result;
                } else {
                    $batch->results = collect($batch_result['result']['records'])->map(function($item) use ($batch) {
                        $model = $batch->class;
                        return new $model($item);
                    });
                }
                $this->put($key, $batch);
                $index++;
            }
        }

        return $this;
    }

}
