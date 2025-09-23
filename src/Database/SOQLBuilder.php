<?php

namespace Lester\EloquentSalesForce\Database;

use Illuminate\Database\Eloquent\Builder as Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\Paginator;
use Lester\EloquentSalesForce\ServiceProvider;
use Lester\EloquentSalesForce\Facades\SObjects;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use PDO;
use Closure;
use Lester\EloquentSalesForce\Model;
use Illuminate\Database\Eloquent\Relations\Relation;


class SOQLBuilder extends Builder
{

    /**
	 * {@inheritDoc}
	 */
	public function __construct(QueryBuilder $query)
	{
        //$pdo = new PDO('sqlite::memory:');
        //$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        //$pdo = new \Illuminate\Database\PDO\Connection($pdo);

		$query->connection = new SOQLConnection();
        $query->grammar = new SOQLGrammar($query->connection);
        $query->connection->setGrammar($query->grammar);

		parent::__construct($query);
	}

    //TODO
    /*public function exists()
    {
        dd('here');
    }*/

    /**
     * Set a model instance for the model being queried.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return $this
     */
    public function setModel($model)
    {
        $this->model = $model;
        $this->query->grammar->setModel($model);

        $this->query->from($model->getTable());

        return $this;
    }

	public function batch($tag = null)
	{
		return SObjects::getBatch()->batch($this, $tag);
	}

	public function toSql()
	{
        $columns = implode(', ', $this->describe());
        $query = str_replace('*', $columns, parent::toSql());
		$query = str_replace('`', '', $query);

        $bindings = array_map(
            fn ($value) => Str::replace("'", "\'", $value),
            $this->getBindings()
        );
        $prepared = Str::replaceArray('?', $bindings, $query);

		return $prepared;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getModels($columns = ['*'])
	{
		if (count($this->model->columns) &&
			in_array('*', $columns)) {
			$cols = $this->model->columns;
            if (!in_array('CreatedDate', $cols)) $cols[] = 'CreatedDate';
            if (!in_array('LastModifiedDate', $cols)) $cols[] = 'LastModifiedDate';

            if (!in_array($this->model->getTable(), config('eloquent_sf.noSoftDeletesOn', ['User'])) &&
                !in_array('IsDeleted', $cols)) $cols[] = 'IsDeleted';

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
     * @param null $perPage
     * @param array|string|string[] $columns
     * @param string $pageName
     * @param null $page
     * @param null $total
     */
	public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
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
        $chunkSize = config('eloquent_sf.batch.insert.size', 200) <= 200 ? config('eloquent_sf.batch.insert.size', 200) : 200;

		$counter = 1;
		$collection = $collection->map(function($object, $index) {
			$attrs = $object->sf_attributes;
			$attrs['referenceId'] = 'ref' . $index;
			$object->sf_attributes = $attrs;
			return $object;
		});



		try {
			$responseCollection = collect([]);
			foreach ($collection->chunk($chunkSize) as $collectionBatch) {
				$payload = [
					'method' => 'post',
					'body' => [
						'records' => $collectionBatch->values()
					]
				];

				$response = SObjects::composite('tree/' . $table, $payload);
				SObjects::log("SOQL Bulk Insert", $payload);

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
				$responseCollection = $responseCollection->merge($response);
			}

			return $responseCollection;
		} catch (\Exception $e) {
			$response = json_decode($e->getMessage());
            if (is_array($response)) SObjects::processExceptions($response);
            else throw $e;
		}
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
		return ServiceProvider::objectFields($table ?: $this->model->getTable(), $columns);
	}

	/**
	 * describe function. returns columns of object.
	 *
	 * @return array
	 */
	public function describe()
	{
		return count($this->model->columns) ? $this->model->columns : $this->getSalesForceColumns(['*'], $this->model->getTable());
	}

    public function delete($allOrNone = false)
    {
        $models = collect($this->getModels());
        foreach ($models->chunk(200) as $chunk) {
            SObjects::composite('sobjects', [
                'method' => 'delete',
                'query' => [
                    'allOrNone' => $allOrNone,
                    'ids' => implode(',',$chunk->pluck('Id')->values()->toArray()),
                ]
            ]);
            SObjects::queryHistory()->push(['delete' => $chunk->pluck('Id')]);
        }

    }

    public function truncate()
    {
        return $this->delete();
    }

    public function withTrashed()
    {
        $this->query->connection = new SOQLConnection(true);
        $this->query->connection->setGrammar($this->query->grammar);
        return $this;
    }

    public function onlyTrashed()
    {
        $this->query->connection = new SOQLConnection(true);
        $this->query->connection->setGrammar($this->query->grammar);
        return $this->where('IsDeleted', TRUE);
    }

    public function getPicklistValues($field)
    {
        $table = $this->model->getTable();
        return SObjects::getPicklistValues($table, $field);
    }

    public function from($table)
    {
        $this->model->setTable($table);
        $this->query->from($table);
        return $this;
    }

    public function whereTime(...$args)
    {
        return $this->where(...$args);
    }

}
