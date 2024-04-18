<?php

namespace Lester\EloquentSalesForce\Database;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\JsonExpression;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lester\EloquentSalesForce\Facades\SObjects;
use Lester\EloquentSalesForce\ServiceProvider;

class SOQLGrammar extends Grammar
{
    protected $model;

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
        'for',
	];

    public function getModel()
    {
        return $this->model;
    }

    public function setModel($model)
    {
        $this->model = $model;
        return $model;
    }

	/**
	 * Wrap a single string in keyword identifiers.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function wrapValue($value)
	{
        return $value;
	}

	protected function unWrapValue($value)
	{
		return str_replace('`', '', $value);
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
        if ($this->isDate($where['column'])) {
            return $this->whereDate($query, $where);
        }
        // allow for "false" values to not be wrapped.
		if (is_bool($where['value'])) {
            return $this->whereBoolean($query, $where);
		}

        // allow for literal string values
        if ($this->checkStringLiteral($where['value'])) {
            return $this->whereLiteral($query, $where);
        }

        if (Str::contains(strtolower($where['operator']), 'not like')) {
			return sprintf(
				'(not %s like %s)',
				$this->wrap($where['column']),
				$this->parameter($where['value'])
			);
		}

        return parent::whereBasic($query, $where);

        //return $this->wrap($where['column']) . $where['operator'] . $this->parameter($where['value']);
	}

    protected function whereDate(Builder $query, $where)
    {
        return $this->wrap($where['column']) . $where['operator'] . '?';
    }

    /**
     * Compile the "limit" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $limit
     * @return string
     */
    protected function compileLimit(Builder $query, $limit)
    {
        return 'limit '.(int) $limit;
    }

    protected function isDate($column)
    {
        return in_array($column, $this->model->getDates());
    }

    /**
     * Get the appropriate query parameter place-holder for a value.
     *
     * @param  mixed   $value
     * @return string
     */
    public function parameter($value, $column = null)
    {
        if (is_int($value)) {
            return '?';
        }
        if (SObjects::isSalesForceId($value) || is_string($value)) {
            return "'?'";
        }

        return $this->isExpression($value) ? $this->getValue($value) : '?';
    }

	/**
	 * {@inheritDoc}
	 */
	protected function whereIn(Builder $query, $where)
	{
        if (! empty($where['values'])) {
            //dd($this->wrap($where['column']).' in ('.$this->parameterize($where['values']).')');
            return $this->wrap($where['column']).' in ('.$this->parameterize($where['values']).')';
        }

        return 'Id = null';
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
		// If the query has a "distinct" constraint, and we're not asking for all columns,
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
		return $this->wrap($where['column']) . ' <> NULL';
	}

	protected function whereNull(Builder $query, $where)
	{
		return $this->wrap($where['column']) . ' = NULL';
	}

	/**
	 * Define grammer for boolean where statements in SOQL
	 * @param  Builder $query [description]
	 * @param  [type]  $where [description]
	 * @return [type]         [description]
	 */
	protected function whereBoolean(Builder $query, $where)
	{
		return $this->wrap($where['column']) . ' = ?';
	}

    /**
     * @param Builder $query The query builder
     * @param array $where The where array
     * @return string
     */
    protected function whereLiteral(Builder $query, $where) {
        return "{$this->wrap($where['column'])} {$where['operator']} {$where['value']}";
    }

    /**
     * Check if the $string is a SOSQL String Literal
     * List taken from: https://developer.salesforce.com/docs/atlas.en-us.soql_sosl.meta/soql_sosl/sforce_api_calls_soql_select_dateformats.htm
     * @param $string
     * @return bool
     */
    protected function checkStringLiteral($string) {
        // some literals use ':' in them, removing before checking
        if (Str::contains($string, ":")) {
            $string = explode(':', $string)[0];
        }
        // check against the array of literals
        return in_array($string, [
            "YESTERDAY",
            "TODAY",
            "TOMORROW",
            "LAST_WEEK",
            "THIS_WEEK",
            "NEXT_WEEK",
            "LAST_MONTH",
            "THIS_MONTH",
            "NEXT_MONTH",
            "LAST_90_DAYS",
            "NEXT_90_DAYS",
            "LAST_N_DAYS",
            "NEXT_N_DAYS",
            "NEXT_N_WEEKS",
            "LAST_N_WEEKS",
            "NEXT_N_MONTHS",
            "LAST_N_MONTHS",
            "THIS_QUARTER",
            "LAST_QUARTER",
            "NEXT_QUARTER",
            "NEXT_N_QUARTERS",
            "LAST_N_QUARTERS",
            "THIS_YEAR",
            "LAST_YEAR",
            "NEXT_YEAR",
            "NEXT_N_YEARS",
            "LAST_N_YEARS",
            "THIS_FISCAL_QUARTER",
            "LAST_FISCAL_QUARTER",
            "NEXT_FISCAL_QUARTER",
            "NEXT_N_FISCAL_​QUARTERS",
            "LAST_N_FISCAL_​QUARTERS",
            "THIS_FISCAL_YEAR",
            "LAST_FISCAL_YEAR",
            "NEXT_FISCAL_YEAR",
            "NEXT_N_FISCAL_​YEARS",
            "LAST_N_FISCAL_​YEARS",
        ]);
    }

    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    public function getDateFormat()
    {
        return 'Y-m-d\TH:i:s\Z';
    }

    public function compileLock($query, $value)
    {
        return 'FOR UPDATE';
    }

}
