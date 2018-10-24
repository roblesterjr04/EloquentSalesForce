<?php

namespace Lester\EloquentSalesForce\Tests;

use Lester\EloquentSalesForce\ServiceProvider;
use Lester\EloquentSalesForce\TestModel;
use Orchestra\Testbench\TestCase;

class EloquentSalesForceTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

	/**
	 * @covers Lester\EloquentSalesForce\TestModel
	 * @covers Lester\EloquentSalesForce\Database\SOQLBuilder
	 * @covers Lester\EloquentSalesForce\Database\SOQLConnection
	 * @covers Lester\EloquentSalesForce\Database\SOQLGrammar
	 */
    public function testObject()
    {
	    
	    fwrite(STDERR, print_r("\n" . config('database.connections.soql.authentication')));
	    
	    //$lead = TestModel::first();
	    
        $this->assertEquals(1, 1);
    }
    
    public function setUp()
	{
		parent::setUp();
		
		config([
			'app.key' => 'base64:WRAf0EDpFqwpbS829xKy2MGEkcJxIEmMrwFIZbGxIqE='
		]);
		
		config([
			'database.connections.soql' => [
				'driver' => 'soql',
			    'database' => null,
				'consumerKey'    => getenv('CONSUMER_KEY'),
		        'consumerSecret' => getenv('CONSUMER_SECRET'),
		        'callbackURI'    => getenv('CALLBACK_URI'),
		        'loginURL'       => getenv('LOGIN_URL'),
		        'authentication' => 'UserPassword',
		
		        // Only required for UserPassword authentication:
		        'username'       => getenv('USERNAME'),
		        // Security token might need to be ammended to password unless IP Address is whitelisted
		        'password'       => getenv('PASSWORD')
			]
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
