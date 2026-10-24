Welcome to the crystal wiki!

This is a PHP (7.0+) microframework based on [anonymous functions](http://php.net/manual/en/functions.anonymous.php) and [anonymous classes](https://www.php.net/manual/en/language.oop5.anonymous.php).

```php
<?php

$mf = require_once('crystal.php');

$mf('/', function () {
    echo 'Hello World!';
});
```

`$mf` is an instance of an invokable anonymous class, so it can be used anywhere a [`callable`](http://php.net/manual/en/language.types.callable.php) is accepted (but it isn't a `Closure`).

## Features

-   requested URLs matched using regular expressions
-   request methods (matches using regular expressions too)
-   differenced [FIFO](http://en.wikipedia.org/wiki/FIFO) [queues](http://en.wikipedia.org/wiki/Queue_%28abstract_data_type%29) for each `$priority`
-   command line usage
-   integrated [[Dependency Injection]] (based on [PSR-11](https://www.php-fig.org/psr/psr-11/)) and settings system
-   named patterns
-   an [[Autoloader]] based on the [PSR-0](https://www.php-fig.org/psr/psr-0/) standard
-   a [[Template Engine]]
-   a [[Logger]] based on [PSR-3](https://www.php-fig.org/psr/psr-3/)
-   a [[Database]] interaction system
-   database [[Migrations]]
-   a [[Cache]] system with APCu, array, filesystem, database, Memcached and Redis drivers, namespaces and chains
-   a [[Mailer]] to send emails using `mail()` or SMTP
-   a [[Configuration]] system with `.env` files support
-   an [[Event Listener]] to listen and dispatch events
-   HTTP [[Request and Response]] objects, callbacks can return arrays sent as JSON
-   route helpers (`$mf->get()`, `$mf->post()`...), `{id:\d+}` placeholders and groups, see [[CGI Usage]]
-   automatic `404`, `405` and `OPTIONS` responses
-   [[Middleware]] for every request, groups and routes
-   [[Errors]] rendered as [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) Problem Details
-   [[CORS]], [[Authentication]] (JWT, Bearer tokens, API keys) and [[Rate Limiting]] middlewares
-   a [[Validator]] for the request input
-   [[Pagination]] of database tables and arrays
-   HTTP caching with ETag and `304 Not Modified` responses
-   in-process [[Testing]] of routes
-   [[OpenAPI]] document generation
-   **no external dependencies, just a single PHP file!**

New to crystal? Start [[Building a REST API]].
