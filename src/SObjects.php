<?php

namespace Lester\EloquentSalesForce;

/** @scrutinizer ignore-call */use Forrest;
use Omniphx\Forrest\Exceptions\MissingTokenException;
use Omniphx\Forrest\Exceptions\MissingResourceException;
use Omniphx\Forrest\Exceptions\MissingVersionException;
use Cache;
use Session;
use Log;

class SObjects
{

	private $batch;

	public function __construct()
	{
		$this->batch = collect([]);
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
		return self::composite('sobjects', [
			'method' => 'patch',
			'body' => tap([
				'allOrNone' => $allOrNone,
				'records' => $collection->map(function($object) {
					return $object->writeableAttributes();
				})
			], function($payload) {
				$this->log('SOQL Bulk Update', $payload);
			})
		]);
	}

	/**
	 * Authenticates Forrest
	 */
	public function authenticate()
	{
		$storage = ucwords(config('eloquent_sf.forrest.storage.type'));
		if (!$storage::has(config('eloquent_sf.forrest.storage.path') . 'token'))
			Forrest::authenticate();
		$tokens = (object) decrypt($storage::get(config('eloquent_sf.forrest.storage.path') . 'token'));
		Session::put('eloquent_sf_instance_url', $tokens->instance_url);
		return $tokens;
	}

	public function __call($name, $arguments)
	{
		self::authenticate();
		try {
			return Forrest::$name(...$arguments);
		} catch (MissingTokenException $ex) {
			self::authenticate();
			return Forrest::$name(...$arguments);
		} catch (MissingResourceException $ex) {
			self::authenticate();
			Forrest::resources();
			return Forrest::$name(...$arguments);
		} catch (MissingVersionException $ex) {
			self::authenticate();
			Forrest::versions();
			return Forrest::$name(...$arguments);
		}
	}

	/**
	 * Describes a specific SalesForce Model/Object pair.
	 *
	 * @param  [type]  $object [description]
	 * @param  boolean $full   [description]
	 * @return [type]          [description]
	 */
	public function describe($object, $full = false)
	{
		self::authenticate();
		return $full ? $this->object($object)->describe() : Forrest::desribe($object);
	}

	/**
	 * Instantiates and returns an anonymous model keyed to a specified SalesForce object type.
	 *
	 * @param  [type] $name       [description]
	 * @param  array  $attributes [description]
	 * @return [type]             [description]
	 */
	public function object($name, $attributes = [])
	{
		return new SalesForceObject($attributes, $name);
	}

	/**
	 * Converts a 15 character ID to 18 character ID.
	 *
	 * @param  [type] $str [description]
	 * @return [type]      [description]
	 */
	public function convert($str)
	{
		if (strlen($str) <> 15) {
			return $str;
		}
		$retval = '';
		foreach (Str::split($str, 5) as $seq) {
					$retval .= substr("ABCDEFGHIJKLMNOPQRSTUVWXYZ012345", bindec(strrev($this->is_uppercase($seq))), 1);
		}

		return $str . $retval;
	}

	/**
	 * Utility conversion function for the ID converter above.
	 *
	 * @param  [type]  $str [description]
	 * @return boolean      [description]
	 */
	private function is_uppercase($str)
	{
		$retval = '';
		for ($i = 0; $i < strlen($str); $i++)
			$retval .= strrpos("AABCDEFGHIJKLMNOPQRSQUVWXYZ", substr($str, $i, 1)) ? '1' : '0';

		return $retval;
	}

	/**
	 * Function provided by @seankndy to get picklist values
	 *
	 * @param  [type] $object [description]
	 * @param  [type] $field  [description]
	 * @return [type]         [description]
	 */
	public function getPicklistValues($object, $field)
	{
		Forrest::authenticate();
		$desc = Forrest::sobjects($object . '/describe');

		if (!isset($desc['fields'])) {
					return collect([]);
		}

		foreach ($desc['fields'] as $f) {
			if ($f['name'] == $field) {
				$values = [];
				foreach ($f['picklistValues'] as $p) {
					$values[$p['value']] = $p['label'];
				}
				return collect($values);
			}
		}
		return collect([]);
	}

	public function addBatch($builder)
	{
		if ($this->batch->count() >= 25) {
			throw new \Exception('You cannot create more than 25 batch queries.');
		}
		$this->batch->push($builder);
		return $builder;
	}

	public function runBatch(&$errors = [])
	{
		$version = 'v' . collect(\SObjects::versions())->last()['version'];

		$results = \SObjects::composite('batch', [
            'method' => 'post',
            'body' => [
                'batchRequests' => tap($this->batch->map(function($query) use ($version) {
					return [
						'method' => 'get',
						'url' => $version . '/query?q=' . urlencode($query->toSql()),
					];
				})->toArray(), function($payload) {
					$this->log('SOQL Batch Query', $payload);
				})
            ]
        ]);

		$output = collect([]);
		foreach ($results['results'] as $query) {
			if ($query['statusCode'] != 200) {
				$errors[] = $query;
			} else {
				$objects = collect($query['result']['records']);
				$type = $objects->first()['attributes']['type'];
				$objects = $objects->map(function($item) {
					return new SalesForceObject($item);
				});
				$output->push((object)[
					'type' => $type,
					'objects' => $objects
				]);
			}
		}

		return $output;
	}

	public function log($message, $details = [], $level = 'info')
	{
		$default = env('LOG_CHANNEL', 'stack');
		$logs = env('SOQL_LOG', $default);

		Log::channel($logs)->$level($message, $details);
	}

}
