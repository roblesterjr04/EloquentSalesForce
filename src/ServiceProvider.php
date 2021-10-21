<?php

namespace Lester\EloquentSalesForce;

use Lester\EloquentSalesForce\Facades\SObjects as SfFacade;
use Illuminate\Support\Arr;
use Lester\EloquentSalesForce\Console\MakeModelCommand;
use Lester\EloquentSalesForce\Console\SyncFromSalesforce;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
	const CONFIG_PATH = __DIR__ . '/../config/eloquent_sf.php';

	public function boot()
	{

		$this->publishes([
			self::CONFIG_PATH => config_path('eloquent_sf.php'),
		], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeModelCommand::class,
                SyncFromSalesforce::class,
            ]);
        } else {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }

	}

	public function register()
	{
		$this->mergeConfigFrom(
			self::CONFIG_PATH,
			'eloquent_sf'
		);

		config([
			'forrest' => config('eloquent_sf.forrest'),
		]);

		$this->app->register(
			'Omniphx\Forrest\Providers\Laravel\ForrestServiceProvider'
		);

		$this->app->bind('sobjects', function() {
			return new SObjects();
		});

		$loader = \Illuminate\Foundation\AliasLoader::getInstance();
		$loader->alias('Forrest', 'Omniphx\Forrest\Providers\Laravel\Facades\Forrest');
		$loader->alias('SObjects', 'Lester\EloquentSalesForce\Facades\SObjects');
	}

	/**
	 * [objectFields description]
	 * @param  [type] $table   [description]
	 * @param  [type] $columns [description]
	 * @return [type]          [description]
	 */
	public static function objectFields($table, $columns)
	{
		if ($columns == ['*']) {
			/** @scrutinizer ignore-call */
			SfFacade::authenticate();
			$layouts = SfFacade::sobjects($table . '/' . config('eloquent_sf.layout') . '/');
			$fields = Arr::pluck($layouts["fieldItems"], 'layoutComponents.0');
			$columns = ['Id', 'CreatedDate', 'LastModifiedDate'];
            if (!in_array($table, config('eloquent_sf.noSoftDeletesOn', ['User']))) $columns[] = 'IsDeleted';
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
