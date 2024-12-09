# Crystal PHP Microframework ![Continuous Integration](https://github.com/EmanueleMinotto/crystal/workflows/Continuous%20Integration/badge.svg)

This is a PHP (5.3+) microframework based on [anonymous functions](http://php.net/manual/en/functions.anonymous.php) and [anonymous classes](https://www.php.net/manual/en/language.oop5.anonymous.php).

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
-   named patterns
-   an [Autoloader](https://github.com/EmanueleMinotto/crystal/wiki/Autoloader) based on the [PSR-0](https://www.php-fig.org/psr/psr-0/) standard
-   a [Template Engine](https://github.com/EmanueleMinotto/crystal/wiki/Template-Engine)
-   a [Logger](https://github.com/EmanueleMinotto/crystal/wiki/Logger) available from PHP 5, based on [PSR-3](https://www.php-fig.org/psr/psr-3/)
-   a [Database](https://github.com/EmanueleMinotto/crystal/wiki/Database) interaction system
-   an [Event Listener](https://github.com/EmanueleMinotto/crystal/wiki/Event-Listener) to listen and dispatch events
-   **no external dependencies, just a single PHP file!**

## Documentation

https://github.com/EmanueleMinotto/crystal/wiki
