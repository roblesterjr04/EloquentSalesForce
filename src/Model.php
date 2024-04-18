<?php

namespace Lester\EloquentSalesForce;

use Session;
use Log;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Lester\EloquentSalesForce\Database\SOQLBuilder as Builder;
use Lester\EloquentSalesForce\Database\SOQLHasMany as HasMany;
use Lester\EloquentSalesForce\Database\SOQLHasOne as HasOne;
use Lester\EloquentSalesForce\Facades\SObjects;

abstract class Model extends EloquentModel
{
	protected $guarded = [];
	protected $readonly = [];

    protected $dateFormat = 'Y-m-d\TH:i:s.vO';

    protected $dates = [
        'CreatedDate',
        'LastModifiedDate',
    ];

    public $custom_headers = [];

    //public $timestamps = false;

    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'CreatedDate';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'LastModifiedDate';

	private $always_readonly = [
		'Id',
		'attributes',
	];

    public $incrementing = false;

    public $wasRecentlyCreated = false;

	public $columns = [];

	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'Id';

	/**
	 * The "type" of the auto-incrementing ID.
	 *
	 * @var string
	 */
	protected $keyType = 'string';

	public function __construct(Array $attributes = [], $table = null)
	{
		parent::__construct($attributes);

        if (isset($attributes['Id'])) {
            $this->exists = true;
        }

		$this->table = $table ?: $this->table ?: class_basename($this);
		$this->attributes['attributes'] = [
			'type' => $this->table
		];
	}

	public function writeableAttributes($exclude = [])
	{
	    $fields = array_merge($this->readonly, $exclude);
	    return Arr::except($this->attributes, $fields);
	}

	/*public static function create(array $attributes)
	{
		return (new static($attributes))->save();
	}*/

	/*public function update(array $attributes = array(), array $options = array())
	{

		$this->attributes = array_merge(Arr::only($this->attributes, ['Id']), $attributes);
		return $this->save($options);
	}*/

	public function delete()
	{

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

		try {
			/** @scrutinizer ignore-call */
			SObjects::sobjects($this->table . '/' . $this->Id, [
				'method' => 'delete'
			]);
			SObjects::log("{$this->table} object {$this->Id} deleted.");
            $this->fireModelEvent('deleted', false);
			return true;
		} catch (\Exception $e) {
			SObjects::log("{$this->table} object {$this->Id} failed to delete.", (array)$e, 'warning');
			return false;
		}
	}

    public function forceDelete()
    {
        return $this->delete();
    }

    /**
     * Perform a model insert operation.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return bool
     */
    protected function performInsert($query)
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        // If the model has an incrementing key, we can use the "insertGetId" method on
        // the query builder, which will give us back the final inserted ID for this
        // table from the database. Not all tables have to be incrementing though.
        $attributes = method_exists($this, 'getAttributesForInsert') ? $this->getAttributesForInsert() : $this->getAttributes();

        $attributes = collect($this->getDirty())->map(function($field, $key) {
            if ($this->isDateAttribute($key)) {
                $carbon = new Carbon($field);
                $format = $this->getDateFormat($key);
                $field = $carbon->format($format);
            }
            return $field;
        });

        if (empty($attributes)) {
            return $this;
        }

        SObjects::authenticate();
        $object = $this->sfObject();

        $result = SObjects::sobjects($object, [
            'method' => 'post',
            'body' => $attributes
        ]);

        SObjects::queryHistory()->push(['insert' => $attributes]);

        if (isset($result['success'])) {
            if (isset($result['id'])) {
                $this->Id = $result['id'];
            }
            //return $this;
        } else {
            return false;
        }

        // We will go ahead and set the 'exists' property to true, so that it is set when
        // the created event is fired, just in case the developer tries to update it
        // during the event. This will allow them to do so and run an update here.
        $this->exists = true;

        $this->wasRecentlyCreated = true;

        $this->fireModelEvent('created', false);

        return $this;
    }

    /**
     * Perform a model update operation.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return bool
     */
    protected function performUpdate($query)
    {
        // If the updating event returns false, we will cancel the update operation so
        // developers can hook Validation systems into their models and cancel this
        // operation if the model does not pass validation. Otherwise, we update.
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        // Once we have run the update operation, we will fire the "updated" event for
        // this model instance. This will allow developers to hook into these after
        // models are updated, giving them a chance to do any special processing.
        $dirty = collect($this->getDirty())->map(function($field, $key) {
            if ($this->isDateAttribute($key)) {
                $carbon = new Carbon($field);
                $format = $this->getDateFormat();
                $field = $carbon->format($format);
            }
            return $field;
        });

        if ($dirty->count() > 0) {

            SObjects::authenticate();
            $object = $this->sfObject();

            // The user can set this property on its models to set some custom value for the headers
            $headers = $this->custom_headers ?: null;

            $result = SObjects::sobjects($object, [
                'method' => 'patch',
                'body' => $dirty->toArray(),
                'headers' => $headers
            ]);

            SObjects::queryHistory()->push(['update' => $dirty->toArray()]);

            $this->syncChanges();

            $this->fireModelEvent('updated', false);
        }

        return true;
    }

	private function sfObject()
	{
		/** @scrutinizer ignore-call */
		return Arr::has($this->attributes, 'Id') ? $this->table . '/' . $this->Id : $this->table;
	}

	private function sfMethod()
	{
		return Arr::has($this->attributes, 'Id') ? 'patch' : 'post';
	}

	/**
	 * Create a new Eloquent query builder for the model.
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @return \Illuminate\Database\Eloquent\Builder|static
	 */
	public function newEloquentBuilder($query)
	{
		/** @scrutinizer ignore-call */
		SObjects::authenticate();
		return new Builder($query);
	}

	/**
	 * Define a one-to-many relationship.
	 *
	 * @param  string  $related
	 * @param  string  $foreignKey
	 * @param  string  $localKey
	 * @return \Lester\EloquentSalesForce\Database\SOQLHasMany
	 */
	public function hasMany($related, $foreignKey = null, $localKey = null)
	{
		$instance = $this->newRelatedInstance($related);
		$foreignKey = $foreignKey ?: $this->getForeignKey();
		$localKey = $localKey ?: $this->getKeyName();
		return $this->newSOQLHasMany(
			$instance->newQuery(), $this, $foreignKey, $localKey
		);
	}

	public function hasOne($related, $foreignKey = null, $localKey = null)
	{
		$instance = $this->newRelatedInstance($related);
		$foreignKey = $foreignKey ?: $this->getForeignKey();
		$localKey = $localKey ?: $this->getKeyName();
		return $this->newSOQLHasOne(
			$instance->newQuery(), $this, $foreignKey, $localKey
		);
	}

	/**
	 * Define an inverse one-to-one or many relationship.
	 *
	 * @param  string  $related
	 * @param  string  $foreignKey
	 * @param  string  $ownerKey
	 * @param  string  $relation
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function belongsTo($related, $foreignKey = null, $ownerKey = null, $relation = null)
	{
		// If no relation name was given, we will use this debug backtrace to extract
		// the calling method's name and use that as the relationship name as most
		// of the time this will be what we desire to use for the relationships.
		if (is_null($relation)) {
			$relation = $this->guessBelongsToRelation();
		}
		$instance = $this->newRelatedInstance($related);
		// If no foreign key was supplied, we can use a backtrace to guess the proper
		// foreign key name by using the name of the relationship function, which
		// when combined with an "_id" should conventionally match the columns.
		if (is_null($foreignKey)) {
			$foreignKey = ucwords(Str::camel($relation . '_' . $instance->getKeyName()));
		}

		// Once we have the foreign key names, we'll just create a new Eloquent query
		// for the related models and returns the relationship instance which will
		// actually be responsible for retrieving and hydrating every relation.
		$ownerKey = $ownerKey ?: $instance->getKeyName();
		return $this->newBelongsTo(
			$instance->newQuery(), $this, $foreignKey, $ownerKey, $relation
		);
	}

	/**
	 * Instantiate a new HasMany relationship.
	 *
	 * @param  \Illuminate\Database\Eloquent\Builder  $query
	 * @param  \Illuminate\Database\Eloquent\Model  $parent
	 * @param  string  $foreignKey
	 * @param  string  $localKey
	 * @return \Lester\EloquentSalesForce\Database\SOQLHasMany
	 */
	protected function newSOQLHasMany(Builder $query, Model $parent, $foreignKey, $localKey)
	{
		return new HasMany($query, $parent, $foreignKey, $localKey);
	}

	protected function newSOQLHasOne(Builder $query, Model $parent, $foreignKey, $localKey)
	{
		return new HasOne($query, $parent, $foreignKey, $localKey);
	}

	/**
	 * Get the default foreign key name for the model.
	 *
	 * @return string
	 */
	public function getForeignKey()
	{
		return Str::camel(class_basename($this) . '_' . $this->getKeyName());
	}

	/**
	 * [getSfAttributesAttribute description]
	 * @return array
	 */
	public function getSfAttributesAttribute()
	{
		return Arr::get($this->attributes, 'attributes');
	}

	/**
	 * [setSfAttributesAttribute description]
	 * @param array $value [description]
	 */
	public function setSfAttributesAttribute(array $value)
	{
		$this->attributes['attributes'] = $value;
	}

	public function getWebLinkAttribute()
	{
		$instance = SObjects::instanceUrl();
		return $instance ? rtrim($instance, '/') . Str::start($this->Id, '/') : null;
	}

	public function __toString()
	{
		return Arr::get($this->attributes, 'Id');
	}

	public static function columns()
	{
		return (new static([]))->columns;
	}

    public function trashed()
    {
        return $this->IsDeleted ?? false;
    }

    public function restore()
    {
        throw new \Exception('The SalesForce Rest API does not natively support UNDELETE');
    }

    public function getExistsAttribute()
    {
        return $this->Id !== null;
    }

    public function getShortDates()
    {
        return $this->shortDates;
    }

}
