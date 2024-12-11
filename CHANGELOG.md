# Changelog

All notable changes to `fork` will be documented in this file.

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
