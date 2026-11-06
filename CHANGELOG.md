# Changelog

All notable changes to `fork` will be documented in this file.

## 1.3.0 - 2026-11-06

### Added

- Opt-in `Fork::catch(callable $callback)` to handle task exceptions in the parent process. The callback receives the exception and zero-based task order; its return value becomes the task result and reaches `after(parent: ...)` (#99).
- Handle failures while serializing task return values through the same callback. Serializable exceptions retain their original type and data. Exceptions that cannot be serialized or restored fall back to `Spatie\Fork\Exceptions\TaskFailed`, preserving their original class, message, file, line and trace text.
- Keep successful data separate from transported exceptions, including strings resembling transport markers and returned exception objects. Exception restoration hooks run only in the parent.

### Development

- Test PHP 8.6 alongside PHP 8.3–8.5, and allow Pest 5 in development dependencies (#104).

### Compatibility

- PHP 8.3+ requirements and existing `before`/`after` callback signatures are unchanged. Without `catch()`, existing output and exception behavior is unchanged.
- `catch()` handles task execution and return-value serialization, not before/after callbacks, parent-side result restoration, transfer failures or unexpected child exit statuses. Exceptions thrown by the catch callback propagate from `run()`.
- Existing transfer deadlines, child status handling, signal-handler restoration, termination and sibling cleanup policies are unchanged.

**Full Changelog**: https://github.com/spatie/fork/compare/1.2.12...1.3.0

## 1.2.12 - 2026-11-04

### Fixed

- Restore the application's previous `SIGINT`, `SIGTERM` and `SIGQUIT` handlers when `run()` returns or throws an exception (#100).

### Compatibility

- Signal dispatch, termination, child handling and cleanup policies during `run()` are unchanged. Signals are not forwarded to application handlers while tasks are running.
- PHP 8.3+ support, optional POSIX support and asynchronous signal dispatch behavior are unchanged. No API or dependency was added.

**Full Changelog**: https://github.com/spatie/fork/compare/1.2.11...1.2.12

## 1.2.11 - 2026-11-04

### Fixed

- Report incomplete child-result transfers with the existing `CouldNotManageTask` exception instead of returning truncated strings or invalid serialized data (#102).
- Preserve the five-second no-progress write limit across interrupted waits. Successful writes reset the limit, so progressing transfers may take longer overall.

### Compatibility

- Successful results, PHP 8.3+ support, and optional POSIX support are unchanged. Transfers that previously appeared successful with incomplete output now fail explicitly; no timeout API or dependency was added.

**Full Changelog**: https://github.com/spatie/fork/compare/1.2.10...1.2.11

## 1.2.10 - 2026-11-04

### What’s Changed

* Fix stopped child tasks being treated as finished: a child stopped with `SIGSTOP` and later resumed with `SIGCONT` is now awaited until it actually terminates, preserving its output. Thanks @specialtactics for the contribution in https://github.com/spatie/fork/pull/97.
* Preserve existing fail-fast handling of nonzero child exits. The original default-draining proposal was intentionally withdrawn: queued work is not started after a failure, and no new draining API or cleanup/cancellation policy is introduced.
* Add an isolated public stopped/continued-child regression. Existing fork-creation cleanup, optional-POSIX support, subclass compatibility and PHP requirements remain unchanged.

**Full Changelog**: https://github.com/spatie/fork/compare/1.2.9...1.2.10

## 1.2.9 - 2026-11-04

### What's Changed

* Fail immediately when a child process cannot be forked instead of storing `-1` as a task PID and potentially waiting for another child in https://github.com/spatie/fork/pull/101.
* Close parent-side task connections and discard queued work after a failed launch. With optional POSIX support, terminate and reap already-started tasks. Preserve exceptions thrown by application warning handlers.
* Add isolated public-behavior regressions for process limits, cleanup, queued work, unrelated children, and reuse. Runtime requirements remain unchanged; without POSIX, already-started children may continue running and remain the caller's responsibility.
* Document an example of task timeouts using `before` / `after` callbacks in https://github.com/spatie/fork/pull/95, and retain Spatie-hosted README images.

**Full Changelog**: https://github.com/spatie/fork/compare/1.2.8...1.2.9

## 1.2.8 - 2026-09-14

### What's Changed

* Fix `TypeError` from `socket_create_pair()` when Swoole runtime hooks are enabled (#93) in #94

**Full Changelog**: https://github.com/spatie/fork/compare/1.2.7...1.2.8

## 1.2.7 - 2026-03-27

### What's Changed

* Require PHP 8.3+
* Upgrade to Pest 4 / PHPUnit 12
* Replace php-cs-fixer with Laravel Pint
* Modernize phpunit.xml.dist for PHPUnit 12
* Use constructor property promotion and first-class callable syntax
* Fix `Task::execute()` return type (`string` instead of `string|bool`)
* Update all GitHub Actions to current versions

**Full Changelog**: https://github.com/spatie/fork/compare/1.2.6...1.2.7

## 1.2.6 - 2026-03-27

### What's Changed

* Fix truncation of large payloads by @obelloc in #83
* Update CI to test PHP 8.0 through 8.5

**Full Changelog**: https://github.com/spatie/fork/compare/1.2.5...1.2.6

## 1.2.5 - 2025-04-24

### What's Changed

* Fix task exit status handling, uniformize process exit method and tests by @jchambon-equativ in https://github.com/spatie/fork/pull/74

### New Contributors

* @jchambon-equativ made their first contribution in https://github.com/spatie/fork/pull/74

**Full Changelog**: https://github.com/spatie/fork/compare/1.2.4...1.2.5

## 1.2.4 - 2025-01-27

### What's Changed

* [REMOVE] PHP 8.4 deprecation warnings by @yabooodya in https://github.com/spatie/fork/pull/66

### New Contributors

* @yabooodya made their first contribution in https://github.com/spatie/fork/pull/66

**Full Changelog**: https://github.com/spatie/fork/compare/1.2.3...1.2.4

## 1.2.3 - 2024-12-11

### What's Changed

* Add a test to verify that child processes are isolated by @ahmadreza1383 in https://github.com/spatie/fork/pull/64
* Interrupted system call is not handled well by @jochem-blok in https://github.com/spatie/fork/pull/61

### New Contributors

* @ahmadreza1383 made their first contribution in https://github.com/spatie/fork/pull/64
* @jochem-blok made their first contribution in https://github.com/spatie/fork/pull/61

**Full Changelog**: https://github.com/spatie/fork/compare/1.2.2...1.2.3

## 1.2.2 - 2023-12-07

### What's Changed

* Switch from kill-all-exit to a normal exit when a child is done by @puggan in https://github.com/spatie/fork/pull/51

### New Contributors

* @puggan made their first contribution in https://github.com/spatie/fork/pull/51

**Full Changelog**: https://github.com/spatie/fork/compare/1.2.1...1.2.2

## 1.2.1 - 2023-11-23

### What's Changed

- Kill any forked tasks when force closed in terminal by @stevebauman in https://github.com/spatie/fork/pull/48

### New Contributors

- @stevebauman made their first contribution in https://github.com/spatie/fork/pull/48

**Full Changelog**: https://github.com/spatie/fork/compare/1.2.0...1.2.1

## 1.2.0 - 2023-05-29

### What's Changed

- Listen signals for terminating process by @zKoz210 in https://github.com/spatie/fork/pull/42

**Full Changelog**: https://github.com/spatie/fork/compare/1.1.3...1.2.0

## 1.1.3 - 2023-05-16

### What's Changed

- Refactor tests to Pest by @alexmanase in https://github.com/spatie/fork/pull/33
- Fixed php-cs-fixer by @zKoz210 in https://github.com/spatie/fork/pull/40
- Fixed tests for PHP 8.2 by @zKoz210 in https://github.com/spatie/fork/pull/38
- Fixed #36 by @zKoz210 in https://github.com/spatie/fork/pull/39

### New Contributors

- @alexmanase made their first contribution in https://github.com/spatie/fork/pull/33
- @zKoz210 made their first contribution in https://github.com/spatie/fork/pull/40

**Full Changelog**: https://github.com/spatie/fork/compare/1.1.2...1.1.3

## 1.1.2 - 2022-10-03

### What's Changed

- fixes socket_select interrupted exception  by @henzeb in https://github.com/spatie/fork/pull/32

### New Contributors

- @henzeb made their first contribution in https://github.com/spatie/fork/pull/32

**Full Changelog**: https://github.com/spatie/fork/compare/1.1.1...1.1.2

## 1.1.1 - 2021-12-09

- Proper handling of timeout microseconds

## 1.1.1 - 2021-12-09

- Proper handling of timeout microseconds

## 1.1.0 - 2021-05-04

- Add `Fork::concurrent(int $concurrent)`

## 1.0.1 - 2021-05-03

- Add check for pcntl support

## 1.0.0 - 2021-04-30

- Initial release
