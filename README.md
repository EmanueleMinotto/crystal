# Crystal PHP Microframework ![Continuous Integration](https://github.com/EmanueleMinotto/crystal/workflows/Continuous%20Integration/badge.svg)

This is a PHP (7.0+) microframework based on [anonymous functions](http://php.net/manual/en/functions.anonymous.php) and [anonymous classes](https://www.php.net/manual/en/language.oop5.anonymous.php).

```php
<?php

$mf = require_once('crystal.php');

$mf('/', function () {
    echo 'Hello World!';
});
```

## Features

-   requested URLs matched using regular expressions
-   request methods (matches using regular expressions too)
-   differenced [FIFO](http://en.wikipedia.org/wiki/FIFO) [queues](http://en.wikipedia.org/wiki/Queue_%28abstract_data_type%29) for each `$priority`
-   command line usage
-   integrated [Dependency Injection](https://github.com/EmanueleMinotto/crystal/wiki/Dependency-Injection) (based on [PSR-11](https://www.php-fig.org/psr/psr-11/)) and settings system
-   named patterns, named routes with URL generation and groups of routes
-   an [Autoloader](https://github.com/EmanueleMinotto/crystal/wiki/Autoloader) based on the [PSR-0](https://www.php-fig.org/psr/psr-0/) standard
-   a [Template Engine](https://github.com/EmanueleMinotto/crystal/wiki/Template-Engine)
-   a [Logger](https://github.com/EmanueleMinotto/crystal/wiki/Logger) based on [PSR-3](https://www.php-fig.org/psr/psr-3/)
-   a [Database](https://github.com/EmanueleMinotto/crystal/wiki/Database) interaction system
-   database [Migrations](https://github.com/EmanueleMinotto/crystal/wiki/Migrations)
-   an [Event Listener](https://github.com/EmanueleMinotto/crystal/wiki/Event-Listener) to listen and dispatch events
-   a [Cache](https://github.com/EmanueleMinotto/crystal/wiki/Cache) system with APCu, array, filesystem, database, Memcached and Redis drivers, namespaces and chains
-   a [Mailer](https://github.com/EmanueleMinotto/crystal/wiki/Mailer) to send emails using `mail()` or SMTP
-   a [Configuration](https://github.com/EmanueleMinotto/crystal/wiki/Configuration) system with `.env` files support
-   [Authentication](https://github.com/EmanueleMinotto/crystal/wiki/Authentication) with password hashing, protected routes, gates, throttling, remember-me and API tokens
-   [Request and Response](https://github.com/EmanueleMinotto/crystal/wiki/Request-Response) objects and [Middlewares](https://github.com/EmanueleMinotto/crystal/wiki/Middleware)
-   [Validation](https://github.com/EmanueleMinotto/crystal/wiki/Validation) of the input data
-   [Error handling](https://github.com/EmanueleMinotto/crystal/wiki/Errors) with error pages, 404 and 405 responses
-   [Sessions](https://github.com/EmanueleMinotto/crystal/wiki/Session) with hardened cookies and [CSRF](https://github.com/EmanueleMinotto/crystal/wiki/CSRF) protection
-   **no external dependencies, just a single PHP file!**

## Documentation

https://github.com/EmanueleMinotto/crystal/wiki

-   [Comparison](https://github.com/EmanueleMinotto/crystal/wiki/Comparison) with other frameworks and microframeworks
-   [When to use crystal](https://github.com/EmanueleMinotto/crystal/wiki/When-to-use-crystal), including free hostings like InfinityFree
