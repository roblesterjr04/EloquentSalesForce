# Eloquent Sales Force

[![Build Status](https://scrutinizer-ci.com/g/rob-lester-jr04/EloquentSalesForce/badges/build.png?b=master)](https://scrutinizer-ci.com/g/rob-lester-jr04/EloquentSalesForce/build-status/master)
[![Code Intelligence Status](https://scrutinizer-ci.com/g/rob-lester-jr04/EloquentSalesForce/badges/code-intelligence.svg?b=master)](https://scrutinizer-ci.com/code-intelligence)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/rob-lester-jr04/EloquentSalesForce/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/rob-lester-jr04/EloquentSalesForce/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/rob-lester-jr04/EloquentSalesForce/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/rob-lester-jr04/EloquentSalesForce/?branch=master)

[![Latest Stable Version](https://poser.pugx.org/rob-lester-jr04/eloquent-sales-force/v/stable.svg)](https://packagist.org/packages/live-person-inc/live-engage-laravel)
[![Latest Unstable Version](https://poser.pugx.org/rob-lester-jr04/eloquent-sales-force/v/unstable.svg)](https://packagist.org/packages/live-person-inc/live-engage-laravel)
[![Packagist](https://poser.pugx.org/rob-lester-jr04/eloquent-sales-force/d/total.svg)](https://packagist.org/packages/live-person-inc/live-engage-laravel)


Package description: CHANGE ME

## Installation

Install via composer
```bash
composer require rob-lester-jr04/eloquent-sales-force
```

### Register Service Provider

**Note! This and next step are optional if you use laravel>=5.5 with package
auto discovery feature.**

Add service provider to `config/app.php` in `providers` section
```php
Lester\EloquentSalesForce\ServiceProvider::class,
```

### Register Facade

Register package facade in `config/app.php` in `aliases` section
```php
Lester\EloquentSalesForce\Facades\EloquentSalesForce::class,
```

### Publish Configuration File

```bash
php artisan vendor:publish --provider="Lester\EloquentSalesForce\ServiceProvider" --tag="config"
```

## Usage

CHANGE ME

## Security

If you discover any security related issues, please email 
instead of using the issue tracker.

## Credits

- [](https://github.com/rob-lester-jr04/eloquent-sales-force)
- [All contributors](https://github.com/rob-lester-jr04/eloquent-sales-force/graphs/contributors)

This package is bootstrapped with the help of
[melihovv/laravel-package-generator](https://github.com/melihovv/laravel-package-generator).
