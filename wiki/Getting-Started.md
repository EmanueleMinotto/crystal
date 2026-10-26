This page builds a small application from scratch: a page, a JSON API and a database, in about 40 lines.

## Requirements

-   PHP 7.0 or newer, nothing else: no Composer, no extensions
-   the [PDO](https://www.php.net/manual/en/book.pdo.php) extension with a driver (like `pdo_sqlite` or `pdo_mysql`) to use the [[Database]]

## Installation

Download [`crystal.php`](https://raw.githubusercontent.com/EmanueleMinotto/crystal/master/crystal.php) and create an `index.php` file next to it:

```
my-app/
├── crystal.php
└── index.php
```

```php
<?php

$mf = require __DIR__.'/crystal.php';

$mf->get('/', function () {
    echo 'Hello World!';
});
```

Start the PHP built-in webserver from the `my-app` directory and open http://localhost:8080:

```bash
php -S localhost:8080 index.php
```

The built-in webserver is for development only: on a real server every request has to be sent to `index.php`, see [[Webserver Configuration]].

## 1. Routes and parameters

Each route has a path and a callback, `{name}` is a parameter of the path:

```php
$mf->get('/hello/{name}', function ($name) {
    echo 'Hello '.htmlspecialchars($name).'!';
});
```

```bash
$ curl http://localhost:8080/hello/Alice
Hello Alice!
```

There are `post`, `put`, `patch` and `delete` routes too, see [[Routing]].

## 2. Error pages

Enable the [[Errors]] handling as soon as possible: paths without a route receive a 404 page and exceptions an error page, instead of a blank page.

```php
$mf('errors')->enable();     // in production
$mf('errors')->enable(true); // during development: details of the exceptions are shown
```

## 3. A JSON API

Arrays returned by the callbacks are JSON responses:

```php
$mf->get('/api/status', function () {
    return ['status' => 'ok', 'time' => date('c')];
});
```

```bash
$ curl http://localhost:8080/api/status
{"status":"ok","time":"2026-10-26T10:00:00+00:00"}
```

## 4. A database

Connect the [[Database]] service once, then the callbacks receive it as the `$database` parameter (services are injected by name):

```php
$mf('database')::setPdo(new PDO('sqlite:'.__DIR__.'/notes.sqlite'));
$mf('database')->query('CREATE TABLE IF NOT EXISTS notes (id INTEGER PRIMARY KEY, text TEXT NOT NULL)');

$mf->get('/api/notes', function ($database) {
    return $database->select('notes', '*', [], 'id DESC');
});
```

## 5. Validating the input

`$request->validate()` returns the valid fields, or answers with a 422 response listing the errors (see [[Validation]]):

```php
$mf->post('/api/notes', function ($request, $response, $database) {
    $note = $request->validate([
        'text' => ['required', 'string', 'max' => 200],
    ]);

    $note['id'] = $database->create('notes', $note);

    return $response->created($note, '/api/notes/'.$note['id']);
});
```

```bash
$ curl -X POST -H 'Content-Type: application/json' -d '{"text":"Buy milk"}' http://localhost:8080/api/notes
{"text":"Buy milk","id":1}

$ curl -X POST -H 'Content-Type: application/json' -d '{}' http://localhost:8080/api/notes
{"error":{"status":422,"title":"Unprocessable Content","message":"The given data is invalid.","errors":{"text":["The text field is required."]}}}
```

## 6. An HTML page

The [[Template Engine]] renders PHP files, `$this->e()` escapes the values:

`templates/notes.html.php`:

```html
<!doctype html>
<html>
    <body>
        <h1>Notes</h1>
        <ul>
            <?php foreach ($notes as $note): ?>
                <li><?php echo $this->e($note['text']); ?></li>
            <?php endforeach; ?>
        </ul>
    </body>
</html>
```

```php
$mf->get('/', function ($database, $template) {
    echo $template->render(__DIR__.'/templates/notes.html.php', [
        'notes' => $database->select('notes', '*', [], 'id DESC'),
    ]);
});
```

## The complete application

`index.php`:

```php
<?php

$mf = require __DIR__.'/crystal.php';

$mf('errors')->enable();

$mf('database')::setPdo(new PDO('sqlite:'.__DIR__.'/notes.sqlite'));
$mf('database')->query('CREATE TABLE IF NOT EXISTS notes (id INTEGER PRIMARY KEY, text TEXT NOT NULL)');

$mf->get('/', function ($database, $template) {
    echo $template->render(__DIR__.'/templates/notes.html.php', [
        'notes' => $database->select('notes', '*', [], 'id DESC'),
    ]);
});

$mf->get('/hello/{name}', function ($name) {
    echo 'Hello '.htmlspecialchars($name).'!';
});

$mf->get('/api/notes', function ($database) {
    return $database->select('notes', '*', [], 'id DESC');
});

$mf->post('/api/notes', function ($request, $response, $database) {
    $note = $request->validate([
        'text' => ['required', 'string', 'max' => 200],
    ]);

    $note['id'] = $database->create('notes', $note);

    return $response->created($note, '/api/notes/'.$note['id']);
});
```

## Next steps

-   the [[Cookbook]] has complete applications: an API, a website with login, a contact form, ...
-   [[Routing]], [[Request Response]] and [[Middleware]] describe how requests are handled
-   [[When to use crystal]] explains how to publish the application on a free hosting
