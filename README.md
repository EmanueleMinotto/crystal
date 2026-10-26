# Crystal PHP Microframework ![Continuous Integration](https://github.com/EmanueleMinotto/crystal/workflows/Continuous%20Integration/badge.svg)

A PHP (7.0+) microframework in a single file, with **no external dependencies**: routing, JSON responses, database, validation, authentication, templates, cache, mail and more.

## Installation

Download [`crystal.php`](https://raw.githubusercontent.com/EmanueleMinotto/crystal/master/crystal.php), put it next to your `index.php` and send every request to `index.php` ([webserver configuration](https://github.com/EmanueleMinotto/crystal/wiki/Webserver-Configuration)).

```php
<?php

$mf = require __DIR__.'/crystal.php';

$mf->get('/', function () {
    echo 'Hello World!';
});
```

```bash
php -S localhost:8080 index.php
```

## Example

```php
<?php

$mf = require __DIR__.'/crystal.php';

$mf('errors')->enable();
$mf('database')::setPdo(new PDO('sqlite:'.__DIR__.'/app.sqlite'));

// GET /api/posts/42 → {"id":"42","title":"..."}
$mf->get('/api/posts/{id:[0-9]+}', function (int $id, $database, $errors) {
    $post = $database->read('posts', $id);

    if (is_null($post)) {
        $errors->abort(404, 'Post not found');
    }

    return $post; // arrays are JSON responses
});

// POST /api/posts → 201, or 422 with the validation errors
$mf->post('/api/posts', function ($request, $response, $database) {
    $post = $request->validate([
        'title' => ['required', 'string', 'max' => 100],
    ]);

    $post['id'] = $database->create('posts', $post);

    return $response->created($post, '/api/posts/'.$post['id']);
});
```

## Documentation

The [wiki](https://github.com/EmanueleMinotto/crystal/wiki) describes every feature with examples:

-   [Getting Started](https://github.com/EmanueleMinotto/crystal/wiki/Getting-Started): a small application from scratch
-   [Cookbook](https://github.com/EmanueleMinotto/crystal/wiki/Cookbook): a JSON API, a website with login, a contact form and more
-   [Routing](https://github.com/EmanueleMinotto/crystal/wiki/Routing), [Request and Response](https://github.com/EmanueleMinotto/crystal/wiki/Request-Response), [Middleware](https://github.com/EmanueleMinotto/crystal/wiki/Middleware), [Errors](https://github.com/EmanueleMinotto/crystal/wiki/Errors), [CORS](https://github.com/EmanueleMinotto/crystal/wiki/CORS)
-   [Database](https://github.com/EmanueleMinotto/crystal/wiki/Database), [Migrations](https://github.com/EmanueleMinotto/crystal/wiki/Migrations), [Pagination](https://github.com/EmanueleMinotto/crystal/wiki/Pagination), [Validation](https://github.com/EmanueleMinotto/crystal/wiki/Validation), [Cache](https://github.com/EmanueleMinotto/crystal/wiki/Cache)
-   [Authentication](https://github.com/EmanueleMinotto/crystal/wiki/Authentication), [Session](https://github.com/EmanueleMinotto/crystal/wiki/Session), [CSRF](https://github.com/EmanueleMinotto/crystal/wiki/CSRF), [Rate Limiting](https://github.com/EmanueleMinotto/crystal/wiki/Rate-Limiting)
-   [Template Engine](https://github.com/EmanueleMinotto/crystal/wiki/Template-Engine), [Mailer](https://github.com/EmanueleMinotto/crystal/wiki/Mailer), [Logger](https://github.com/EmanueleMinotto/crystal/wiki/Logger), [Event Listener](https://github.com/EmanueleMinotto/crystal/wiki/Event-Listener), [Configuration](https://github.com/EmanueleMinotto/crystal/wiki/Configuration), [Dependency Injection](https://github.com/EmanueleMinotto/crystal/wiki/Dependency-Injection)
-   [When to use crystal](https://github.com/EmanueleMinotto/crystal/wiki/When-to-use-crystal) (free hostings like InfinityFree included) and the [Comparison](https://github.com/EmanueleMinotto/crystal/wiki/Comparison) with other frameworks
