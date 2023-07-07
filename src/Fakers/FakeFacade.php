<?php

namespace Lester\EloquentSalesForce\Fakers;

use Lester\EloquentSalesForce\SalesForce;
use Lester\EloquentSalesForce\SalesForceObject;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert as PHPUnit;

class FakeFacade
{
    protected $instance;

    protected $authenticated;
    protected $commands = [];
    protected $history;

    private $fakeRecord;

    public function __construct(SalesForce $instance)
    {
        $this->instance = $instance;
        $this->history = collect([]);
    }

    public function sobjects($object, $arguments = null)
    {
        dump($object);
        $id = Str::contains($object, '/') ? Str::after($object, '/') : Str::random(18);
        $response = [
            'success' => true,
            'id' => $id,
        ];

        $method = $arguments['method'] ?? '';

        if ($arguments === null) {
            $this->commands[$method . $object] = $arguments;
            return $this->fakeFields();
        }

        $arguments['body']['Id'] = $id;
        //$arguments['body']['CreatedDate'] = now()->subDay();
        //$arguments['body']['LastModifiedDate'] = now()->subHours(12);
        $this->commands[$method . $object] = $arguments;

        return $response;
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

    private function fakeRecord()
    {
        return [
            'Id' => Str::random(18),
            'Email' => fake()->safeEmail(),
            'Phone' => fake()->e164PhoneNumber(),
            'Company' => fake()->company(),
            'CreatedDate' => fake()->dateTime(),
            'LastModifiedDate' => fake()->dateTime(),
        ];
    }

    private function fakeFields()
    {
        return [
            'Custom_Text_Field__c',
            'Email',
            'FirstName',
            'LastName',
            'Company',
            'Custom_Date_Field__c',
            'Id',
            'CreatedDate',
            'LastModifiedDate',
            'IsDeleted',
        ];
    }

    public function query()
    {
        return [
            'records' => [
                $this->fakeRecord()
            ]
        ];
    }

    public function log()
    {

    }

    public function assertModelCreated($object, $arguments = [])
    {
        PHPUnit::assertArrayHasKey('post' . $object, $this->commands);

        $requestParameters = $this->commands['post' . $object];
        $method = $requestParameters['method'];
        $body = $requestParameters['body'];

        PHPUnit::assertTrue($method == 'post' && $body == $arguments);

    }

    public function assertModelUpdatedOrCreated($object, $arguments = [])
    {
        dump($this->commands);

        PHPUnit::assertArrayHasKey('patch' . $object . '/', $this->commands);

        $requestParameters = $this->commands['patch' . $object . '/'];
        $method = $requestParameters['method'];
        $body = $requestParameters['body'];

        PHPUnit::assertTrue($method == 'patch' && $body == $arguments);
    }

    public function assertModelUpdated($object, $arguments = [])
    {
        $id = $arguments['Id'] ?? '';
        PHPUnit::assertArrayHasKey('patch' . "$object/$id", $this->commands);

        $requestParameters = $this->commands['patch' . "$object/$id"];
        $method = $requestParameters['method'];
        $body = $requestParameters['body'];

        PHPUnit::assertTrue($method == 'patch' && $body['Id'] == $arguments['Id']);
    }

    public function assertModelDeleted($object, $arguments)
    {
        $id = $arguments['Id'] ?? '';
        if (empty($this->commands['delete' . "$object/$id"])) {
            PHPUnit::assertTrue(false);
            return false;
        }

        $requestParameters = $this->commands['delete' . "$object/$id"];
        $method = $requestParameters['method'];

        PHPUnit::assertTrue($method == 'delete');
    }
}
