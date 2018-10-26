<?php

namespace Lester\EloquentSalesForce\Tests;

use Lester\EloquentSalesForce\ServiceProvider;
use Lester\EloquentSalesForce\TestModel;
use Orchestra\Testbench\TestCase;
use Illuminate\Support\Facades\Config;

class EloquentSalesForceTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }
    
    private $lead;

	/**
	 * @covers Lester\EloquentSalesForce\TestModel
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
	    $email = strtolower(str_random(10) . '@test.com');
	    $lead = TestModel::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => $email]);
	    $lead = TestModel::where('Email', $email)->first();
	    
        $this->assertEquals($lead->Email, $email);
        $lead->delete();
    }
    
    /*
	 * @covers Lester\EloquentSalesForce\Model
	 * @covers Lester\EloquentSalesForce\Model::save
	 * @covers Lester\EloquentSalesForce\Model::update
	 */
    public function testObjectUpdate()
    {
	    $email = strtolower(str_random(10) . '@test.com');
	    $lead = TestModel::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => $email]);
	    $lead->update(['FirstName' => 'Robert']);
        $lead = TestModel::where('Email', $email)->first();
        
        $this->assertEquals($lead->FirstName, 'Robert');
        $lead->delete();
    }
    
    /*
	 * @covers Lester\EloquentSalesForce\Model
	 * @covers Lester\EloquentSalesForce\Model::delete
	 */
    public function testObjectDelete()
    {
	    $email = strtolower(str_random(10) . '@test.com');
	    $lead = TestModel::create(['FirstName' => 'Rob', 'LastName' => 'Lester', 'Company' => 'Test', 'Email' => $email]);
	    $lead->delete();
	    $lead = TestModel::where('Email', $email)->get();
        $this->assertCount(0, $lead);
    }
    
    public function setUp()
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
			'cache.stores.file.path' => __DIR__,
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
		
		$app = require __DIR__.'/../../../../bootstrap/app.php';
	
		$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
	
		$app->loadEnvironmentFrom('.env');

		return $app;
	}
}
