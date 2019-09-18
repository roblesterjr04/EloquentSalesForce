<?php

namespace Lester\EloquentSalesForce\Tests;

use Illuminate\Support\Str;
use Lester\EloquentSalesForce\ServiceProvider;
use Lester\EloquentSalesForce\TestLead;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Config;
use Lester\EloquentSalesForce\Facades\SObjects;

class EloquentSalesForceTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    private $lead;

	/**
	 * @covers Lester\EloquentSalesForce\TestLead
	 * @covers Lester\EloquentSalesForce\Model
	 * @covers Lester\EloquentSalesForce\Model::create
	 * @covers Lester\EloquentSalesForce\Model::save
	 * @covers Lester\EloquentSalesForce\Database\SOQLBuilder
	 * @covers Lester\EloquentSalesForce\Database\SOQLConnection
	 * @covers Lester\EloquentSalesForce\Database\SOQLGrammar
	 * @covers Lester\EloquentSalesForce\Database\SOQLGrammar::whereBasic
	 */
    public function testObjectCreate()
    {
	    $email = strtolower(Str::random(10) . '@test.com');
	    $lead = TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => $email]);
	    $lead = TestLead::where('Email', $email)->first();

        $this->assertEquals($lead->Email, $email);

        try {
        	$lead->update(['Name' => 'test']);
        } catch (\Exception $e) {

        }

        $lead->delete();
    }

    /**
     * @covers Lester\EloquentSalesForce\Database\SOQLBuilder::insert
     * @covers Lester\EloquentSalesForce\SObjects::update
     */
    public function testObjectMass()
    {
        $collection = collect([]);
        for ($i = 0; $i < 3; $i++) {
            $email = strtolower(Str::random(10) . '@test.com');
            $collection->push(new TestLead(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => $email]));
        }
        $results = TestLead::insert($collection);

        $results = $results->map(function($lead) {
            $lead->Company = 'Test 2';
            return $lead;
        });

        $this->assertCount(3, $results);

        SObjects::update($results);

        $lead = TestLead::find($results->first()->Id);

        $this->assertEquals($lead->Company, 'Test 2');

        foreach ($results as $lead) {
            $lead->delete();
        }
    }

    /*
	 * @covers Lester\EloquentSalesForce\Model
	 * @covers Lester\EloquentSalesForce\Model::save
	 * @covers Lester\EloquentSalesForce\Model::update
	 */
    public function testObjectUpdate()
    {
	    $email = strtolower(Str::random(10) . '@test.com');
	    $lead = TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => $email]);
	    $lead->update(['FirstName' => 'Robert']);
        $lead = TestLead::where('Email', $email)->first();

        $this->assertEquals($lead->FirstName, 'Robert');
        $lead->delete();
    }

    /**
	 * @covers Lester\EloquentSalesForce\Database\SOQLGrammar
	 * @covers Lester\EloquentSalesForce\Database\SOQLGrammar::whereBasic
	 */
    public function testWhereBasic()
    {
	    $leads = TestLead::where('FirstName', 'not like', 'xxxxxxxxxxxxx')->limit(5)->get();
	    $this->assertCount(5, $leads);
    }

    /*
     * @covers Lester\EloquentSalesForce\Database\SOQLGrammar
     * @covers Lester\EloquentSalesForce\Database\SOQLGrammar::whereBoolean
     */
    public function testWhereBoolean()
    {
        $leads = TestLead::where('DoNotCall', false)->limit(5)->get();
        $this->assertCount(5, $leads);
    }

    /*
     * @covers Lester\EloquentSalesForce\Database\SOQLGrammar
     * @covers Lester\EloquentSalesForce\Database\SOQLGrammar::whereIn
     */
    public function testWhereIn()
    {
        $leads = TestLead::whereIn('FirstName', ['Kathy', 'Betty'])->get();
        $this->assertTrue($leads->count() >= 2);
    }

    /**
	 * @covers Lester\EloquentSalesForce\Database\SOQLGrammar
	 * @covers Lester\EloquentSalesForce\Database\SOQLGrammar::whereDate
	 */
    public function testWhereDate()
    {
	    $leads = TestLead::where('CreatedDate', '>=', '2010-10-01T12:00:00.000+00:00')->limit(5)->get();
	    $this->assertCount(5, $leads);
    }

    /*
	 * @covers Lester\EloquentSalesForce\Model
	 * @covers Lester\EloquentSalesForce\Model::delete
	 */
    public function testObjectDelete()
    {
	    $email = strtolower(Str::random(10) . '@test.com');
	    $lead = TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => $email]);
	    $lead->delete();
	    $lead = TestLead::where('Email', $email)->get();
        $this->assertCount(0, $lead);
    }

    /*
	 * @covers Lester\EloquentSalesForce\Model
	 * @covers Lester\EloquentSalesForce\Model::belongsTo
	 * @covers Lester\EloquentSalesForce\Model::hasMany
	 * @covers Lester\EloquentSalesForce\Database\SOQLHasMany
	 * @covers Lester\EloquentSalesForce\Database\SOQLHasOneOrMany
	 */
    public function testRelationships()
    {
	    $email = strtolower(Str::random(10) . '@test.com');
	    $lead = TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => $email]);

	    $task = $lead->tasks()->create(['Subject' => 'TestTask']);

	    $lead = $task->lead;

	    $this->assertCount(1, $lead->tasks);

	    $task->delete();

	    $lead->delete();

    }

    /*
	 * @covers Lester\EloquentSalesForce\Model
	 * @covers Lester\EloquentSalesForce\Database\SOQLGrammar::compileJoins
	 */
    public function testJoins()
    {
	    $email = strtolower(Str::random(10) . '@test.com');
	    $lead = TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => $email]);

	    $task = $lead->tasks()->create(['Subject' => 'TestTask']);

	    $joined = TestLead::join('Task', 'WhoId')->where('Email', $email)->first();

	    $this->assertCount(1, $joined->Tasks['records']);

	    $task->delete();

	    $lead->delete();
    }

    /*
	 * @covers Lester\EloquentSalesForce\Model
	 * @covers Lester\EloquentSalesForce\Database\SOQLBuilder::paginate
	 */
    public function testPaginate()
    {
	    $pageone = TestLead::paginate(3);

	    $this->assertCount(3, $pageone);

    }

    /*
     * @covers Lester\EloquentSalesForce\Model::getPicklistValues
     * @covers Lester\EloquentSalesForce\SObjects::getPicklistValues
     */
    public function testGetPicklistValues()
    {
        $email = strtolower(Str::random(10) . '@test.com');
        $lead = TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => $email]);

        $statuses = $lead->getPicklistValues('Status');

        $this->assertTrue($statuses->count() > 0);
        $this->assertTrue($statuses !== null);

        $lead->delete();
    }

    public function setUp(): void
	{
		parent::setUp();


		if (getenv('SCRUT_TEST')) {
			config([
				'forrest' => [
					'authentication' => 'UserPassword',
					/*
				     * These are optional authentication parameters that can be specified for the WebServer flow.
				     * https://help.salesforce.com/apex/HTViewHelpDoc?id=remoteaccess_oauth_web_server_flow.htm&language=en_US
				     */
				    'parameters'     => [
				        'display'   => '',
				        'immediate' => false,
				        'state'     => '',
				        'scope'     => '',
				        'prompt'    => '',
				    ],

				    /*
				     * Default settings for resource requests.
				     * Format can be 'json', 'xml' or 'none'
				     * Compression can be set to 'gzip' or 'deflate'
				     */
				    'defaults'       => [
				        'method'          => 'get',
				        'format'          => 'json',
				        'compression'     => false,
				        'compressionType' => 'gzip',
				    ],

				    /*
				     * Where do you want to store access tokens fetched from Salesforce
				     */
				    'storage'        => [
				        'type'          => 'cache', // 'session' or 'cache' are the two options
				        'path'          => 'forrest_', // unique storage path to avoid collisions
				        'expire_in'     => 20, // number of minutes to expire cache/session
				        'store_forever' => false, // never expire cache/session
				    ],

				    /*
				     * If you'd like to specify an API version manually it can be done here.
				     * Format looks like '32.0'
				     */
				    'version'        => '',

				    /*
				     * Optional (and not recommended) if you need to override the instance_url returned from Saleforce
				     */
				    'instanceURL'    => '',

				    /*
				     * Language
				     */
				    'language'       => 'en_US',

				    'credentials' => [
						'driver' => 'soql',
					    'database' => null,
						'consumerKey'    => getenv('CONSUMER_KEY'),
				        'consumerSecret' => getenv('CONSUMER_SECRET'),
				        'callbackURI'    => getenv('CALLBACK_URI'),
				        'loginURL'       => getenv('LOGIN_URL'),

				        // Only required for UserPassword authentication:
				        'username'       => getenv('USERNAME'),
				        // Security token might need to be ammended to password unless IP Address is whitelisted
				        'password'       => getenv('PASSWORD')
					]
				]
			]);
		}

		config([
			'app.key' => 'base64:WRAf0EDpFqwpbS829xKy2MGEkcJxIEmMrwFIZbGxIqE=',
			'cache.stores.file.path' => __DIR__ . '/cache',
			'cache.default' => 'file',
		]);

	}

	/**
	 * Creates the application.
	 *
	 * @return \Illuminate\Foundation\Application
	 */

	public function createApplication()
	{
		if (getenv('SCRUT_TEST')) return parent::createApplication();

		$app = require __DIR__.'/../../../bootstrap/app.php';

		$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

		$app->loadEnvironmentFrom('.env');

		return $app;
	}

    protected function tearDown(): void
    {
        \Artisan::call('cache:clear');

        parent::tearDown();
    }
}
