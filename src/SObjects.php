<?php

namespace Lester\EloquentSalesForce;

/** @scrutinizer ignore-call */use Forrest;
use Omniphx\Forrest\Exceptions\MissingTokenException;
use Omniphx\Forrest\Exceptions\MissingResourceException;
use Omniphx\Forrest\Exceptions\MissingVersionException;
use Cache;
use Session;

class SObjects
{

    public function __construct()
    {

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
        $tokens = (object)decrypt($storage::get(config('eloquent_sf.forrest.storage.path').'token'));
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

    public function describe($object, $full = false)
    {
        self::authenticate();
        return $full ? $this->object($object)->describe() : Forrest::desribe($object);
    }

    public function object($name, $attributes = [])
    {
        return new SalesForceObject($attributes, $name);
    }

    public function convert($str)
    {
        if (strlen($str) <> 15) return $str;
        $retval = '';
        foreach (str_split($str, 5) as $seq)
            $retval .= substr("ABCDEFGHIJKLMNOPQRSTUVWXYZ012345", bindec(strrev($this->is_uppercase($seq))), 1);

        return $str.$retval;
    }

    private function is_uppercase($str)
    {
        $retval = '';
        for ($i=0; $i<strlen($str); $i++)
            $retval .= strrpos("AABCDEFGHIJKLMNOPQRSQUVWXYZ", substr($str,$i,1)) ? '1':'0';

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

        if (!isset($desc['fields']))
            return collect([]);

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

}
