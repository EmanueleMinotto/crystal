Middlewares are callables receiving the [[request|Request and Response]] and the next handler, they can modify the request, return a response before reaching the route callback or modify the returned response.

```php
$timer = function ($request, callable $next) {
    $start = microtime(true);
    $response = $next($request);

    return $response->withHeader('X-Response-Time', round((microtime(true) - $start) * 1000).'ms');
};
```

Like route callbacks, middlewares can return arrays (sent as JSON) or response objects:
```php
$admin = function ($request, callable $next) use ($mf) {
    if ('admin' !== $request->attribute('role')) {
        return $mf('response')->problem(403, 'Admins only.');
    }

    return $next($request);
};
```

## Global middlewares

Global middlewares wrap every request, including the ones not matching any route (404, 405 and automatic `OPTIONS` responses). The first defined is the outer one.
```php
$mf->middleware($timer);
$mf->middleware($mf('cors')->middleware());
```

## Route and group middlewares

```php
$mf->get('/admin', $callback, ['middleware' => [$admin]]);

$mf->group('/admin', function ($mf) {
    // ...
}, ['middleware' => [$auth, $admin]]);
```

Group middlewares run before the route ones.

## Built-in middlewares

-   [[CORS]]: `$mf('cors')->middleware($options)`
-   [[Authentication]]: `$mf('auth')->bearer($secret)` and `$mf('auth')->apiKey($keys)`
-   [[Rate Limiting]]: `$mf('ratelimit')->middleware($options)`
-   HTTP caching: `$mf('response')->conditional()`, see [[Request and Response]]

Exceptions thrown by middlewares are rendered by the [[Errors]] service.
