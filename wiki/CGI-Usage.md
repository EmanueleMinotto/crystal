As visible in this wiki's home, the CGI usage is pretty simple, here how to print a "Hello World!" phrase in the browser:

```php
$mf = require_once('crystal.php');

$mf('/', function () {
    echo 'Hello World!';
});
```

second argument must be [callable](http://php.net/manual/en/language.types.callable.php), so you can use
```php
$mf('/', function () { /* ... */ });
$mf('/', 'function');
$mf('/', array($object, 'method'));
$mf('/', array('Class', 'staticMethod'));
$mf('/', 'Class::staticMethod');
$mf('/', $object); // Used with __invoke
```

HTTP request methods are the third parameter and are controlled using a regular expression.
```php
$mf('/', function () {
    echo 'Hello ' . $_POST['name'] . '!';
}, 'POST');
```

```php
$mf('/', function () {
    echo 'Hello ' . $_SERVER['REQUEST_METHOD'] . '!';
}, 'GET|POST');
```

Routes are defined using a regular expression too.
```php
$mf('/hello/world', function () {
    echo 'Hello ';
});
$mf('/hello(.*)', function () {
    echo 'World!';
});
// Output: Hello World!
```

Use [named subpatterns](http://php.net/manual/en/regexp.reference.subpatterns.php) to define parameters order.
```php
$mf('/(?P<user>[^\/]+)/(?P<verb>[^\/]+)', function ($verb, $user) {
    echo 'Output: ' . $verb . ' ' . $user . '!';
});

// http://localhost/bob/hello
// Output: hello bob!
```

Callbacks can also return a response and read the request, see [[Request Response]] and [[Middleware]].
```php
$mf('/api/users/(?P<id>[0-9]+)', function ($id) use ($mf) {
    return $mf('response')->json(['id' => (int) $id]);
});
```

**Attention:** don't forget to configure your [[webserver|Webserver Configuration]]!

## Priorities

Priority is used to set callbacks order, each function is called using highest priority 0 but you can set it passing a not negative integer or a not negative float number.

```php
// $mf('/', function () { echo 'A'; });
$mf('/', function () { echo 'A'; }, 'GET', 0);
$mf('/', function () { echo 'B'; }, 'GET', 1);

// Output: AB
```

```php
$mf('/', function () { echo 'A'; }, 'GET', 1);
$mf('/', function () { echo 'B'; }, 'GET', 0);

// Output: BA
```

In command line usage priority is the second parameter.

To break callbacks chain use the [`exit()`](http://it2.php.net/manual/en/function.exit.php) function.


## Page not found

With the [[Errors]] handling enabled, requests not matching any route receive a 404 (or 405) response automatically.

The [HTTP 404 error](https://en.wikipedia.org/wiki/HTTP_404) is defined as a regular expression too and is matched if no one of **currently** defined regular expressions match current URL.
To retrieve the 404 regular expression call the function with the argument `router:not-found` (available only if not in CLI mode).
```php
$mf('/', function () {
    echo 'Hello World!';
});

$mf($mf('router:not-found'), function () {
    echo 'Error 404: Page not Found';
});
```

## Named routes and URLs

Routes can be named using the fifth argument, or with `$mf('router')->route()`, and their URLs can be generated:

```php
$router = $mf('router');

$mf('/posts/(?P<id>[0-9]+)', function ($id) { /* ... */ }, 'GET', 0, 'posts.show');
// or
$router->route('posts.show', '/posts/(?P<id>[0-9]+)', function ($id) { /* ... */ });

$router->url('posts.show', ['id' => 42]);              // "/posts/42"
$router->url('posts.show', ['id' => 42, 'page' => 2]); // "/posts/42?page=2"
$router->has('posts.show');                            // true
```

-   the base path of the application (the directory of `SCRIPT_NAME`) is added
-   values are URL encoded and must match their subpattern, otherwise an `InvalidArgumentException` is thrown
-   parameters not used by the route are added to the query string
-   names must be unique, defining a name twice throws an `InvalidArgumentException`

URLs can be generated only for routes made of literal characters, escaped characters (like `\.`) and named subpatterns: for routes using other constructs (like `.*`, alternatives, optional groups or `\d` outside a subpattern) `url()` throws a `LogicException`.
Such routes can still be named and used.

## Groups

Groups share a prefix of the path, a prefix of the names and [[Middleware]] (the first one is the outermost):

```php
$router->group('/admin', function ($route) {
    $route('/users', $listUsers, 'GET', 0, 'users');                 // "/admin/users", named "admin.users"
    $route->route('users.update', '/users/(?P<id>[0-9]+)', $update, 'POST');

    // nested groups: prefixes are concatenated, the outer middlewares are the outermost
    $route->group('/reports', function ($route) {
        $route('/daily', $daily, 'GET', 0, 'daily');                  // named "admin.reports.daily"
    }, [$cache], 'reports.');
}, [$auth->middleware('admin'), $csrf->middleware()], 'admin.');
```

`$route` accepts the same arguments of `$mf` (with the name as fifth argument), routes in groups work like the other ones: priorities, multiple matches and `router:not-found` aren't changed.
