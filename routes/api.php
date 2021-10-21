<?php

use Illuminate\Http\Request;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('syncObject/{sfid}', function (Request $request, $sfid) {
    foreach(config('eloquent_sf.syncTwoWayModels', []) as $class) {
        $collection = $class::where((new $class)->getSalesforceIdField(), $sfid)->get();
        foreach ($collection as $model) {
            $model->syncWithSalesforce();
        }
    }
});
