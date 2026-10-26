Crystal is a PHP (7.0+) microframework in a single file, without dependencies: copy `crystal.php` next to your `index.php` and start writing routes.

```php
<?php

$mf = require __DIR__.'/crystal.php';

$mf->get('/', function () {
    echo 'Hello World!';
});

$mf->get('/api/users/{id:[0-9]+}', function (int $id, $database) {
    return $database->read('users', $id); // arrays are JSON responses
});
```

`$mf` defines the routes (`$mf->get()`, `$mf->post()`, ...) and returns the services (`$mf('database')`, `$mf('auth')`, ...), which the callbacks can also receive as parameters.

## Where to start

-   [[Getting Started]]: from an empty directory to a small application with a JSON API, a database and an HTML page
-   [[Cookbook]]: complete applications to copy, like a JSON API, a website with login and a contact form
-   [[Routing]]: paths, parameters, methods, groups and named routes

## Features

| Area | Pages |
| --- | --- |
| Routing and HTTP | [[Routing]] (regular expressions, placeholders, groups, named routes), [[Request Response]] (JSON, `201`/`204` helpers, `ETag`), [[Middleware]], [[Errors]] (error pages, 404, 405, Problem Details), [[CORS]], [[CLI Usage]] |
| Services | [[Dependency Injection]] (PSR-11-like container with autowiring) and settings, [[Configuration]] (`.env` files), [[Event Listener]], [[Logger]] (PSR-3-like), [[Autoloader]] (PSR-0) |
| Data | [[Database]] (PDO CRUD helpers, transactions), [[Migrations]], [[Pagination]] (with the `Link` header), [[Validation]] (nested fields too), [[Cache]] (APCu, array, filesystem, database, Memcached, Redis, chains) |
| Pages and emails | [[Template Engine]] (plain PHP, layouts and sections), [[Mailer]] (`mail()` or SMTP) |
| Security | [[Session]] (hardened cookies), [[Authentication]] (password hashing, gates, throttling, remember-me, API tokens), [[CSRF]], [[Rate Limiting]] |

## Choosing crystal

-   [[When to use crystal]]: good and bad fits, and how to use it on InfinityFree and other free or limited hostings
-   [[Comparison]] with Slim, Flight, Fat-Free, Leaf, Laravel, Symfony and Laminas/Mezzio
-   [[Webserver Configuration]] for Apache, nginx, IIS, lighttpd and the PHP built-in webserver
