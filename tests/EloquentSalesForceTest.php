<?php

namespace Lester\EloquentSalesForce\Tests;

use Lester\EloquentSalesForce\ServiceProvider;
use Lester\EloquentSalesForce\SalesForceObject;
use Lester\EloquentSalesForce\TestLead;
use Lester\EloquentSalesForce\TestModel;
use Lester\EloquentSalesForce\TestTask;
use Lester\EloquentSalesForce\TestUser;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Config;
use Lester\EloquentSalesForce\Facades\SObjects;
use Lester\EloquentSalesForce\Database\SOQLBatch;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Error\Notice;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Forrest;
use GuzzleHttp\Client;
use Carbon\Carbon;

class EloquentSalesForceTest extends TestCase
{

    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    private $lead;

    public function testWebAuthentication()
    {
        config([
            'eloquent_sf.forrest.authentication' => 'WebServer',
            'eloquent_sf.forrest.credentials' => [
                'driver' => 'soql',
                'database' => null,
                'consumerKey'    => getenv('WF_CONSUMER_KEY'),
                'consumerSecret' => getenv('WF_CONSUMER_SECRET'),
                'callbackURI'    => url('/login/salesforce/callback'),
                'loginURL'       => getenv('LOGIN_URL'),
            ],
            'eloquent_sf.forrest.parameters' => [
                'display'   => 'page',
                'immediate' => false,
                'state'     => '',
                'scope'     => 'full',
                'prompt'    => 'select_account',
            ],
        ]);

        config([
            'forrest' => config('eloquent_sf.forrest'),
        ]);

        \Artisan::call('cache:clear');

        $response = $this->get('/login/salesforce');

        $response->assertOk();
    }

    public function testSimpleObject()
    {
        $lead = TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => 'test@test.com']);

        $test = SalesForceObject::select('Id')
            ->from('Lead')
            ->where('Email', 'test@test.com')
            ->first();

        $this->assertEquals($lead->Id, $test->Id);
    }

    public function testSyncsModelsToSalesforce()
    {
        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('salesforce')->nullable();
            $table->string('firstName');
            $table->string('lastName');
            $table->string('company');
            $table->timestamps();
        });

        $test = TestModel::create([
            'email' => 'test@test.com',
            'firstName' => 'Rob',
            'lastName' => 'Test',
            'company' => 'Test Company',
        ]);

        $this->assertCount(1, TestModel::get());
        $this->assertCount(1, TestLead::where('Email', 'test@test.com')->get());

        $test->update([
            'email' => 'test2@test.com'
        ]);

        $object = TestLead::where('Email', 'test2@test.com')->first();

        $this->assertEquals('test2@test.com', $test->fresh()->email);
        $this->assertEquals('test2@test.com', $object->Email);

        config([
            'eloquent_sf.syncTwoWay' => true,
            'eloquent_sf.syncPriority' => 'local',
        ]);

        sleep(2);

        $object->update([
            'Email' => 'test3@test.com'
        ]);

        $test->syncWithSalesforce();

        $test->refresh();

        $this->assertEquals('test3@test.com', $test->email);

        $test4 = TestModel::create([
            'email' => 'test4@test.com',
            'firstName' => 'Rob',
            'lastName' => 'Test',
            'company' => 'Test Company',
        ]);

        $this->assertNotNull(TestLead::where('Email', 'test4@test.com')->first());

        $object = TestLead::where('Email', 'test4@test.com')->first();

        $object->update([
            'Email' => 'test5@test.com'
        ]);

        $test4->email = 'test6@test.com';

        $test4->syncWithSalesforce();

        $this->assertEquals($test4->email, $object->refresh()->Email);

    }

    public function testSyncCommand()
    {
        config([
            'eloquent_sf.syncTwoWay' => true,
            'eloquent_sf.syncPriority' => 'local',
            'eloquent_sf.syncTwoWayModels' => [
                'Lester\EloquentSalesForce\TestModel',
            ],
        ]);

        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('salesforce')->nullable();
            $table->string('firstName');
            $table->string('lastName');
            $table->string('company');
            $table->timestamps();
        });

        $test = TestModel::create([
            'email' => 'test@test.com',
            'firstName' => 'Rob',
            'lastName' => 'Test',
            'company' => 'Test Company',
        ]);

        $object = TestLead::find($test->refresh()->salesforce)->first();

        $this->assertNotNull($object);

        sleep(2);

        $object->update([
            'Email' => 'test2@test.com'
        ]);

        \Artisan::call('db:sync');

        $this->assertEquals($object->Email, $test->refresh()->email);

        $response = $this->post('api/syncObject/' . $object->Id);

        $response->assertStatus(200);
    }

    public function testObjectCreate()
    {
	    $email = strtolower(Str::random(10) . '@test.com');
	    $lead = TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => $email]);

        $lead = TestLead::where('Email', $email)->first();

        $this->assertNotNull($lead);
        $this->assertEquals($lead->Email, $email);
        $this->assertEquals($lead->Phone, '1231231234');

        TestLead::truncate();

        $lead = TestLead::firstOrCreate(
            ['Email' => $email],
            ['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test']
        );

        $this->assertTrue($lead->wasRecentlyCreated);
    }

    /**
     * @covers Lester\EloquentSalesForce\Database\SOQLBuilder::insert
     * @covers Lester\EloquentSalesForce\SObjects::update
     */
    public function testObjectMass()
    {
        $collection = collect([]);
        for ($i = 0; $i < 10; $i++) {
            $email = strtolower(Str::random(10) . '@test.com');
            $collection->push(new TestLead(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => $email]));
        }
        $results = TestLead::insert($collection);

        $ids = $results->pluck('Id');
        $results = TestLead::whereIn('Id', $ids)->get();

        $this->assertCount(10, $results);

        $results = $results->slice(0, 3)->map(function($lead) {
            $lead->Company = 'Test 2';
            return $lead;
        });

        SObjects::update($results);

        $lead = TestLead::find($results->first()->Id);

        $this->assertEquals($lead->Company, 'Test 2');

        $batch = new SOQLBatch();

        $batch->query(TestLead::where('Company', 'Test'));

        $this->expectException(\Exception::class);
        $batch->push(TestLead::where('Company', 'Test 2'));

        $results = $batch->run();

        $this->assertCount(7, $results->get('TestLead_0'));
        $this->assertCount(3, $results->get('TestLead_1'));

        TestLead::truncate();

        $this->assertCount(0, TestLead::all());
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

        $lead = TestLead::updateOrCreate(
            ['Email' => $email],
            ['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test']
        );

        $this->assertEquals($lead->FirstName, 'Rob');
        $this->assertFalse($lead->wasRecentlyCreated);

        $lead->delete();

        $lead = TestLead::updateOrCreate(
            ['Email' => $email],
            ['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test']
        );

        $this->assertTrue($lead->wasRecentlyCreated);
    }

    /**
	 * @covers Lester\EloquentSalesForce\Database\SOQLGrammar
	 * @covers Lester\EloquentSalesForce\Database\SOQLGrammar::whereBasic
	 */
    public function testWhereBasic()
    {
        TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => 'test@test.com']);

	    $leads = TestLead::where('FirstName', 'not like', 'xxxxxxxxxxxxx')->get();
	    $this->assertTrue($leads->count() > 0);

        $this->assertInstanceOf(Carbon::class, $leads->first()->CreatedDate);

        $int = 123;
        $lead = TestLead::select('Id', 'CreatedDate')->where('LastName', 'August')->where('Email', (string)$int)->first();

        $this->assertNull($lead);

        $user = TestUser::first();
        $this->assertNotNull($user);
    }

    /*
     * @covers Lester\EloquentSalesForce\Database\SOQLGrammar
     * @covers Lester\EloquentSalesForce\Database\SOQLGrammar::whereBoolean
     */
    public function testWhereBoolean()
    {
        TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => 'test@test.com']);

        $leads = TestLead::where('DoNotCall', false)->limit(5)->get();
        $this->assertTrue($leads->count() > 0);
    }

    /*
     * @covers Lester\EloquentSalesForce\Database\SOQLGrammar
     * @covers Lester\EloquentSalesForce\Database\SOQLGrammar::whereIn
     */
    public function testWhereIn()
    {

        TestLead::insert(collect([
            new TestLead([
                'Email' => strtolower(Str::random(10) . '@test.com'),
                'FirstName' => 'Kathy',
                'LastName' => 'Test',
                'Company' => 'TestCo',
            ]),
            new TestLead([
                'Email' => strtolower(Str::random(10) . '@test.com'),
                'FirstName' => 'Betty',
                'LastName' => 'Test',
                'Company' => 'TestCo',
            ]),
        ]));

        $leads = TestLead::whereIn('FirstName', ['Kathy', 'Betty'])->get();
        $this->assertCount(2, $leads);
    }

    /**
	 * @covers Lester\EloquentSalesForce\Database\SOQLGrammar
	 * @covers Lester\EloquentSalesForce\Database\SOQLGrammar::whereDate
	 */
    public function testWhereDate()
    {
        TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => 'test@test.com']);

	    $leads = TestLead::where('CreatedDate', '>=', '2010-10-01T12:00:00.000+00:00')->limit(5)->get();
	    $this->assertTrue($leads->count() > 0);
    }

    public function testOrWhere()
    {
        TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => 'test@test.com']);

        $leads = TestLead::where('FirstName', 'not like', 'xxxxxxxxxxxxx')->orWhere('Owner.UserRole.Name', 'like', 'yyyyyyyyyy%')->limit(5)->get();
        $this->assertTrue($leads->count() > 0);
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
        TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => 'test@test.com']);

	    $pageone = TestLead::paginate(3);

	    $this->assertTrue($pageone->count() > 0);

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

        $this->assertNotNull($statuses);
        $this->assertTrue($statuses->count() > 0);

        $statuses = TestLead::getPicklistValues('Status');

        $this->assertNotNull($statuses);
        $this->assertTrue($statuses->count() > 0);

    }

    public function testMassDelete()
    {
        $email = strtolower(Str::random(10) . '@test.com');
        $lead = TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => $email]);

        TestLead::select('Id')->delete();

        $this->assertCount(0, TestLead::all());

    }

    /**
     * @covers Lester\EloquentSalesForce\SObjects::object
     * @covers Lester\EloquentSalesForce\SObjects::convert
     * @covers Lester\EloquentSalesForce\SObjects::is_uppercase
     * @covers Lester\EloquentSalesForce\SObjects::describe
     */
    public function testFacadeFuncs()
    {
        //SObjects::authenticate();

        TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => 'test@test.com']);

        $query = \Forrest::query('select Id from Lead limit 1');

        $object = SObjects::object($query['records'][0]);
        $this->assertInstanceOf('Lester\EloquentSalesForce\SalesForceObject', $object);

        $testLeadFields = SObjects::describe('Product2');

        $this->assertNotNull($testLeadFields);

        $convertedId = SObjects::convert('5003000000D8cuI');
        $this->assertEquals('5003000000D8cuIAAR', $convertedId);

        TestLead::truncate();

    }

    public function testSoftDeletes()
    {
        $lead = TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => 'test@test.com']);

        $allLeads = TestLead::get();
        $this->assertCount(1, $allLeads);

        $this->assertFalse($lead->trashed());

        $lead->delete();

        $allLeads = TestLead::get();
        $this->assertCount(0, $allLeads);

        $allLeads = TestLead::withTrashed()->get();
            //dd($allLeads);
        $this->assertTrue($allLeads->count() > 0);

        $deleted = TestLead::withTrashed()->where('IsDeleted', TRUE)->first();

        $this->assertTrue($deleted->trashed());

        $this->expectException(\Exception::class);
        $deleted->restore();

    }

    public function testReplicate()
    {
        $lead = TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => 'test@test.com']);

        $leadTwo = $lead->replicate()->fill([
            'Email' => 'test2@test.com'
        ])->save();

        $this->assertTrue($leadTwo->wasRecentlyCreated);

    }

    public function setUp(): void
	{
		parent::setUp();


		//if (getenv('SCRUT_TEST')) {
			config([
                'logging' => [
                    'default' => 'single',
                    'channels' => [
                        'single' => [
                            'driver' => 'single',
                            'path' => storage_path('logs/laravel.log'),
                            'level' => 'error',
                        ],
                    ]
                ],
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
		//}

		config([
			'app.key' => 'base64:WRAf0EDpFqwpbS829xKy2MGEkcJxIEmMrwFIZbGxIqE=',
			'cache.stores.file.path' => __DIR__ . '/cache',
			'cache.default' => 'file',
		]);



        TestLead::truncate();
        TestTask::truncate();

	}

	/**
	 * Creates the application.
	 *
	 * @return \Illuminate\Foundation\Application
	 */

	public function createApplication()
	{
		if (getenv('GIT_TEST')) return parent::createApplication();

        $env = file_get_contents(__DIR__.'/../.env');

        $lines = explode("\n", $env);

        foreach ($lines as $line) {
            if ($line) putenv(trim($line));
        }

		return parent::createApplication();
	}

    protected function tearDown(): void
    {
        \Artisan::call('cache:clear');

        //TestLead::truncate();

        parent::tearDown();
    }

}
