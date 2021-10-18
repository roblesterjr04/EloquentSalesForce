# Eloquent Sales Force
## SalesForce Object to Laravel ORM for Laravel 7+8

[![Actions Status](https://github.com/roblesterjr04/EloquentSalesForce/workflows/Tests/badge.svg)](https://github.com/roblesterjr04/EloquentSalesForce/actions)
[![Latest Stable Version](https://img.shields.io/packagist/v/rob-lester-jr04/eloquent-sales-force.svg)](https://packagist.org/packages/rob-lester-jr04/eloquent-sales-force)
[![Packagist](https://img.shields.io/packagist/dm/rob-lester-jr04/eloquent-sales-force.svg)](https://packagist.org/packages/rob-lester-jr04/eloquent-sales-force)
[![Packagist](https://img.shields.io/packagist/l/rob-lester-jr04/eloquent-sales-force.svg)](https://packagist.org/packages/rob-lester-jr04/eloquent-sales-force)



Work with SalesForce APIs via the Eloquent Model.

## NOTE FOR BUILD

Tests do not use mockery, they are connecting to SFDC in real time - sometimes the credentials expire and then the build will fail when pull requests are evaluated. I will be implementing mockery at some point, but for now, do not be too concerned about a `failed` build status.

## Installation

Install via composer

```bash
composer require rob-lester-jr04/eloquent-sales-force
```

*Note: This package is only tested and supported Laravel 7.0 and up.

### Publish Configuration File

**Note that this is optional and in most cases, the configuration here is not needed.

```bash
php artisan vendor:publish --provider="Lester\EloquentSalesForce\ServiceProvider" --tag="config"
```

## Usage

### Setting up your connected app

1. Log into to your Salesforce org
2. Click on Setup in the upper right-hand menu
3. Under Build click `Create > Apps`
4. Scroll to the bottom and click `New` under Connected Apps.
5. Enter the following details for the remote application:
    * Connected App Name
    * API Name
    * Contact Email
    * Enable OAuth Settings under the API dropdown
    * Callback URL
    * Select access scope (If you need a refresh token, specify it here)
6. Click `Save`

After saving, you will now be given a Consumer Key and Consumer Secret. Update your config file with values for `consumerKey`, `consumerSecret`, and `loginURL`.

### Configuration

In your `config/database.php` file, add the following driver to the connections array

```php
'soql' => [
    'driver' => 'soql',
    'database' => null,
    'consumerKey'    => env('SF_CONSUMER_KEY'),
    'consumerSecret' => env('SF_CONSUMER_SECRET'),
    'loginURL'       => env('SF_LOGIN_URL'),
    // Only required for UserPassword authentication:
    'username'       => env('SF_USERNAME'),
    // Security token might need to be ammended to password unless IP Address is whitelisted
    'password'       => env('SF_PASSWORD')
],
```

If you need to modify any more settings for Forrest, publish the config file using the `artisan` command:

```bash
php artisan vendor:publish
```

You can find the config file in: `config/eloquent_sf.php`. Any of the same settings that Forrest recognizes will be available to set here.

### Using Models

Create a model for the object you want to use, example: `artisan make:model Lead`

Open the model file, it will look like this:

```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    //
}
```

Replace the `use` statement so your model looks like:

```php
<?php

namespace App;

use Lester\EloquentSalesForce\Model;

class Lead extends Model
{
    //
}
```

Now use it like you normally would!

```php
$leads = Lead::where('email', 'user@test.com')->get();

$lead = Lead::find('00Q1J00000cQ08eUAC');
```

Update properties like you normally would...

```php
$lead->FirstName = 'Robert';
$lead->save();
```

#### Observers

The latest version updates allow the use of observers now! Here is an example setting a static observer in the model using the `booted()` method.

```php
/* Lead Model */
public static function booted()
{
    // Example setting the phone number when creating the model.
    static::creating(function ($lead) {
        $lead->Phone = '1231231234';
    });
}
```

#### SoftDeletes

SalesForce already handles soft-deleting - What the package now does is expose the traditional soft-delete methods that Eloquent has to query upon trashed elements.

```php

/* Finding deleted items */
Lead::withTrashed()->where('Email', 'email@test.com')->get();

/* Checking if an item is deleted */
$lead->trashed()

/* Quering only deleted items */
Lead::onlyTrashed()->get();

```

#### Columns

By default, the selected columns for the record will be the compact layout defined in the SalesForce instance. This is usually enough. If you need to pull specific columns, you have some options.

```php
// Per select
$object = Contact::select('Id', 'Name')->get();
```

You can also define on the model what columns you want to bring back with each record. Add `public $columns` to the top of the model.

```php
...

    public $columns = [
        'Name',
        'Id',
        'Email',
    ];
```

To return the columns currently available on a model, use the `describe` method on the object.

```php
$fields = Lead::describe();
```

#### ReadOnly Fields

To specify fields on the model that are read-only and to force them to be excluded from any update/insert requests, define the `protected $readonly = []` array in the model

```php
...

	protected $readonly = [
		'Name'
	];
```

#### Picklists

If you are using a field that is a picklist in SalesForce, you can capture the values of that picklist from this function on the model.

```php
$statusValues = $lead->getPicklistValues('Status');
```

## Batch Queries (beta)

SalesForce has API limits. We know this. It sucks. For us at least. So now in the package, you can batch several queries and make a single API call to execute them, and get the results back in a handy collection object.

#### Usage

At the end of most queries we commonly call `get()` to retrieve the results of our assembled query. We can queue up a batch call by calling `batch()` instead of `get()`. After queuing up the desired queries, you can call `SObjects::runBatch()` and it will return the results of the batched queries in an array.

#### Example

```php
Lead::select(['Id', 'FirstName', 'Company'])->limit(100)->batch(); // instead of get.

Contact::select(['Id', 'FirstName', 'Phone'])->limit(50)->batch();

$batch = SObjects::runBatch();

$leads = $batch->results('Lead'); // get() also works here...
$contacts = $batch->results('Contact'); // ... and here.
```

### Tagging the batch

By default, each batch query is tagged with the name of the model that is being queried. For example if you have a model class called `Prospects` (even if it maps to the SF Lead object), the tag of the batch will be `Prospects`. If you try and batch 3 queries on the same object without specifying a custom tag for each batch, only the last batch will actually be run. So we recommend tagging the batch when you queue it if you're batching multiple queries on the same object.

#### Example

```php
Lead::select(['Id', 'FirstName', 'Company'])->limit(100)->batch();

Lead::select(['Id', 'FirstName', 'Company'])->limit(30)->where('Company', 'Test')->batch('test_company');

$batch = SObjects::runBatch();

$firstCentLeads = $batch->get('Lead');
$testCompanyLeads = $batch->get('test_company');

```

### Using the Batch Collection object

The batch collection object can be used independently of the facade if you'd like to create a batch over time and then execute later. When using the `batch()` method on the query builder, the assembled query builder is added to a collection on the facade. You can either run that batch collection by using the method `SObjects::runBatch()` or you can access the collection by returning `SObjects::getBatch()`. If you have the object stored in a variable, you can run it with `->run()` or you can add more query builders to it with `->batch`

```php
$batchCollection = new SOQLBatch();

$batchCollection->batch(Lead::where('FirstName', 'like', 'Test%'));

$batchCollection->run();

```

## Inserting and Updating

#### Insert

```php
$lead = new Lead();
$lead->FirstName = 'Foo';
$lead->LastName = 'Bar';
$lead->Company = 'Test';
$lead->save();
```

OR:

```php
$lead = Lead::create(['FirstName' => 'Foo', 'LastName' => 'Bar', 'Company' => 'Test Company']);
```

#### Bulk Insert

```php
$leads = collect([
	new Lead(['Email' => 'email1@test.com']),
	new Lead(['Email' => 'email2@test.com'])
]);

Lead::insert($leads);
```

#### Update

```php
$lead = Lead::first();
$lead->LastName = 'Lester';
$lead->save();
```

OR:

```php
$lead->update(['LastName' => 'Lester']);
```

#### Bulk Update
The bulk update method is model agnostic - meaning that this capability, within salesforce, accepts a mix of object types in the collection that gets sent. So this method therefore exists in the new SObjects facade.

```php
$accounts = Account::where('Company', 'Test Company')->get(); // collection of accounts.

$accounts = $accounts->map(function($account) {
	$account->Company = 'New Company Name';
	return $account;
});

SObjects::update($accounts); // Sends all these objects to SF with updates.
```

SalesForce will execute each update individually and will not fail the batch if any individual update fails. If you want success to be dependent on all updates succeeding (all or nothing), then you can pass `true` as the second parameter. If this is set, the batch of updates must all succeed, or none will.

```php
SObjects::update($accounts, true); // All or none.
```

## Columns, Where, Ordering
#### Columns/Properties
By default, the object is loaded with the columns found in the primary compactLayout. If you'd like additional columns, you would use the `select` method on the model. For example:

```php
$leads = Lead::select('Id', 'Name', 'Email', 'Custom_Field__c')->limit(10)->get();
```

#### Where / Order By
The `where` and `orderBy` methods work as usual as well.

```php
$contacts = Contact::where('Email', 'test@test.com')->first();

$contacts = Contact::where('Name', 'like', 'Donuts%')->get();

$contacts = Contact::limit(20)->orderBy('Name')->get();
```

#### Delete
Exactly as you'd expect.

```php
$lead = Lead::first();

$lead->delete();
```

## Relationships

Relationships work the same way.

Create a model like above for `Account` and `Contact`

In the `Contact` model, add a method for a relationship like you normally would

```php
## Contact.php
public function account()
{
	return $this->belongsTo(Account::class);
}
```

So you can call now:

```php
$contact = Contact::where('email', 'some@email.com')->first();
$account = $contact->account;
```

And the reverse is true

```php
## Account.php
public function contacts()
{
	return $this->hasMany(Contact::class);
}
```

```php
$contacts = $account->contacts;
```

### Joins
You are also able to use manual joins

```php
$account = Account::join('Contact', 'AccountId')->first();
```

These aren't as easy to work with as **Relationships** because the SalesForce API still returns the array nested in the `records` property.

### Custom Objects

To use custom objects (or an object with a special object name, different from the model), set the protected `$table` property in the model.

```php
<?php

namespace App;

use Lester\EloquentSalesForce\Model;

class TouchPoint extends Model
{
    protected $table = 'TouchPoint__c';

    /** Any other overrides **/
}
```

## The SObjects Facade
This is a new feature to the package. The SObjects facade serves the purpose of exposing any global features not model specific, such as authentication and mass updates, but also it is a pass-thru mechanism for the Forrest facade.

Any methods such as get, post, patch, resources, etc will pass through to the Forrest facade and accept parameters respectively.

#### Authentication
The `authenticate()` method in the facade will return the token information that has been stored in cache/session.

#### Anonymous Objects
Sometimes you want to grab a record from SalesForce casually without having to pre-generate a model for it. Now you can do that easily with the `object` method on the facade. Example:

```php
$queryResult = SObjects::query("Select Id, FirstName from Lead where Email like 'test@%'");

$leads = collect($queryResult['records'])->map(function($record) {
    return SObjects::object($record);
});

```

The class used for each object returned will be `Lester\EloquentSalesForce\SalesForceObject`.

#### Pick list choices
You can get the possible pick list values from a dropdown by using this method on the facade.

```php
$listValues = SObjects::getPicklistValues('Lead', 'Status');
```

#### Logging
You can set a different log channel for the SOQL actions by specifying `SOQL_LOG=` in your `.env` file.

## Testing

The tests in this package are meant for contributors and have been written to be executed independently of a Laravel application. They will not work as part of the applications testing flow.

Create a `.env` file that includes the SalesForce credentials for your test instance, or else the test will fail to execute. The `.env` field should include these properties:

```txt
USERNAME=
PASSWORD=
CONSUMER_KEY=
CONSUMER_SECRET=
LOGIN_URL=https://login.salesforce.com
CACHE_DRIVER=array
LOG_CHANNEL=file
```

Dependencies are required, so execute `composer install`

To execute, run `composer test`

## Security

If you discover any security related issues, please email
instead of using the issue tracker.

## Credits

- [Rob Lester](https://github.com/roblesterjr04/EloquentSalesForce)
- [Omniphx/Forrest SalesForce for Laravel](https://github.com/omniphx/forrest)
- [All contributors](https://github.com/roblesterjr04/EloquentSalesForce/graphs/contributors)

This package is bootstrapped with the help of
[melihovv/laravel-package-generator](https://github.com/melihovv/laravel-package-generator).
