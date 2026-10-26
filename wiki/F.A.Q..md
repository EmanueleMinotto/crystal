## I get a blank page

Enable the [[Errors]] handling with the debug mode, the page will show the exception:

```php
$mf('errors')->enable(true);
```

Never use the debug mode on a public website: use `$mf('errors')->enable()` there.

## Every page except the home is a 404 of the webserver

The webserver has to send every request to `index.php`, see [[Webserver Configuration]].
With Apache, check that `mod_rewrite` is enabled and that the `.htaccess` file is uploaded (files starting with a dot are often hidden by FTP clients).

## Can I use crystal in a subdirectory?

Yes: the routes are relative to the directory of `index.php`, so `$mf->get('/about', ...)` matches `/my-app/about` when the application is in `/my-app/`.
With Apache, set `RewriteBase /my-app` in the `.htaccess` file.

## My callback returns a string but the page is empty

Returned strings are ignored: print them with `echo`, or return an array (a JSON response) or a [[response|Request Response]].

```php
$mf->get('/', function () {
    echo 'Hello';
});
```

## Two routes are invoked for the same request

Every route matching the request is invoked, see [Multiple matching routes](Routing#multiple-matching-routes).
Returning a response (or an array) stops the next callbacks.

## "Cannot modify header information - headers already sent"

Something was printed before the routes: an `echo` outside the callbacks, spaces or empty lines before `<?php`, or a [BOM](https://en.wikipedia.org/wiki/Byte_order_mark) at the beginning of a file.
The output of the route callbacks is buffered, so headers, cookies and sessions can be used anywhere in them.

## `$mf(function () { ... })` doesn't work in my website

A callback without a path is a command line script, see [[CLI Usage]].
In a website, configure the services at the beginning of `index.php` and use them in the routes:

```php
$mf('database')::setPdo(new PDO('sqlite:'.__DIR__.'/app.sqlite'));

$mf->get('/posts', function ($database) {
    return $database->select('posts');
});
```

## Can I use Composer packages?

Yes, require the Composer autoloader together with crystal:

```php
require __DIR__.'/vendor/autoload.php';

$mf = require __DIR__.'/crystal.php';
```

## How can I use crystal from other files?

`$mf` can be passed around like any variable, or wrapped in a function (PHP documents [`main`](https://www.php.net/manual/en/function.main.php) as a dummy function, so the name is free):

```php
if (!function_exists('main')) {
    function main() {
        static $mf;

        if (is_null($mf)) {
            $mf = require __DIR__.'/crystal.php';
        }

        return call_user_func_array($mf, func_get_args());
    }
}

main('/', function () {
    echo 'Hello World!';
});

$database = main('database');
```

## Which PHP versions are supported?

Every version from PHP 7.0 to PHP 8.5, all of them are tested by the continuous integration.

## There's a bug or I have a suggestion

Report it or fix it: read the [contributing guidelines](https://github.com/EmanueleMinotto/crystal/blob/master/.github/CONTRIBUTING.md) and open a pull request. (Thanks)

Following the original idea, nothing was defined in the test environment: the framework is tested using GitHub Actions and [phpt files](https://qa.php.net/phpt_details.php) through PHPUnit.
The examples of the [[Getting Started]] and [[Cookbook]] pages are tested too.

![Continuous Integration](https://github.com/EmanueleMinotto/crystal/workflows/Continuous%20Integration/badge.svg)
