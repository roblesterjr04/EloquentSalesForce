<?php
	
Route::get('login/salesforce', function() {
	return \Forrest::authenticate();
});

Route::get('login/salesforce/callback', function() {
	\Forrest::callback();

	$me = \Forrest::identity();
	
	return Redirect::to('/');
});