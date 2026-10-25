Middlewares wrap route callbacks to run code before and after them, or to return a different response.
A middleware receives the [[request|Request Response]] and the next handler, and returns a response:

```php
$securityHeaders = function ($request, callable $next) {
    $response = $next();

    return $response
        ->setHeader('X-Frame-Options', 'DENY')
        ->setHeader('X-Content-Type-Options', 'nosniff');
};

$maintenance = function ($request, callable $next) use ($mf) {
    if (is_file('/tmp/maintenance')) {
        // the callback isn't invoked
        return $mf('response')->setStatus(503)->setBody('Back soon!');
    }

    return $next();
};
```

## Wrapping callbacks

`$mf('middleware')->wrap($callback, ...$middlewares)` returns the wrapped callback, the first middleware is the outermost:

```php
$middleware = $mf('middleware');

$mf('/posts/(?P<id>[0-9]+)', $middleware->wrap(function ($id) {
    echo 'Post ' . $id;
}, $maintenance, $securityHeaders));
```

Named route parameters keep working, and they're available in middlewares with `$request->param('id')`.
Wrapped callbacks can be wrapped again.

To share middlewares between routes, use a group (see [[CGI Usage]]):

```php
$mf('router')->group('/admin', function ($route) {
    $route('', function () { /* ... */ });
    $route('/users', function () { /* ... */ });
}, [$securityHeaders, $auth->middleware('admin')]);
```

## The response of `$next()`

`$next()` always returns a response: when the callback uses `echo` instead of returning a response, its output becomes the body of a response, so middlewares can change it.

When the callback doesn't return a response and the middlewares return the one of `$next()`, the next matching routes are still invoked, like for callbacks without middlewares.
When a middleware returns another response (like `$maintenance` above), it's the response of the route: the next matching routes aren't invoked (see [[Request Response]]).

## Built-in middlewares

```php
$auth->middleware();             // 401 for guests
$auth->middleware('post:edit');  // 403 when the gate denies, the gate receives the user and the request
$csrf->middleware();             // 403 for invalid CSRF tokens
$limiter->middleware(60, 60);    // 429 after 60 requests per minute for each IP address

$mf('/posts/(?P<id>[0-9]+)', $middleware->wrap($update, $csrf->middleware(), $auth->middleware('post:edit')), 'POST');
```

See [[Authentication]], [[CSRF]] and [[Rate Limiting]].
