# A lightweight solution for running PHP code concurrently

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/fork.svg?style=flat-square)](https://packagist.org/packages/spatie/fork)
[![Tests](https://github.com/spatie/fork/actions/workflows/run-tests.yml/badge.svg)](https://github.com/spatie/fork/actions/workflows/run-tests.yml)[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/spatie/fork/Check%20&%20fix%20styling?label=code%20style)](https://github.com/spatie/fork/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/fork.svg?style=flat-square)](https://packagist.org/packages/spatie/fork)

This package makes it easy to run PHP concurrently. Behind the scenes, concurrency is achieved by forking the main PHP process to one or more child processes.

In this example, the three closures will run at the same time.

```php
use Spatie\Fork\Fork;

$results = Fork::new()
    ->run(
        function ()  {
            sleep(1);
        
            return 'result from process 1';
        },
        function ()  {
             sleep(1);
        
            return 'result from process 2';
        },
        function ()  {
             sleep(1);
        
            return 'result from process 3';
        },
    );

// this code will be reached after 1 second
$results[0]; // contains 'result from process 1'
$results[1]; // contains 'result from process 2'
$results[2]; // contains 'result from process 3'
```

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/fork.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/fork)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can
support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using.
You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards
on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require spatie/fork
```

## Usage

You can pass as many closures as you want to `run`. They will be run concurrently. The `run` function will return an array with the return values of the executed closures.

```php
use Spatie\Fork\Fork;

$results = Fork::new()
    ->run(
        function ()  {
            sleep(1);
        
            return 'result from process 1';
        },
        function ()  {
             sleep(1);
        
            return 'result from process 2';
        },
        function ()  {
             sleep(1);
        
            return 'result from process 3';
        },
    );

// this code will be reached this point after 1 second
$results[0]; // contains 'result from process 1'
$results[1]; // contains 'result from process 2'
$results[2]; // contains 'result from process 3'
```

The closures to run shouldn't return objects, only primitives and arrays are allowed.

### Running code before and after each closure

If you need to execute code some before each callable passed to `run`, you can pass a callable to `before`. This callable passed to `before` will be executed in the child process right before the callable passed to  `run` will execute.

Here's an example where we are going to get a value from the database using a Laravel Eloquent model. In order to let the child process use the DB, it is necessary to reconnect to the DB. The closuse passed to `before` will run in both child processes that are created for the closures passed to `run`.

```php
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Fork\Fork;
 
 Fork::new()
    ->before(fn () => DB::connection('mysql')->reconnect())
    ->run(
        fn () => User::find(1)->someLongRunningFunction(),
        fn () => User::find(2)->someLongRunningFunction()
    );
```

If you need to perform some cleanup in the child process after the callable has run, you can use the `after` method on a `Spatie\Fork\Fork` instance. 

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Brent Roose](https://github.com/brendt_gd)
- [Freek Van der Herten](https://github.com/freekmurze)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
