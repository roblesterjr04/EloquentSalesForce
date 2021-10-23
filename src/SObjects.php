<?php

namespace Lester\EloquentSalesForce;

/** @scrutinizer ignore-call */use Forrest;
use Omniphx\Forrest\Exceptions\MissingTokenException;
use Omniphx\Forrest\Exceptions\MissingResourceException;
use Omniphx\Forrest\Exceptions\MissingVersionException;
use Lester\EloquentSalesForce\Database\SOQLBatch;
use Illuminate\Support\Str;
use Cache;
use Session;
use Log;

class SObjects
{

	private $batch;

	public function __construct()
	{
		$this->batch = new SOQLBatch([]);
	}

	/**
	 * Bulk update SObjects in SalesForce
	 *
	 * @param  \Illuminate\Support\Collection $collection [collection of Lester\EloquentSalesForce\Model]
	 * @param  boolean                     $allOrNone  [Should update fail entirely if one object fails to update?]
	 */
	public function update(\Illuminate\Support\Collection $collection, $allOrNone = false)
	{
        $chunkSize = config('eloquent_sf.batch.insert.size', 200) <= 200 ? config('eloquent_sf.batch.insert.size', 200) : 200;

		foreach ($collection->chunk($chunkSize) as $collectionBatch) {
			$results = self::composite('sobjects', [
				'method' => 'patch',
				'body' => tap([
					'allOrNone' => $allOrNone,
					'records' => $collectionBatch->map(function($object) {
						return $object->writeableAttributes(['IsDeleted', 'CreatedDate', 'LastModifiedDate']);
					})->values()
				], function($payload) {
					$this->log('SOQL Bulk Update', $payload);
				})
			]);
		}
	}

	/**
	 * Authenticates Forrest
	 */
	public function authenticate()
	{
		$storage = ucwords(config('eloquent_sf.forrest.storage.type'));
		if (!$storage::has(config('eloquent_sf.forrest.storage.path') . 'token')) {
			if (config('eloquent_sf.forrest.authentication') == 'WebServer') return Forrest::authenticate();
            else Forrest::authenticate();
        }
		$tokens = (object) decrypt($storage::get(config('eloquent_sf.forrest.storage.path') . 'token'));
		Session::put('eloquent_sf_instance_url', $tokens->instance_url);
		return $tokens;
	}

	public function instanceUrl()
	{
		return Session::get('eloquent_sf_instance_url');
	}

    public function callback()
    {
        return Forrest::callback();
    }

	public function __call($name, $arguments)
	{
		self::authenticate();
		try {
			return Forrest::$name(...$arguments);
		} catch (MissingTokenException $ex) {
			$this->log("MissingTokenException, trying again...", $ex->getTrace(), 'error');
			self::authenticate();
			return Forrest::$name(...$arguments);
		} catch (MissingResourceException $ex) {
			$this->log("MissingResourceException, trying again...", $ex->getTrace(), 'error');
			self::authenticate();
			Forrest::resources();
			return Forrest::$name(...$arguments);
		} catch (MissingVersionException $ex) {
			$this->log("MissingVersionException, trying again...", $ex->getTrace(), 'error');
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
		return $full ? $this->object($object)->describe() : Forrest::describe($object);
	}

	/**
	 * Instantiates and returns an anonymous model keyed to a specified SalesForce object type.
	 *
	 * @param  [type] $name       [description]
	 * @param  array  $attributes [description]
	 * @return [type]             [description]
	 */
	public function object($attributes = [])
	{
		return new SalesForceObject($attributes);
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
		foreach (str_split($str, 5) as $seq) {
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
			$retval .= strrpos("ABCDEFGHIJKLMNOPQRSQUVWXYZ", substr($str, $i, 1)) ? '1' : '0';

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

	public function getBatch()
	{
		return $this->batch;
	}

	public function runBatch(&$errors = [])
	{
		return tap($this->batch->run(), function() {
			$this->batch = new SOQLBatch([]);
		});
	}

	public function log($message, $details = [], $level = 'info')
	{
		$logs = config('eloquent_sf.logging', config('logging.default'));

		Log::channel($logs)->$level($message, $details);
	}

    /**
     * Based on characters and length of $str, determine if it appears to be a
     * SalesForce ID.
     *
     * @param string $str String to test
     *
     * @return bool
     */
    public function isSalesForceId($str)
    {
        return boolval(\preg_match('/^[0-9a-zA-Z]{15,18}$/', $str));
    }

}
