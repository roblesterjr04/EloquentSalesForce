<?php

namespace Lester\EloquentSalesForce;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    const CONFIG_PATH = __DIR__ . '/../config/eloquent-sales-force.php';

    public function boot()
    {
	    config(
		    [
			    'forrest.authentication' => config('database.connections.soql.authentication'),
			    'forrest.credentials' => config('database.connections.soql'),
			    'forrest.storage.type' => 'cache',
			    'forrest.storage.store_forever' => true
		    ]
	    );
	    
	    \Forrest::authenticate();
	    
	    $this->publishes([
            self::CONFIG_PATH => config_path('eloquent-sales-force.php'),
        ], 'config');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            self::CONFIG_PATH,
            'eloquent-sales-force'
        );

        $this->app->bind('eloquent-sales-force', function () {
            return new EloquentSalesForce();
        });
    }
}
