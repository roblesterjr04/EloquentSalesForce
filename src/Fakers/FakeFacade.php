<?php

namespace Lester\EloquentSalesForce\Fakers;

use Lester\EloquentSalesForce\SalesForce;
use Lester\EloquentSalesForce\SalesForceObject;
use PHPUnit\Framework\Assert as PHPUnit;

class FakeFacade
{
    protected $instance;

    protected $authenticated;
    protected $commands = [];
    protected $history;

    public function __construct(SalesForce $instance)
    {
        $this->instance = $instance;
        $this->history = collect([]);
    }

    public function sobjects($object, $arguments = [])
    {
        $this->commands[$object] = $arguments;

        return new SalesForceObject();
    }

    public function assertRequestSent($method, $arguments)
    {

    }

    public function queryHistory()
    {
        return $this->history;
    }

    public function assertHistoryAltered()
    {
        PHPUnit::assertTrue($this->history->count() > 0);
    }

    public function update(\Illuminate\Support\Collection $collection, $allOrNone = false)
    {
        self::authenticate();
        $this->commands['update'][] = $collection;
    }

    public function assertMassUpdate(\Illuminate\Support\Collection $collection)
    {

        //$this->assertAuthenticated();

        PHPUnit::assertTrue($this->updated($collection));

    }

    protected function updated(\Illuminate\Support\Collection $collection)
    {
        if (!isset($this->commands['update'])) return false;
        foreach ($this->commands['update'] as $command) {
            return $command == $collection;
        }
    }

    public function authenticate()
    {
        $this->authenticated = true;
    }

    public function assertAuthenticated()
    {
        PHPUnit::assertTrue($this->authenticated);
    }

    public function isSalesForceId()
    {
        return true;
    }

    public function queryAll()
    {
        return $this->query();
    }

    public function query()
    {
        return [
            'records' => [

            ]
        ];
    }

    public function log()
    {

    }

    public function assertModelCreated($object, $arguments = [])
    {
        if (empty($this->commands[$object])) {
            PHPUnit::assertTrue(false);
            return false;
        }

        $requestParameters = $this->commands[$object];
        $method = $requestParameters['method'];
        $body = $requestParameters['body'];

        PHPUnit::assertTrue($method == 'post' && $body->toArray() == $arguments);

    }
}
