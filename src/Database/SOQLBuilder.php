<?php

namespace Lester\EloquentSalesForce\Database;

use Illuminate\Database\Eloquent\Builder as Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\Paginator;
use Lester\EloquentSalesForce\ServiceProvider;

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

	/**
	 * getModels function.
	 *
	 * @access public
	 * @param string $columns (default: ['*'])
	 * @return void
	 */
	public function getModels($columns = ['*'])
	{
		return parent::getModels($this->getSalesForceColumns($columns));
	}

	/**
	 * Paginate the given query.
	 *
	 * @param  int  $perPage
	 * @param  array  $columns
	 * @param  string  $pageName
	 * @param  int|null  $page
	 * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
	 *
	 * @throws \InvalidArgumentException
	 */
	public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
	{
		$columns = $this->getSalesForceColumns($columns);

		$table = $this->model->getTable();
		$total = \Forrest::query("SELECT COUNT() FROM $table")['totalSize'];

		$page = $page ?: Paginator::resolveCurrentPage($pageName);
		$perPage = $perPage ?: $this->model->getPerPage();
		$results = $total
									? $this->forPage($page, $perPage)->get($columns)
									: $this->model->newCollection();
		return $this->paginator($results, $total, $perPage, $page, [
			'path' => Paginator::resolveCurrentPath(),
			'pageName' => $pageName,
		]);
	}

	/**
	 * Mass insert of models
	 * @return Collection of models.
	 */
	public function insert(\Illuminate\Support\Collection $values)
	{
		$table = $this->model->getTable();

		if (is_array($values)) {
			$values = collect($values);
		}
		$counter = 1;
		$values = $values->map(function($object, $index) {
			$attrs = $object->sf_attributes;
			$attrs['referenceId'] = 'ref' . $index;
			$object->sf_attributes = $attrs;
			return $object;
		});

		$payload = [
            'method' => 'post',
            'body' => [
                'records' => $values->toArray()
            ]
        ];

		$response = \Forrest::composite('tree/' . $table, $payload);

		$response = collect($response['results']);
		$model = $this->model;
		$response = $response->map(function($item) use ($model) {
			unset($item['referenceId']);
			foreach ($item as $key => $value) {
				$item[ucwords($key)] = $value;
				unset($item[$key]);
			}
			return new $model($item);
		});

		return $response;
	}

	/**
	 * getSalesForceColumns function.
	 *
	 * @access protected
	 * @param mixed $columns
	 * @param mixed $table (default: null)
	 * @return array
	 */
	protected function getSalesForceColumns($columns, $table = null) {
		$table = $table ?: $this->model->getTable();

		return ServiceProvider::objectFields($table, $columns);
	}


}
