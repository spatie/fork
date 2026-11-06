# A lightweight solution for running PHP code concurrently

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/fork.svg?style=flat-square)](https://packagist.org/packages/spatie/fork)
[![Tests](https://github.com/spatie/fork/actions/workflows/run-tests.yml/badge.svg)](https://github.com/spatie/fork/actions/workflows/run-tests.yml)
[![GitHub Code Style Action Status](https://github.com/spatie/fork/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/spatie/fork/actions/workflows/php-cs-fixer.yml)
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

[<img src="https://spatie.be/github-ad-image/fork.jpg" width="419px" />](https://spatie.be/github-ad-click/fork)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can
support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using.
You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards
on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Requirements

This package requires PHP 8 and the [pcntl](https://www.php.net/manual/en/intro.pcntl.php) extensions which is installed in many Unix and Mac systems by default.

❗️ [pcntl](https://www.php.net/manual/en/intro.pcntl.php) only works in CLI processes, not in a web context.
❗️ [posix](https://www.php.net/manual/en/book.posix.php) required for correct handling of process termination for Alpine Linux.

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

### Using `before` and `after` to perform a timeout

If you want to put a limit on how long a child task can run, making use of pcntl_alarm() is an easy way to do that.

Building off of the first example, you could do the following to cap a child task at 60 seconds:


```php
use Spatie\Fork\Fork;

$results = Fork::new()
    ->before(function () {
        // this is the code that is run if the alarm time is exceeded, 
        // which is to kill the process and exit with a non 0
        pcntl_signal(SIGALRM, function () {
            posix_kill(getmypid(), SIGKILL);
            exit(1);
        });

        // 60 is the number of seconds for your timeout, 
        // you can change this to anything
        pcntl_alarm(60);
    })
    ->after(function () {
        // Clear the alarm on a successful set of processing
        pcntl_alarm(0);
    })
    ->run(
        fn () => (new Api)->fetchData(userId: 1),
        fn () => (new Api)->fetchData(userId: 2),
        fn () => (new Api)->fetchData(userId: 3),
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

Tasks can finish in any order. To identify each completed task, opt in with `afterWithOrder`. Its callbacks receive the output and the task's position in the `run` call, starting at 0 and matching the result array's keys, not completion order:

```php
Fork::new()
    ->afterWithOrder(
        parent: function ($output, int $order): void {
            echo "Task {$order} returned {$output}";
        },
    )
    ->run(
        fn () => 'first',
        fn () => 'second',
    );
```

Like `after`, `afterWithOrder` accepts both `child` and `parent` callbacks. Parent callbacks receive the decoded return value, or the recovery value returned by `catch` when it handles a task exception. Child callbacks receive the raw output sent to the parent, which is serialized for non-string values or when `catch` is enabled. The existing task-failure and serialization behavior is unchanged.

The existing `after` callbacks still receive exactly one argument. You can register both hooks: in each process, `after` runs before `afterWithOrder`. Calling `afterWithOrder` again replaces both of its callbacks; calling it without arguments clears them without changing `after`. Callback exceptions follow the same behavior as `after`: parent exceptions propagate, while child callback exceptions are subject to the existing child-exit policy and are not transported to the parent.

Keep parent callbacks short: while they run, the parent cannot read other children's results. If a child cannot make progress sending its result for five seconds, `run()` throws `Spatie\Fork\Exceptions\CouldNotManageTask` instead of returning incomplete data. Successful writes reset this wait limit, so a transfer that keeps making progress can take longer than five seconds overall.

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

### Handling exceptions

By default, a child task that throws an exception returns an empty string. To find out what went wrong, pass a callback to `catch`. The exception is sent to the parent process, and passed to the callback together with the position of the task in the `run` call. Whatever the callback returns becomes the output of that task:

```php
$results = Fork::new()
    ->catch(function (Throwable $exception, int $order) {
        report($exception);

        return null;
    })
    ->run(
        fn () => (new Api)->fetchData(userId: 1),
        fn () => (new Api)->fetchData(userId: 2),
    );
```

The callback runs in the parent process. Its return value also reaches `after(parent: ...)` and does not need to be serializable. The task order is zero-based and refers to its position in `run`, not its completion order. Other tasks keep running and queued tasks are started as usual.

A task whose return value can't be serialized (for example a closure) is caught the same way, with the exception PHP threw while serializing it. Serializable exceptions retain their class, code, previous exception and custom properties. If an exception can't be serialized or restored in the parent, the callback receives a `Spatie\Fork\Exceptions\TaskFailed` exception instead, with the original exception's class, message, file, line and a string representation of its trace (`originalTrace`).

`catch` handles exceptions from the task callable and serialization of its return value, not from `before` or `after` callbacks, parent-side return-value restoration, failed output transfers or unexpected child exit statuses. If the catch callback itself throws, that exception propagates from `run`, just like other parent callbacks: no further queued tasks are started, but already-started siblings are not automatically cancelled or drained. Installing `catch` does not change signal handling or cleanup policies. Without it, existing output and failure behavior is unchanged.

### When a child process cannot be created

If the system cannot create a child process (for example, because a process limit is reached), `run()` throws `Spatie\Fork\Exceptions\CouldNotManageTask` instead of waiting for an invalid process id. If your error handler throws an exception for the fork warning, that original exception is rethrown instead.

No further queued tasks are started. With the optional `posix` extension, already-started tasks are killed and reaped before the exception is thrown. Without `posix`, `run()` still fails immediately, but cannot terminate or reap those tasks; they may continue running, and the caller is responsible for managing them. Parent-side task connections are closed in either case. Cleanup does not undo side effects of tasks that already ran.

### Signals

`run()` temporarily replaces the application's `SIGINT`, `SIGTERM` and `SIGQUIT` handlers. The previous handlers (including `SIG_DFL` and `SIG_IGN`) are restored when `run()` returns or throws an exception.

Signal handling during `run()` is unchanged: with the optional `posix` extension, these signals kill the running child tasks and the parent with `SIGKILL`; application handlers are not forwarded the signal. Without `posix`, the receiving process exits without terminating its children. As before, `run()` enables asynchronous signal dispatch and leaves it enabled afterwards.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Brent Roose](https://github.com/brendt)
- [Freek Van der Herten](https://github.com/freekmurze)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
