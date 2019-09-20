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

class SOQLConnection extends Connection
{
	/**
	 * {@inheritDoc}
	 */
	public function select($query, $bindings = [], $useReadPdo = true)
	{
		return $this->run($query, $bindings, function($query, $bindings) {
			if ($this->pretending()) {
				return [];
			}

			$statement = $this->prepare($query, $bindings);

			/** @scrutinizer ignore-call */
			$result = SObjects::query($statement);
			$records = $result['records'];

			while (isset($result['nextRecordsUrl'])) {
				$result = SObjects::next($result['nextRecordsUrl']);
				if (isset($result['records'])) {
					$records = \array_merge($records, $result['records']);
				}
			}

			return $records;
		});
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
		$start = microtime(true);

		try {
			$result = $this->runQueryCallback($query, $bindings, $callback);
		} catch (QueryException $e) {
			$result = $this->handleQueryException(
				$e, $query, $bindings, $callback
			);
		}
		// Once we have run the query we will calculate the time that it took to run and
		// then log the query, bindings, and execution time so we will report them on
		// the event that the developer needs them. We'll log time in milliseconds.
		$this->logQuery(
			$query, $bindings, $this->getElapsedTime($start)
		);
		return $result;
	}

	private function prepare($query, $bindings)
	{
		$query = Str::replace('`', '', $query);
		$bindings = array_map(function($item) {
		try {
			if (Carbon::parse($item) !== false &&
				!$this->isSalesForceId($item)) {
					return $item;
				}
			} catch (\Exception $e) {
				return "'$item'";
			}
			return "'$item'";
		}, $bindings);

		$query = Str::replace_array('?', $bindings, $query);
		return $query;
	}

	/**
	 * Based on characters and length of $str, determine if it appears to be a
	 * SalesForce ID.
	 *
	 * @param string $str String to test
	 *
	 * @return bool
	 */
	private function isSalesForceId($str)
	{
		return boolval(\preg_match('/^[0-9a-zA-Z]{15,18}$/', $str));
	}
}
