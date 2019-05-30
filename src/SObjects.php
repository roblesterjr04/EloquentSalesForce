<?php

namespace Lester\EloquentSalesForce;

/** @scrutinizer ignore-call */use Forrest;
use Cache;
use Session;

class SObjects
{

    public function __construct()
    {
        /** @scrutinizer ignore-call */
        self::authenticate();
    }

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

		$response = self::composite('sobjects', $payload);

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
        try {
            return Forrest::$name(...$arguments);
        } catch (Omniphx\Forrest\Exceptions\MissingTokenException $ex) {
            self::authenticate();
            return Forrest::$name(...$arguments);
        }
    }

    public function describe($object, $full = false)
    {
        return $full ? $this->object($object)->describe() : Forrest::desribe($object);
    }

    public function object($name, $attributes = [])
    {
        return new SalesForceObject($attributes, $name);
    }

}
