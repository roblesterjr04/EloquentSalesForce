<?php

return [

	// Set the layout used for default columns returned on select.
	'layout'			=> 'describe/compactLayouts/primary',

	'logging'			=> env('SOQL_LOG', 'single'),

	// Override any Forrest settings here. The Forrest package config file is ignored, but all Forrest settings are supported here.
	'forrest' => [
		/*
	     * Options include WebServer or UserPassword
	     */
		'authentication'	=> 'UserPassword',

		/*
	     * Enter your credentials
	     * Username and Password are only necessary for UserPassword flow.
	     * Likewise, callbackURI is only necessary for WebServer flow.
	     */
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

		/*
	     * If you'd like to specify an API version manually it can be done here.
	     * Format looks like '32.0'
	     */
		'version'        => '',
	],

];
