<?php

namespace Lester\EloquentSalesForce\Database;

use Illuminate\Database\Eloquent\Builder as Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\Paginator;
use Lester\EloquentSalesForce\ServiceProvider;
use Lester\EloquentSalesForce\Facades\SObjects;

class SOQLBuilder extends Builder
{
	/**
	 * {@inheritDoc}
	 */
	public function __construct(QueryBuilder $query)
	{
		$query->connection = new SOQLConnection(null);
		$query->grammar = new SOQLGrammar();

		parent::__construct($query);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getModels($columns = ['*'])
	{
		if (count($this->model->columns) &&
			in_array('*', /** @scrutinizer ignore-type */ $columns)) {
			$cols = $this->model->columns;
		} else {
			$cols = $this->getSalesForceColumns($columns);
		}
		return parent::getModels($cols);
	}

	/**
	 * {@inheritDoc}
	 */
	public function cursor()
	{
		if (!$this->query->columns || in_array('*', $this->query->columns)) {
			$this->query->columns = $this->model->columns;
		}

		return parent::cursor();
	}

	/**
	 * {@inheritDoc}
	 */
	public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
	{
		$columns = $this->getSalesForceColumns($columns);

		$table = $this->model->getTable();

		/** @scrutinizer ignore-call */
		//$total = SObjects::query("SELECT COUNT() FROM $table")['totalSize'];
		$builder = $this->getQuery()->cloneWithout(
			['columns', 'orders', 'limit', 'offset']
		);
		$builder->aggregate = ['function' => 'count', 'columns' => ['Id']];
		$total = $builder->get()[0]['aggregate'];
		if ($total > 2000) { // SOQL OFFSET limit is 2000
			$total = 2000;
		}

		$page = $page ?: Paginator::resolveCurrentPage($pageName);
		$perPage = $perPage ?: $this->model->getPerPage();
		$results = $total
			? /** @scrutinizer ignore-call */ $this->forPage($page, $perPage)->get($columns)
			: $this->model->newCollection();
		return $this->paginator($results, $total, $perPage, $page, [
			'path' => Paginator::resolveCurrentPath(),
			'pageName' => $pageName,
		]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function insert(\Illuminate\Support\Collection $collection)
	{
		$table = $this->model->getTable();

		$counter = 1;
		$collection = $collection->map(function($object, $index) {
			$attrs = $object->sf_attributes;
			$attrs['referenceId'] = 'ref' . $index;
			$object->sf_attributes = $attrs;
			return $object;
		});

		$payload = [
			'method' => 'post',
			'body' => [
				'records' => $collection->toArray()
			]
		];

		/** @scrutinizer ignore-call */
		$response = SObjects::composite('tree/' . $table, $payload);

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
	protected function getSalesForceColumns($columns, $table = null)
	{
		$table = $table ?: $this->model->getTable();

		return ServiceProvider::objectFields($table, $columns);
	}

	/**
	 * describe function. returns columns of object.
	 *
	 * @return array
	 */
	public function describe()
	{
		$table = $this->model->getTable();

		if (count($this->model->columns)) {
			return $this->model->columns;
		}

		return $this->getSalesForceColumns(['*'], $table);
	}
}
