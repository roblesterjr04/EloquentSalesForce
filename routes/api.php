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

Route::post('/api/syncObject/{sfid}', function (Request $request, $sfid) {
    foreach(config('eloquent_sf.syncTwoWayModels', []) as $class) {
        $collection = $class::where((new $class)->getSalesforceIdField(), $sfid)->get();
        foreach ($collection as $model) {
            $model->syncWithSalesforce();
        }
    }
})->middleware('api');

Route::get('/login/salesforce', function(Request $request)
{
    return SalesForce::authenticate();
})->middleware('web');

Route::get('/login/salesforce/callback', function(Request $request)
{
    SalesForce::callback();

    return redirect(config('eloquent_sf.redirectTo', '/'));
})->middleware('web');
