<?php

namespace Lester\EloquentSalesForce\Database;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JsonExpression;
use Illuminate\Database\Query\Grammars\Grammar;
use Lester\EloquentSalesForce\ServiceProvider;

class SOQLGrammar extends Grammar
{
	/**
	 * The components that make up a select clause.
	 *
	 * @var array
	 */
	protected $selectComponents = [
		'aggregate',
		'columns',
		'joins',
		'from',
		'wheres',
		'groups',
		'havings',
		'orders',
		'limit',
		'offset',
		'lock',
	];

	/**
	 * Wrap a single string in keyword identifiers.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function wrapValue($value)
	{
		return $value === '*' ? $value : '`' . Str::replace('`', '``', $value) . '`';
	}

	protected function unWrapValue($value)
	{
		return Str::replace('`', '', $value);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @param  array  $where
	 * @return string
	 */
	protected function whereBasic(Builder $query, $where)
	{
		// allow for "false" values to not be wrapped.
		if (is_bool($where['value'])) {
			return $this->whereBoolean($query, $where);
		}

		if (Str::contains(strtolower($where['operator']), 'not like')) {
			return sprintf(
				'(not %s like %s)',
				$this->wrap($where['column']),
				$this->parameter($where['value'])
			);
		}
		return parent::whereBasic($query, $where);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function whereIn(Builder $query, $where)
	{
		if (empty($where['values'])) {
			// the below statement is invalid in SOQL
			// return '0 = 1';
			// since virtually every object in SalesForce has Id column then
			// compare that field to null which should always be false.
			return 'Id = null';
		}
		return parent::whereIn($query, $where);
	}

	/**
	 * Compile the "join" portions of the query.
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @param  array  $joins
	 * @return string
	 */
	protected function compileJoins(Builder $query, $joins)
	{
		return collect($joins)->map(function($join) use ($query) {
			$table = $join->table;

			$columns = ServiceProvider::objectFields($table, $join->columns ?: ['*']);
			$columns = collect($columns)->implode(',');

			$table_p = $this->unWrapValue($this->grammarPlural($table));

			$strQuery = "select $columns from {$table_p} ";
			Arr::forget($join->wheres, 0);

			if ($join->wheres) {
				$strQuery .= $this->compileWheres($join);
			}

			$strQuery = trim(", ($strQuery)");

			return $strQuery;
		})->implode(' ');
	}

	/**
	 * Format the where clause statements into one string.
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @param  array  $sql
	 * @return string
	 */
	protected function concatenateWhereClauses($query, $sql)
	{
		$conjunction = 'where';
		return $conjunction . ' ' . $this->removeLeadingBoolean(implode(' ', $sql));
	}

	/**
	 * Compile an aggregated select clause.
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @param  array  $aggregate
	 * @return string
	 */
	protected function compileAggregate(Builder $query, $aggregate)
	{
		$column = $this->columnize($aggregate['columns']);
		// If the query has a "distinct" constraint and we're not asking for all columns
		// we need to prepend "distinct" onto the column name so that the query takes
		// it into account when it performs the aggregating operations on the data.
		if ($query->distinct && $column !== '*') {
			$column = 'distinct ' . $column;
		}
		return 'select ' . $aggregate['function'] . '(' . $column . ') aggregate';
	}

	/**
	 * Modify plural to pluralize try to tries
	 *
	 * @param  [type] $string [description]
	 * @return [type]         [description]
	 */
	private function grammarPlural($string)
	{
		if (Str::endsWith($string, 'try')) {
			return Str::replaceLast('try', 'tries', $string);
		}

		return Str::plural($string);
	}

	/**
	 * Compile a "where not null" clause.
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @param  array  $where
	 * @return string
	 */
	protected function whereNotNull(Builder $query, $where)
	{
		return $this->wrap($where['column']) . ' <> null';
	}

	/**
	 * Define grammer for boolean where statements in SOQL
	 * @param  Builder $query [description]
	 * @param  [type]  $where [description]
	 * @return [type]         [description]
	 */
	protected function whereBoolean(Builder $query, $where)
	{
		if ($where['value'] === true) {
			return $this->wrap($where['column']) . $where['operator'] . 'TRUE';
		} else {
			return $this->wrap($where['column']) . $where['operator'] . 'FALSE';
		}
	}
}
