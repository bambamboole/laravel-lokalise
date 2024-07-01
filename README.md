# Laravel Lokalise

[![Latest Version on Packagist](https://img.shields.io/packagist/v/bambamboole/laravel-lokalise.svg?style=flat-square)](https://packagist.org/packages/bambamboole/laravel-lokalise)
[![Total Downloads](https://img.shields.io/packagist/dt/bambamboole/laravel-lokalise.svg?style=flat-square)](https://packagist.org/packages/bambamboole/laravel-lokalise)
![GitHub Actions](https://github.com/bambamboole/laravel-lokalise/actions/workflows/main.yml/badge.svg)

Lokalise states, that they support Laravel translations, but out of the box this is not the case.  
They do not support Laravels placeholders and also not its pluralization. In Laravel it is also
common to use two translation files. Multiple PHP files per locale which contain nested keys and
also one JSON file per locale which uses the base locale as key.  
This package provides a simple way to sync your Laravel translations with Lokalise without changing 
anything in your translations nor in your lokalise settings. It just works!

## How does it work?
The package does a few things to give the best out-of-the-box experience.  
It checks your JSON and PHP translations separately.   
Dotted translation keys will get prefixed by the file name.  
The translations are then processed on the fly to convert placeholders and pluralization to Lokalise compatible formats.  
It then uploads the files to Lokalise.  
Downloading translations works a bit different since Lokalise converts the placeholders to a non-reversible format
when downloading whole files.
Therefor the package makes usage of the translation keys API to fetch keys file per file. Before it dumps
the keys into their respective files, it converts the placeholders back to the Laravel format.

## Installation

You can install the package via composer.

```bash
composer require --dev bambamboole/laravel-lokalise
```

Add the following environment variables to your `.env` file:

```dotenv
LOKALISE_API_TOKEN=your-lokalise-api-token
LOKALISE_PROJECT_ID=your-lokalise-project-id
```

## Usage
The package is still in its early development and therefor pretty opinionated and not very flexible.  

To upload your translations to Lokalise you can run the following command:
```bash 
php artisan lokalise:upload
```

To download your translations from Lokalise you can run the following command:
```bash
php artisan lokalise:download
```

## Known issues
Localise pagination is not working as expected. Therefor we can currently only fetch 500 keys per file.

### Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email manuel@christlieb.eu instead of using the issue tracker.

## Credits

-   [Manuel Christlieb](https://github.com/bambamboole)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

