<?php

namespace Lester\EloquentSalesForce;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
	const CONFIG_PATH = __DIR__ . '/../config/eloquent_sf.php';

	public function boot()
	{
		
		$this->publishes([
			self::CONFIG_PATH => config_path('eloquent_sf.php'),
		], 'config');
	}

	public function register()
	{
		config(
			[
				'forrest.authentication' => 'UserPassword',
				'forrest.credentials' => config('database.connections.soql'),
				'forrest.storage.type' => 'cache',
				'forrest.storage.store_forever' => true
			]
		);
	    
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
    
	public static function objectFields($table, $columns)
	{
		if ($columns == ['*']) {
			$layouts = \Forrest::sobjects($table . '/' . config('eloquent_sf.layout') . '/');
			$fields = array_pluck($layouts["fieldItems"], 'layoutComponents.0');
			$columns = ['Id'];
			self::getDetailNames($fields, $columns);
		}
		return $columns;
	    
	}
    
	/**
	 * getDetailNames function.
	 * 
	 * @access private
	 * @param mixed $fields
	 * @param mixed &$columns
	 * @return void
	 */
	private static function getDetailNames($fields, &$columns) {
		foreach ($fields as $field) {
			if ($field['details']['updateable'] == true) {
				$columns[] = $field['details']['name'];
			}
			if (isset($field['components'])) {
				self::getDetailNames($field['components'], $columns);
			}
		}
	}
}
