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
use Illuminate\Foundation\Testing\WithFaker;
use Forrest;
use GuzzleHttp\Client;
use Carbon\Carbon;
use Lester\EloquentSalesForce\Exceptions\MalformedQueryException;
use Lester\EloquentSalesForce\Exceptions\RestAPIException;

class EloquentSalesForceTest extends TestCase
{
    use WithFaker;

    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    private $lead;

    public function testStringType()
    {
        $lead = TestLead::create([
            'FirstName' => 'Rob',
            'LastName' => 'Lester',
            'Company' => 'Test',
            'Email' => 'test@test.com',
            'Custom_Text_Field__c' => '009',
        ]);

        $lead->refresh();

        $this->assertEquals('009', $lead->Custom_Text_Field__c);
    }

    public function testForUpdate()
    {
        $lead = TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => 'test@test.com']);

        $this->expectException(RestAPIException::class);
        TestLead::where('Id', 'test')->get();

        $this->expectException(MalformedQueryException::class);
        TestLead::select('Id')->limit(1)->lockForUpdate()->get();



    }

    public function testFacadeVersions()
    {
        $this->assertFalse(cache()->has('sfdc_versions'));
        $versions = SObjects::versions();
        $this->assertTrue(cache()->has('sfdc_versions'));
    }

    public function testDirtyAndChanges()
    {
        $email = strtolower(Str::random(10) . '@test.com');
        $lead = TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => $email]);

        $this->assertFalse($lead->isDirty());

        $lead->FirstName = 'Testing';

        $this->assertTrue($lead->isDirty());

        $this->assertCount(1, $lead->getDirty());

        $lead->save();

        $this->assertCount(1, $lead->getChanges());
    }

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
            $table->bigIncrements('id');
            $table->string('email')->unique();
            $table->string('salesforce')->nullable();
            $table->string('name');
            $table->string('company');
            $table->timestamps();
        });

        $test = TestModel::create([
            'email' => 'test@test.com',
            'name' => 'Rob Lester',
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
            'name' => 'Rob Lester',
            'company' => 'Test Company',
        ]);

        $this->assertNotNull(TestLead::where('Email', 'test4@test.com')->first());
        $this->assertEquals('Rob', TestLead::where('Email', 'test4@test.com')->first()->FirstName);

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
            $table->bigIncrements('id');
            $table->string('email')->unique();
            $table->string('salesforce')->nullable();
            $table->string('name');
            $table->string('company');
            $table->timestamps();
        });

        $test = TestModel::create([
            'email' => 'test@test.com',
            'name' => 'Rob Test',
            'company' => 'Test Company',
        ]);

        sleep(2);

        $object = TestLead::find($test->refresh()->salesforce);

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
        //$this->assertEquals($lead->Phone, '1231231234');
        $this->assertEquals($lead->Company, 'Test Company');

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
        TestLead::create([
            'FirstName' => 'Rob',
            'LastName' => 'Lester',
            'Company' => 'Test',
            'Email' => 'test@test.com',
            'Custom_Date_Field__c' => now()->subDay(),
        ]);

        /*$query = TestLead::query()
            ->where('CreatedDate', '>=', today())
            ->where('Email', 'test@test.com')
            ->where('Id', 'fffff')
            ->toSql();

        dd($query);*/

	    $leads = TestLead::where('CreatedDate', '>=', today())->where('Id' , '<>', '0030000000Db7DuAAJ')->get();
	    $this->assertTrue($leads->count() > 0);

        $lead = TestLead::where('Email', 'test@test.com')->first();

        $now = now();
        $lead->update([
            'Custom_Date_Field__c' => $now,
            'Company' => 'Test Co',
        ]);
        $lead = TestLead::select('Custom_Date_Field__c', 'Id')
            ->where('Email', 'test@test.com')
            ->whereDate('Custom_Date_Field__c', '>=', today())
            ->first();

        TestLead::whereTime('CreatedDate', now())->get();

        $this->assertTrue($now->startOfDay()->eq($lead->Custom_Date_Field__c));
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

        //dd(SObjects::queryHistory());

    }

    //TODO
    /*public function testExists()
    {
        $email = strtolower(Str::random(10) . '@test.com');
        $lead = TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => $email]);

        $exists = TestLead::where('LastName', 'Lester')->exists();

        $this->assertTrue($exists);

        $lead->delete();
    }*/

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

    public function testQueryLiterals()
    {
        $lead = TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => 'test@test.com']);

        $results = TestLead::onlyTrashed()->where('CreatedDate', 'THIS_WEEK')->get();

        $this->assertTrue($results->count() > 0);
    }

    public function testSoftDeletes()
    {
        $lead = TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => 'test@test.com']);
        $id = $lead->Id;

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
        $idFind = TestLead::onlyTrashed()->where('Id', $id)->first();



        $this->assertTrue($deleted->trashed());
        $this->assertEquals($idFind->Id, $id);

        $this->expectException(\Exception::class);
        $deleted->restore();

    }

    public function testCursorWithSoftDeletes()
    {
        $leadOne = TestLead::create(['FirstName' => $this->faker->firstName(), 'LastName' => $this->faker->lastName(), 'Company' => $this->faker->company(), 'Email' => $this->faker->email()]);

        $leadTwo = TestLead::create(['FirstName' => $this->faker->firstName(), 'LastName' => $this->faker->lastName(), 'Company' => $this->faker->company(), 'Email' => $this->faker->email()]);


        $leadsByGetCount = TestLead::get()->count();
        $leadsByCursorCount = TestLead::cursor()->count();
        $this->assertEquals($leadsByGetCount, $leadsByCursorCount);

        $allLeadsByGetCount = TestLead::withTrashed()->get()->count();
        $allLeadsByCursorCount = TestLead::withTrashed()->cursor()->count();
        $this->assertEquals($allLeadsByGetCount, $allLeadsByCursorCount);

        $onlyTrashedLeadsByGetCount = TestLead::onlyTrashed()->get()->count();
        $onlyTrashedLeadsByCursorCount = TestLead::onlyTrashed()->cursor()->count();
        $this->assertEquals($onlyTrashedLeadsByGetCount, $onlyTrashedLeadsByCursorCount);


        $leadOne->delete();

        $newLeadsByCursorCount = TestLead::cursor()->count();
        $this->assertEquals($leadsByCursorCount-1, $newLeadsByCursorCount);

        $newAllLeadsByCursorCount = TestLead::withTrashed()->cursor()->count();
        $this->assertEquals($allLeadsByCursorCount, $newAllLeadsByCursorCount);

        $newOnlyTrashedLeadsByCursorCount = TestLead::onlyTrashed()->cursor()->count();
        $this->assertEquals($onlyTrashedLeadsByCursorCount+1, $newOnlyTrashedLeadsByCursorCount);

    }

    /*public function testReplicate()
    {
        $lead = TestLead::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => 'test@test.com']);

        $leadTwo = $lead->replicate()->fill([
            'Email' => 'test2@test.com'
        ])->save();

        $this->assertTrue($leadTwo->wasRecentlyCreated);

    }*/

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
				     * If you'd like to specify an API version manually, it can be done here.
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

        if (!file_exists(__DIR__.'/../.env')) {
            $ex = new \Exception("Local testing requires a .env file!");
            throw $ex;
        }

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
