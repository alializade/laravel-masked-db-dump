# Laravel Masked DB Dump

This package is a fork of [masked-db-dump](https://github.com/beyondcode/laravel-masked-db-dump), A database dumping package that allows you to replace and mask columns while dumping your database.

## Installation

You can install the package via composer:

```bash
composer require alializade/laravel-masked-db-dump
```

## Usage

Use this dump schema definition to remove, replace or mask certain parts of your database tables.


```php
use AliAlizade\LaravelMaskedDumper\DumpSchema;
use AliAlizade\LaravelMaskedDumper\TableDefinitions\TableDefinition;
use Faker\Generator as Faker;

class CoreServiceProvider extends ServiceProvider
{
    // ...
    public function boot(): void
    {
        //... 
        
        $this->app->singleton('masked_dump_default', function () {
            return DumpSchema::define('mysql')
                ->allTables()
                ->table('users', function (TableDefinition $table) {
                    $table->replace('name', function (Faker $faker) {
                        return $faker->name;
                    });
                    $table->replace('email', function (Faker $faker) {
                        return $faker->lastName().'@fake.com';
                    });
                    $table->replace('password', function (Faker $faker) {
                        return $password = bcrypt('secret');
                    });
                })
                ->schemaOnly('personal_access_tokens')
        });
        // ...
}

```


    $ php artisan db:masked-dump output.sql

    $ php artisan db:masked-dump output.sql --gzip

## Documentation

The documentation can be found on [the website](https://beyondco.de/docs/laravel-masked-db-dump).

### Security

If you discover any security related issues, please email ali.alizade@outlook.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
