# A lightweight solution for running PHP code concurrently

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/fork.svg?style=flat-square)](https://packagist.org/packages/spatie/fork)
[![Tests](https://github.com/spatie/fork/actions/workflows/run-tests.yml/badge.svg)](https://github.com/spatie/fork/actions/workflows/run-tests.yml)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/spatie/fork/Check%20&%20fix%20styling?label=code%20style)](https://github.com/spatie/fork/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/fork.svg?style=flat-square)](https://packagist.org/packages/spatie/fork)

This package makes it easy to run PHP concurrently. Behind the scenes, concurrency is achieved by forking the main PHP process to one or more child tasks.

In this example, where we are going to call an imaginary slow API, all three closures will run at the same time.

```php
use Spatie\Fork\Fork;

$results = Fork::new()
    ->run(
        fn () => (new Api)->fetchData(userId: 1),
        fn () => (new Api)->fetchData(userId: 2),
        fn () => (new Api)->fetchData(userId: 3),
    );

$results[0]; // fetched data of user 1
$results[1]; // fetched data of user 2
$results[2]; // fetched data of user 3
```
## How it works under the hood

✨ In this [video on YouTube](https://www.youtube.com/watch?v=IJXzc46MFPM), we explain how the package works internally.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/fork.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/fork)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can
support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using.
You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards
on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Requirements

This package requires PHP 8 and the [pcntl](https://www.php.net/manual/en/intro.pcntl.php) extensions which is installed in many Unix and Mac systems by default.

❗️ [pcntl](https://www.php.net/manual/en/intro.pcntl.php) only works in CLI processes, not in a web context.

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

            return 'result from task 1';
        },
        function ()  {
            sleep(1);

            return 'result from task 2';
        },
        function ()  {
            sleep(1);

            return 'result from task 3';
        },
    );

// this code will be reached this point after 1 second
$results[0]; // contains 'result from task 1'
$results[1]; // contains 'result from task 2'
$results[2]; // contains 'result from task 3'
```

### Running code before and after each closure

If you need to execute some code before or after each callable passed to `run`, you can pass a callable to `before` or `after` methods. This callable passed will be executed in the child process right before or after the execution of the callable passed to `run`.

### Using `before` and `after` in the child task

Here's an example where we are going to get a value from the database using a Laravel Eloquent model. In order to let the child task use the DB, it is necessary to reconnect to the DB. The closure passed to `before` will run in both child tasks that are created for the closures passed to `run`.

```php
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Fork\Fork;

 Fork::new()
    ->before(fn () => DB::connection('mysql')->reconnect())
    ->run(
        fn () => User::find(1)->someLongRunningFunction(),
        fn () => User::find(2)->someLongRunningFunction(),
    );
```

If you need to perform some cleanup in the child task after the callable has run, you can use the `after` method on a `Spatie\Fork\Fork` instance.

### Using `before` and `after` in the parent task.

If you need to let the callable passed to `before` or `after` run in the parent task, then you need to pass that callable to the `parent` argument.

```php
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Fork\Fork;

 Fork::new()
    ->before(
        parent: fn() => echo 'this runs in the parent task'
    )
    ->run(
        fn () => User::find(1)->someLongRunningFunction(),
        fn () => User::find(2)->someLongRunningFunction(),
    );
```

You can also pass different closures, to be run in the child and the parent task

```php
use Spatie\Fork\Fork;

Fork::new()
    ->before(
        child: fn() => echo 'this runs in the child task',
        parent: fn() => echo 'this runs in the parent task',
    )
    ->run(
        fn () => User::find(1)->someLongRunningFunction(),
        fn () => User::find(2)->someLongRunningFunction(),
    );
```

### Returning data

All output data is gathered in an array and available as soon as all children are done. In this example, `$results` will contain three items:

```php
$results = Fork::new()
    ->run(
        fn () => (new Api)->fetchData(userId: 1),
        fn () => (new Api)->fetchData(userId: 2),
        fn () => (new Api)->fetchData(userId: 3),
    );
```

The output is also available in the `after` callbacks, which are called whenever a child is done and not at the very end:

```php
$results = Fork::new()
    ->after(
        child: fn (int $i) => echo $i, // 1, 2 and 3
        parent: fn (int $i) => echo $i, // 1, 2 and 3
    )
    ->run(
        fn () => 1,
        fn () => 2,
        fn () => 3,
    );
```

Finally, return values from child tasks are serialized using PHP's built-in `serialize` method. This means that you can return anything you can normally serialize in PHP, including objects:

```php
$result = Fork::new()
    ->run(
        fn () => new DateTime('2021-01-01'),
        fn () => new DateTime('2021-01-02'),
    );
```

### Configuring concurrency

By default, all callables will be run in parallel. You can however configure a maximum amount of concurrent processes:

```php
$results = Fork::new()
    ->concurrent(2)
    ->run(
        fn () => 1,
        fn () => 2,
        fn () => 3,
    );
```

In this case, the first two functions will be run immediately and as soon as one of them finishes, the last one will start as well.

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

- [Brent Roose](https://github.com/brendt)
- [Freek Van der Herten](https://github.com/freekmurze)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
