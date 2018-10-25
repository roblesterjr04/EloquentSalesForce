<?php

namespace Lester\EloquentSalesForce;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Lester\EloquentSalesForce\Database\SOQLBuilder as Builder;
use Lester\EloquentSalesForce\Database\SOQLHasMany as HasMany;

abstract class Model extends EloquentModel
{
	protected $guarded = [];
	
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
	
	public function __construct(Array $attributes = [])
	{
		$this->table = $this->table ?: class_basename($this);
		\Forrest::authenticate();
		parent::__construct($attributes);
	}
	
	public static function create(array $attributes)
    {
	    $object = new static($attributes);
	    return $object->save();
    }
    
    public function update(array $attributes = array(), array $options = array())
    {
	    $this->attributes = array_merge($this->attributes, $attributes);
	    return $this->save($options);
    }
    
    public function save(array $options = array())
    {
	    $body = $this->attributes;
	    unset($body['attributes']);
	    if (isset($body['Id'])) {
		    unset($body['Id']);
		    $updated = \Forrest::sobjects($this->table . '/' . $this->Id, [
			    'method' => 'patch',
			    'body' => $body
		    ]);
		    return $this;
	    } else {
		    $new = \Forrest::sobjects($this->table, [
			    'method' => 'post',
			    'body' => $body,
		    ]);
		    if ($new['success']) return $this->find($new['id']);
	    }
		return $this;
    }
    
    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }
    
    /**
     * Define a one-to-many relationship.
     *
     * @param  string  $related
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
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
            $foreignKey = ucwords(Str::camel($relation.'_'.$instance->getKeyName()));
        }
        
        // Once we have the foreign key names, we'll just create a new Eloquent query
        // for the related models and returns the relationship instance which will
        // actually be responsible for retrieving and hydrating every relations.
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
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    protected function newSOQLHasMany(Builder $query, Model $parent, $foreignKey, $localKey)
    {
        return new HasMany($query, $parent, $foreignKey, $localKey);
    }
    
    /**
     * Get the default foreign key name for the model.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return camel_case(class_basename($this).'_'.$this->getKeyName());
    }
}
