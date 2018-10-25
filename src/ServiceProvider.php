<?php

namespace Lester\EloquentSalesForce;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    const CONFIG_PATH = __DIR__ . '/../config/eloquent_sf.php';

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
		
		$this->publishes([
            self::CONFIG_PATH => config_path('eloquent_sf.php'),
        ], 'config');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            self::CONFIG_PATH,
            'eloquent_sf'
        );
        
        $this->app->register(
		    'Omniphx\Forrest\Providers\Laravel\ForrestServiceProvider'
		);
		
		$loader = \Illuminate\Foundation\AliasLoader::getInstance();
		$loader->alias('Forrest', 'Omniphx\Forrest\Providers\Laravel\Facades\Forrest');
    }
}
