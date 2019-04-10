<?php

namespace Lester\EloquentSalesForce;

use Forrest;

class SObjects
{

    /**
     * Bulk update SObjects in SalesForce
     *
     * @param  \Illuminate\Support\Collection $collection [collection of Lester\EloquentSalesForce\Model]
     * @param  boolean                     $allOrNone  [Should update fail entirely if one object fails to update?]
     * @return array                                  [Response from SalesForce]
     */
    public function update(\Illuminate\Support\Collection $collection, $allOrNone = false)
	{
		$payload = [
            'method' => 'patch',
            'body' => [
				'allOrNone' => $allOrNone,
                'records' => $collection->toArray()
            ]
        ];

		$response = Forrest::composite('sobjects', $payload);

		return $response;
	}

}
