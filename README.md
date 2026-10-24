# Crystal PHP Microframework ![Continuous Integration](https://github.com/EmanueleMinotto/crystal/workflows/Continuous%20Integration/badge.svg)

This is a PHP (7.0+) microframework based on [anonymous functions](http://php.net/manual/en/functions.anonymous.php) and [anonymous classes](https://www.php.net/manual/en/language.oop5.anonymous.php).

```php
<?php

$mf = require_once('crystal.php');

$mf('/', function () {
    echo 'Hello World!';
});

$mf->get('/users/{id:\d+}', function ($id) {
    return ['id' => (int) $id]; // {"id":42}
});
```

## Features

-   requested URLs matched using regular expressions
-   request methods (matches using regular expressions too)
-   differenced [FIFO](http://en.wikipedia.org/wiki/FIFO) [queues](http://en.wikipedia.org/wiki/Queue_%28abstract_data_type%29) for each `$priority`
-   command line usage
-   integrated [Dependency Injection](https://github.com/EmanueleMinotto/crystal/wiki/Dependency-Injection) (based on [PSR-11](https://www.php-fig.org/psr/psr-11/)) and settings system
-   named patterns
-   an [Autoloader](https://github.com/EmanueleMinotto/crystal/wiki/Autoloader) based on the [PSR-0](https://www.php-fig.org/psr/psr-0/) standard
-   a [Template Engine](https://github.com/EmanueleMinotto/crystal/wiki/Template-Engine)
-   a [Logger](https://github.com/EmanueleMinotto/crystal/wiki/Logger) based on [PSR-3](https://www.php-fig.org/psr/psr-3/)
-   a [Database](https://github.com/EmanueleMinotto/crystal/wiki/Database) interaction system
-   database [Migrations](https://github.com/EmanueleMinotto/crystal/wiki/Migrations)
-   an [Event Listener](https://github.com/EmanueleMinotto/crystal/wiki/Event-Listener) to listen and dispatch events
-   a [Cache](https://github.com/EmanueleMinotto/crystal/wiki/Cache) system with APCu, array, filesystem, database, Memcached and Redis drivers, namespaces and chains
-   a [Mailer](https://github.com/EmanueleMinotto/crystal/wiki/Mailer) to send emails using `mail()` or SMTP
-   a [Configuration](https://github.com/EmanueleMinotto/crystal/wiki/Configuration) system with `.env` files support
-   HTTP [Request and Response](https://github.com/EmanueleMinotto/crystal/wiki/Request-and-Response) objects, callbacks can return arrays sent as JSON
-   route helpers, `{id:\d+}` placeholders and [groups](https://github.com/EmanueleMinotto/crystal/wiki/CGI-Usage)
-   automatic `404`, `405` and `OPTIONS` responses
-   [Middlewares](https://github.com/EmanueleMinotto/crystal/wiki/Middleware) for every request, groups and routes
-   [Errors](https://github.com/EmanueleMinotto/crystal/wiki/Errors) rendered as [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) Problem Details
-   [CORS](https://github.com/EmanueleMinotto/crystal/wiki/CORS), [Authentication](https://github.com/EmanueleMinotto/crystal/wiki/Authentication) (JWT, Bearer tokens, API keys) and [Rate Limiting](https://github.com/EmanueleMinotto/crystal/wiki/Rate-Limiting) middlewares
-   a [Validator](https://github.com/EmanueleMinotto/crystal/wiki/Validator) for the request input
-   [Pagination](https://github.com/EmanueleMinotto/crystal/wiki/Pagination) of database tables and arrays
-   HTTP caching with ETag and `304 Not Modified` responses
-   in-process [Testing](https://github.com/EmanueleMinotto/crystal/wiki/Testing) of routes
-   [OpenAPI](https://github.com/EmanueleMinotto/crystal/wiki/OpenAPI) document generation
-   **no external dependencies, just a single PHP file!**

## Documentation

https://github.com/EmanueleMinotto/crystal/wiki

Building an API? Start from [Building a REST API](https://github.com/EmanueleMinotto/crystal/wiki/Building-a-REST-API).
