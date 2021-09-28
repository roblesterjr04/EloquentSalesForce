<?php

namespace Lester\EloquentSalesForce\Fakers;

use Lester\EloquentSalesForce\SObjects;
use PHPUnit\Framework\Assert as PHPUnit;

class SObjectsFake
{
    protected $instance;

    protected $authenticated;
    protected $commands = [];

    public function __construct(SObjects $instance)
    {
        $this->instance = $instance;
    }

    public function update(\Illuminate\Support\Collection $collection, $allOrNone = false)
    {
        self::authenticate();
        $this->commands['update'][] = $collection;
    }

    public function assertMassUpdate(\Illuminate\Support\Collection $collection)
    {

        $this->assertAuthenticated();

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
}
