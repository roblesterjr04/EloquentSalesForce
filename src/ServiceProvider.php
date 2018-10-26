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
		config([
			'forrest' => [
				'authentication'	=> 'UserPassword',
				'credentials' => config('database.connections.soql'),
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
			    'version'        => '',
			]
		]);
	    
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
