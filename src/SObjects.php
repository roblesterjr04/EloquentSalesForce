<?php

namespace Lester\EloquentSalesForce;

use Forrest;
use Cache;
use Session;

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
        self::authenticate();

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

    /**
	 * Authenticates Forrest
	 */
	public function authenticate()
	{
        $storage = ucwords(config('eloquent_sf.forrest.storage.type'));
        if (!$storage::has(config('eloquent_sf.forrest.storage.path').'token'))
            Forrest::authenticate();
        return decrypt($storage::get(config('eloquent_sf.forrest.storage.path').'token'));
	}

    public function __call($name, $arguments)
    {
        return Forrest::$name(...$arguments);
    }

    public function object($name)
    {
        return new Object([], $name);
    }

}
