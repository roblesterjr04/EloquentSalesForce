# Eloquent Sales Force

[![Build Status](https://travis-ci.org/rob-lester-jr04/eloquent-sales-force.svg?branch=master)](https://travis-ci.org/rob-lester-jr04/eloquent-sales-force)
[![styleci](https://styleci.io/repos/CHANGEME/shield)](https://styleci.io/repos/CHANGEME)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/rob-lester-jr04/eloquent-sales-force/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/rob-lester-jr04/eloquent-sales-force/?branch=master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/CHANGEME/mini.png)](https://insight.sensiolabs.com/projects/CHANGEME)
[![Coverage Status](https://coveralls.io/repos/github/rob-lester-jr04/eloquent-sales-force/badge.svg?branch=master)](https://coveralls.io/github/rob-lester-jr04/eloquent-sales-force?branch=master)

[![Packagist](https://img.shields.io/packagist/v/rob-lester-jr04/eloquent-sales-force.svg)](https://packagist.org/packages/rob-lester-jr04/eloquent-sales-force)
[![Packagist](https://poser.pugx.org/rob-lester-jr04/eloquent-sales-force/d/total.svg)](https://packagist.org/packages/rob-lester-jr04/eloquent-sales-force)
[![Packagist](https://img.shields.io/packagist/l/rob-lester-jr04/eloquent-sales-force.svg)](https://packagist.org/packages/rob-lester-jr04/eloquent-sales-force)

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
RobLesterJr04\EloquentSalesForce\ServiceProvider::class,
```

### Register Facade

Register package facade in `config/app.php` in `aliases` section
```php
RobLesterJr04\EloquentSalesForce\Facades\EloquentSalesForce::class,
```

### Publish Configuration File

```bash
php artisan vendor:publish --provider="RobLesterJr04\EloquentSalesForce\ServiceProvider" --tag="config"
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
