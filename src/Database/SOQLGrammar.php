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
	    return $value === '*' ? $value : '`'.str_replace('`', '``', $value).'`';
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
     * Compile the "join" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $joins
     * @return string
     */
    protected function compileJoins(Builder $query, $joins)
    {
	    return collect($joins)->map(function ($join) use ($query) {
            $table = $join->table;
            
            $columns = ServiceProvider::objectFields($table, ['*']);
            $columns = collect($columns)->implode(',');
            
            $table_p = str_plural($this->wrapTable($table));
            
            return trim(", (select $columns from {$table_p})");
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
        return $conjunction.' '.$this->removeLeadingBoolean(implode(' ', $sql));
    }
}