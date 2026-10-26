Routes connect a path to a callback. Every request is sent to `index.php` by the webserver (see [[Webserver Configuration]]), and crystal invokes the callbacks of the routes matching it.

```php
<?php

$mf = require __DIR__.'/crystal.php';

$mf->get('/', function () {
    echo 'Hello World!';
});

$mf->get('/hello/{name}', function ($name) {
    echo 'Hello '.$name.'!';
});

// GET /hello/Bob
// Hello Bob!
```

## Request methods

```php
$mf->get('/posts', $list);
$mf->post('/posts', $create);
$mf->put('/posts/{id}', $replace);
$mf->patch('/posts/{id}', $update);
$mf->delete('/posts/{id}', $remove);
```

`$mf($path, $callback, $methods)` accepts a regular expression of the methods, `GET` when it's missing:

```php
$mf('/search', $search, 'GET|POST');
$mf('/webhook', $receive, '.*'); // any method
```

The callback can be any [callable](https://www.php.net/manual/en/language.types.callable.php):

```php
$mf->get('/', function () { /* ... */ });
$mf->get('/', 'function_name');
$mf->get('/', [$object, 'method']);
$mf->get('/', 'Class::staticMethod');
$mf->get('/', $object); // with an __invoke method
```

## Route parameters

`{name}` matches a segment of the path, `{name:pattern}` uses its own regular expression.
Parameters are passed to the callback by name:

```php
$mf->get('/users/{id:[0-9]+}/posts/{slug}', function ($slug, $id) {
    echo "Post $slug of the user $id";
});

// GET /users/42/posts/hello-world
// Post hello-world of the user 42
```

Values are strings, typed parameters receive them converted by PHP:

```php
$mf->get('/users/{id:[0-9]+}', function (int $id) {
    var_dump($id); // int(42)
});
```

Optional parts are regular expressions too, the parameter receives its default value when the part is missing:

```php
$mf->get('/blog(?:/page/{page:[0-9]+})?', function (int $page = 1) {
    echo 'Page '.$page;
});

// GET /blog         → Page 1
// GET /blog/page/3  → Page 3
```

## Returning a response

A callback can print the page, return an array (a JSON response) or return a [[response|Request Response]]:

```php
$mf->get('/', function () {
    echo '<h1>Home</h1>';
});

$mf->get('/api/users/{id:[0-9]+}', function (int $id) {
    return ['id' => $id, 'name' => 'Alice'];
});

$mf->get('/old', function ($response) {
    return $response->redirect('/new', 301);
});
```

Other returned values, like strings, are ignored: use `echo` for them.

## Services as parameters

Parameters without a value from the path receive the service with the same name, like `$request`, `$response` or `$database`.
Parameters typed with a class receive an instance made by the [[Dependency Injection]] container:

```php
$mf->post('/posts/{id:[0-9]+}/comments', function (int $id, $request, $database) {
    $data = $request->validate(['text' => ['required', 'string']]);

    $database->create('comments', ['post_id' => $id, 'text' => $data['text']]);

    return ['saved' => true];
});

$mf->get('/posts', function (PostRepository $posts) {
    return $posts->latest();
});
```

So `use ($mf)` isn't needed in the callbacks, it can still be used:

```php
$mf->get('/posts', function () use ($mf) {
    return $mf('database')->select('posts');
});
```

## Not found pages

Enable the [[Errors]] handling: requests not matching any route receive a 404 page, or a 405 page when only the method doesn't match.

```php
$mf('errors')->enable();

$mf->get('/', function () {
    echo 'Home';
});

// GET /missing → 404 Not Found
// POST /       → 405 Method Not Allowed
```

Custom error pages are described in [[Errors]].

## Named routes and URLs

Routes can be named with the fourth argument of the shortcuts (the fifth one of `$mf()`), and their URLs generated with the `router` service:

```php
$mf->get('/posts/{id:[0-9]+}', $show, 0, 'posts.show');

$router = $mf('router');

$router->url('posts.show', ['id' => 42]);              // "/posts/42"
$router->url('posts.show', ['id' => 42, 'page' => 2]); // "/posts/42?page=2"
$router->has('posts.show');                            // true
```

On PHP 8, named arguments skip the priority:

```php
// PHP 8.0+
$mf->get('/posts/{id:[0-9]+}', $show, name: 'posts.show');
```

-   the base path of the application (the directory of `index.php`) is added
-   parameters not used by the route are added to the query string
-   values are URL encoded and must match their pattern, otherwise an `InvalidArgumentException` is thrown
-   names must be unique, defining a name twice throws an `InvalidArgumentException`

URLs can be generated for routes made of literal characters, escaped characters (like `\.`) and parameters.
For routes using other regular expressions (like `.*`, alternatives or optional parts) `url()` throws a `LogicException`, but they can still be named.

## Groups

Groups share a prefix of the path, a prefix of the names and the [[Middleware]]:

```php
$mf('router')->group('/admin', function ($route) {
    $route->get('/users', $listUsers, 0, 'users');      // "/admin/users", named "admin.users"
    $route->post('/users/{id:[0-9]+}', $updateUser);

    // nested groups: prefixes are concatenated
    $route->group('/reports', function ($route) {
        $route->get('/daily', $daily, 0, 'daily');       // "/admin/reports/daily", named "admin.reports.daily"
    }, [], 'reports.');
}, [$mf('auth')->middleware('admin')], 'admin.');
```

`$route` has the same methods of `$mf` (`get`, `post`, `put`, `patch`, `delete`, `group` and `$route($path, $callback, $methods, $priority, $name)`).
The middlewares of the outer groups run first.

## Multiple matching routes

Routes are regular expressions and, unlike most frameworks, **every route matching the request is invoked**, in the order they're defined:

```php
$mf->get('/hello/world', function () {
    echo 'Hello ';
});

$mf->get('/hello(.*)', function () {
    echo 'World!';
});

// GET /hello/world
// Hello World!
```

-   a callback returning a response (or an array) stops the next ones, see [[Request Response]]
-   [`exit()`](https://www.php.net/manual/en/function.exit.php) stops the next callbacks too

### Only the first route

With the first match mode only the first matching route (by priority, then in the order they're defined) is invoked, like in most frameworks:

```php
$mf('router')->firstMatch();

$mf->get('/users/new', function () {
    echo 'New user form';
});

$mf->get('/users/{id}', function ($id) {
    echo 'User '.$id;
});

// GET /users/new → New user form (without the first match mode: New user formUser new)
```

### Priorities

The priority (third argument of the shortcuts, fourth of `$mf()`) changes the order: `0` (the default) runs first, then `1`, `2`, and so on.

```php
$mf->get('/', function () { echo 'A'; }, 1);
$mf->get('/', function () { echo 'B'; }, 0);

// Output: BA
```

## Details

### Regular expressions

The path is a regular expression matched against the whole requested path (without the query string).
Placeholders are a shorter syntax for [named subpatterns](https://www.php.net/manual/en/regexp.reference.subpatterns.php), these routes are the same:

```php
$mf->get('/users/{id:[0-9]+}/posts/{slug}', $callback);
$mf->get('/users/(?P<id>[0-9]+)/posts/(?P<slug>[^/]+)', $callback);
```

Quantifiers (like `[0-9]{2}`) and escaped braces (`\{`) aren't placeholders.
Values of subpatterns without a name are passed in order, as the first parameters.

### Parameters conversion

When PHP can't convert a value to the type of the parameter:

-   empty values (like an optional part not matched) receive the default value of the parameter, or `null` when the parameter accepts it
-   other values (like `abc` for `int $id`) are a 404 response when the [[Errors]] handling is enabled

### Injected services

-   only objects are injected: services, factories without arguments (like `response`, a new response each time) and objects set with `$mf('name', $object)`, settings aren't
-   values from the path are never replaced: parameters without a value and without a service receive `null` or their default value

### The `router:not-found` pattern

Without the [[Errors]] handling, `$mf('router:not-found')` returns a regular expression matching the paths not matched by the routes defined **before** it:

```php
$mf->get('/', function () {
    echo 'Home';
});

$mf($mf('router:not-found'), function () {
    http_response_code(404);
    echo 'Page not found';
}, '.*');
```

With the [[Errors]] handling enabled, a route using it replaces the automatic 404 response.
