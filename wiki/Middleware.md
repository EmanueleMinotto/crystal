Middlewares run code before and after a route callback, or return a different response instead of invoking it.
A middleware receives the [[request|Request Response]] and the next handler, and returns a response:

```php
$securityHeaders = function ($request, callable $next) {
    $response = $next(); // invokes the callback

    return $response
        ->setHeader('X-Frame-Options', 'DENY')
        ->setHeader('X-Content-Type-Options', 'nosniff');
};

$mf->get('/', $mf('middleware')->wrap(function () {
    echo 'Home';
}, $securityHeaders));
```

## Using middlewares

`$mf('middleware')->wrap($callback, ...$middlewares)` returns the wrapped callback, the first middleware is the outermost:

```php
$middleware = $mf('middleware');

$mf->get('/posts/{id:[0-9]+}', $middleware->wrap(function (int $id, $database) {
    return $database->read('posts', $id);
}, $maintenance, $securityHeaders));
```

Route parameters and injected services keep working, and route parameters are available in middlewares with `$request->param('id')`.
Wrapped callbacks can be wrapped again.

To share middlewares between routes, use a group (see [[Routing]]):

```php
$mf('router')->group('/admin', function ($route) {
    $route->get('', $dashboard);
    $route->get('/users', $listUsers);
}, [$securityHeaders, $mf('auth')->middleware('admin')]);
```

## Examples

### Maintenance mode

Returning a response without calling `$next()` stops the request: the callback isn't invoked.

```php
$maintenance = function ($request, callable $next) use ($mf) {
    if (is_file(__DIR__.'/maintenance.flag')) {
        return $mf('response')->setStatus(503)->setHeader('Retry-After', '600')->setBody('Back soon!');
    }

    return $next();
};
```

### Response time

```php
$timing = function ($request, callable $next) {
    $start = microtime(true);
    $response = $next();

    return $response->setHeader('Server-Timing', sprintf('app;dur=%.1f', (microtime(true) - $start) * 1000));
};
```

### Sharing values with the callback

```php
$currentTeam = function ($request, callable $next) use ($mf) {
    $team = $mf('database')->read('teams', ['slug' => $request->param('team')]);

    if (is_null($team)) {
        $mf('errors')->abort(404, 'Team not found');
    }

    $request->setAttribute('team', $team);

    return $next();
};

$mf->get('/teams/{team}/members', $mf('middleware')->wrap(function ($request, $database) {
    $team = $request->attribute('team');

    return $database->select('members', '*', ['team_id' => $team['id']]);
}, $currentTeam));
```

## Built-in middlewares

```php
$mf('auth')->middleware();            // 401 for guests
$mf('auth')->middleware('post:edit'); // 403 when the gate denies, the gate receives the user and the request
$mf('csrf')->middleware();            // 403 for invalid CSRF tokens
$mf('limiter')->middleware(60, 60);   // 429 after 60 requests per minute for each IP address

$mf->post('/posts/{id:[0-9]+}', $mf('middleware')->wrap(
    $update,
    $mf('csrf')->middleware(),
    $mf('auth')->middleware('post:edit')
));
```

See [[Authentication]], [[CSRF]] and [[Rate Limiting]].

## The response of `$next()`

`$next()` always returns a response: when the callback uses `echo` instead of returning a response, its output becomes the body of a response, so middlewares can change it.

When the callback doesn't return a response and the middlewares return the one of `$next()`, the next matching routes are still invoked, like for callbacks without middlewares.
When a middleware returns another response (like `$maintenance` above), it's the response of the route: the next matching routes aren't invoked (see [[Request Response]]).
