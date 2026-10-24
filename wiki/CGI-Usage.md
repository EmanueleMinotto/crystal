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

## Method not allowed

When the requested URL matches a route but the request method doesn't, a `405 Method Not Allowed` response with the `Allow` header is sent automatically, and `OPTIONS` requests receive a `204 No Content` response listing the allowed methods.

To customize the 405 response use the `router:method-not-allowed` pattern (the method argument is ignored), allowed methods are returned by `router:allowed-methods`:
```php
$mf($mf('router:method-not-allowed'), function () use ($mf) {
    echo 'Use one of: ' . implode(', ', $mf('router:allowed-methods'));
});
```

Requests not matching any route receive a `404 Not Found` response, see [[Errors]] for the response format.

## Route helpers and placeholders

Routes can be defined with a method helper too, `get` routes match `HEAD` requests as well:
```php
$mf->get('/users', function () { /* ... */ });
$mf->post('/users', function () { /* ... */ });
$mf->put('/users/{id}', function ($id) { /* ... */ });
$mf->patch('/users/{id}', function ($id) { /* ... */ });
$mf->delete('/users/{id}', function ($id) { /* ... */ });
$mf->options('/users', function () { /* ... */ });
$mf->any('/ping', function () { /* ... */ });
$mf->route(['GET', 'POST'], '/search', function () { /* ... */ });
```

`{name}` placeholders match a path segment, `{name:regex}` placeholders use a custom regular expression. Both are converted to named subpatterns, so they work with the `$mf()` function too:
```php
$mf->get('/users/{id:\d+}/posts/{slug}', function ($slug, $id) {
    // ...
});
```

Helpers accept an array of options as last argument:
```php
$mf->get('/users/{id:\d+}', $callback, [
    'priority' => 1,                       // same as the 4th argument of $mf()
    'middleware' => [$auth],               // see Middleware
    'name' => 'getUser',                   // used as OpenAPI operationId
    'openapi' => ['summary' => 'Get user'], // see OpenAPI
]);
```

## Groups

Groups share a prefix, [[Middleware]] and [[OpenAPI]] options, and can be nested:
```php
$mf->group('/api/v1', function ($mf) {
    $mf->get('/users', function () { /* ... */ }); // GET /api/v1/users
}, ['middleware' => [$auth], 'openapi' => ['tags' => ['users']]]);
```

## Returning values

Callbacks can return a value instead of printing the output:

-   arrays, `stdClass` and `JsonSerializable` objects are sent as JSON
-   strings are appended to the output
-   response objects are sent with their status code and headers, see [[Request and Response]]

```php
$mf->get('/users/{id:\d+}', function ($id) {
    return ['id' => (int) $id];
});
// {"id":42}
```

The output of each callback is buffered and sent when all the matched callbacks are executed, status codes and headers set using `http_response_code()` and `header()` still work.
