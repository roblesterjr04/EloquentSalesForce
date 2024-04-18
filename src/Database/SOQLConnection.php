<?php

namespace Lester\EloquentSalesForce\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\MySqlBuilder;
use Illuminate\Database\Query\Processors\MySqlProcessor;
use Illuminate\Database\Query\Grammars\MySqlGrammar as QueryGrammar;
use Illuminate\Database\Schema\Grammars\MySqlGrammar as SchemaGrammar;
use Omniphx\Forrest\Exceptions\MissingResourceException;
use Lester\EloquentSalesForce\Facades\SObjects;
use Closure;
use Carbon\Carbon;
use Illuminate\Support\Str;
use DateTimeInterface;
use Illuminate\Support\Carbon as SupportCarbon;
use Omniphx\Forrest\Exceptions\SalesforceException;
use Lester\EloquentSalesForce\Exceptions;

class SOQLConnection extends Connection
{

    private $all = false;

    public function __construct($all = false)
    {
        $this->all = $all;
    }

    public function setGrammar($grammar)
    {
        $this->queryGrammar = $grammar;
    }
	/**
	 * {@inheritDoc}
	 */
	public function select($query, $bindings = [], $useReadPdo = true)
	{
        try {
		    return $this->run($query, $bindings, function($query, $bindings) use ($useReadPdo) {

			    $statement = $this->prepare($query, $bindings);
                /** @scrutinizer ignore-call */
                try {
			        $result = $this->all ? SObjects::queryAll($statement) : SObjects::query($statement);
                    SObjects::queryHistory()->push($statement);
                } catch (\Exception $e) {
                    $response = json_decode($e->getMessage());
                    if (is_array($response)) SObjects::processExceptions($response);
                    else throw $e;
                }

			    SObjects::log('SOQL Query', [
				    'query' => $statement
			    ]);

			    $records = $result['records'];

			    while (isset($result['nextRecordsUrl'])) {
				    $result = SObjects::next($result['nextRecordsUrl']);
				    if (isset($result['records'])) {
					    $records = \array_merge($records, $result['records']);
				    }
			    }

			    return $records;
		    });
        } catch (\Exception $e) {
            throw $e->getPrevious();
        }
	}

	/**
	 * {@inheritDoc}
	 */
	public function cursor($query, $bindings = [], $useReadPdo = true)
	{
		$result = $this->run($query, $bindings, function($query, $bindings) {
			if ($this->pretending()) {
				return [];
			}

			$statement = $this->prepare($query, $bindings);

            if ($this->all) {
                return SObjects::queryAll($statement);
            }

			/** @scrutinizer ignore-call */
			return SObjects::query($statement);
		});

		while (true) {
			foreach ($result['records'] as $record) {
				yield $record;
			}
			if (!isset($result['nextRecordsUrl'])) {
				break;
			}
			$result = SObjects::next($result['nextRecordsUrl']);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	protected function run($query, $bindings, Closure $callback)
	{
        if (isset($this->beforeExecutingCallbacks)) {
            foreach ($this->beforeExecutingCallbacks as $beforeExecutingCallback) {
                $beforeExecutingCallback($query, $bindings, $this);
            }
        }

		$start = microtime(true);

		try {
			$result = $this->runQueryCallback($query, $bindings, $callback);
		} catch (QueryException $e) {
			$result = $this->handleQueryException(
				$e, $query, $bindings, $callback
			);
		}
		// Once we have run the query, we will calculate the time that it took to run and
		// then log the query, bindings, and execution time, so we will report them on
		// the event that the developer needs them. We'll log time in milliseconds.
		$this->logQuery(
			$query, $bindings, $this->getElapsedTime($start)
		);
		return $result;
	}

	private function prepare($query, $bindings)
	{
        $bindings = $this->prepareBindings($bindings);
        $query = Str::replaceArray('?', $bindings, $query);
        return $query;
	}

    /**
     * Prepare the query bindings for execution.
     *
     * @param  array  $bindings
     * @return array
     */
    public function prepareBindings(array $bindings)
    {
        $grammar = $this->getQueryGrammar();

        foreach ($bindings as $key => $value) {
            // We need to transform all instances of DateTimeInterface into the actual
            // date string. Each query grammar maintains its own date string format,
            // so we'll just ask the grammar for the format to get from the date.
            if ($value instanceof DateTimeInterface) {
                $bindings[$key] = $value->format($grammar->getDateFormat());
            } else if (is_bool($value)) {
                $bindings[$key] = $value ? 'TRUE' : 'FALSE';
            }
        }

        return $bindings;
    }

}
