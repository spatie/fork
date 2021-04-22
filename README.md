# A lightweight solution for running code concurrently in PHP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/fork.svg?style=flat-square)](https://packagist.org/packages/spatie/fork)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/spatie/fork/run-tests?label=tests)](https://github.com/spatie/fork/actions?query=workflow%3ATests+branch%3Amaster)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/spatie/fork/Check%20&%20fix%20styling?label=code%20style)](https://github.com/spatie/fork/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/fork.svg?style=flat-square)](https://packagist.org/packages/spatie/fork)

[](delete) 1) manually replace `Freek Van der Herten, freekmurze, auhor@domain.com, spatie, spatie, Vendor Name, fork, fork, fork, Fork, A lightweight solution for running code concurrently in PHP` with their correct values
[](delete) in `CHANGELOG.md LICENSE.md README.md composer.json ForkClass.php ExampleTest.php FUNDING.yml config.yml SECURITY.md`
[](delete) and delete `configure-fork.sh`

[](delete) 2) You can also run `./configure-fork.sh` to do this automatically.

This is where your description should go. Try and limit it to a paragraph or two. Consider adding a small example.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/package-fork-php.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/package-fork-php)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require spatie/fork
```

## Usage

```php
$fork = new Spatie\Fork();
echo $fork->echoPhrase('Hello, Spatie!');
```

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

- [Freek Van der Herten](https://github.com/freekmurze)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
