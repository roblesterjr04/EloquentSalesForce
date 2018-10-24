<?php
	
namespace Lester\EloquentSalesForce\Database;

use Illuminate\Database\Eloquent\Builder as Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class SOQLBuilder extends Builder
{
	
	
    /**
     * Create a new Eloquent query builder instance.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return void
     */
    public function __construct(QueryBuilder $query)
    {
	    $query->connection = new SOQLConnection(null);
	    $query->grammar = new SOQLGrammar();
	    
	    parent::__construct($query);
    }
    
    public function getModels($columns = ['*'])
    {
	    if ($columns == ['*']) {
		    $layouts = \Forrest::sobjects($this->model->getTable() . '/' . config('eloquent_sf.layout', 'describe/compactLayouts/primary') . '/');
		    $columns = array_pluck($layouts["fieldItems"], 'layoutComponents.0.details.name');
		    $columns = array_merge($columns, ['Id']);
	    }
	    
	    return parent::getModels($columns);
    }
	
	
}