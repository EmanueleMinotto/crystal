The `limiter` service limits how many times something can happen in a period, like the requests to an API, using the [[Cache]] service.
Hits are counted in fixed windows: the window starts with the first hit and when it ends the counter starts again from zero.

```php
// the cache must be shared between requests, the array driver isn't
$mf('cache')::setDriver('filename', __DIR__.'/cache');
```

## Limiting routes

`$mf('limiter')->middleware($max, $seconds, $key)` is a [[Middleware]] allowing `$max` requests every `$seconds` (by default 60 requests per minute):

```php
$limiter = $mf('limiter');

// 10 searches per minute
$mf->get('/api/search', $mf('middleware')->wrap(function ($request) {
    return ['results' => [], 'query' => $request->query('q')];
}, $limiter->middleware(10, 60)));

// all the routes of a group
$mf('router')->group('/api', function ($route) {
    $route->get('/users', $listUsers);
    $route->get('/posts', $listPosts);
}, [$limiter->middleware(60, 60)]);
```

Responses have the `X-RateLimit-Limit` and `X-RateLimit-Remaining` headers.
When the limit is reached, the route isn't invoked and a 429 response is returned with the `Retry-After` header (seconds before the window ends).
The 429 response is rendered by the [[Errors]] service, so it's JSON when the client asks for it and it can be customized with `setRenderer()`.

By default the requests are counted for each IP address (see `$request->ip()` in [[Request Response]]): behind a proxy or Cloudflare, set the trusted proxies or every client would share the same counter.
The third argument receives the request and returns the key of the counter, e.g. to limit each user of the [[Authentication]]:

```php
$limiter->middleware(1000, 3600, function ($request) use ($mf) {
    $auth = $mf('auth');

    return $auth->check() ? 'user:' . $auth->id() : 'ip:' . $request->ip();
});
```

**Counters are shared by the routes using the same key**, so the limit of a group applies to all its routes together.
To give a route its own limit, add a prefix to the key:

```php
$limiter->middleware(5, 60, function ($request) {
    return 'login:' . $request->ip();
});
```

## Counting hits

The counters can be used directly, e.g. to limit an action instead of a route:

```php
$mf->post('/newsletter', function ($request, $limiter, $errors) {
    $email = $request->input('email', '');

    // 3 subscriptions per day for each email address
    if (!$limiter->attempt('newsletter:'.$email, 3, 86400)) {
        $seconds = $limiter->availableIn('newsletter:'.$email);

        $errors->abort(429, "Retry in $seconds seconds", ['Retry-After' => (string) $seconds]);
    }

    // subscribe...
});
```

```php
$limiter->hit($key, $seconds);         // count a hit, returns the hits of the window
$limiter->hits($key);                  // hits of the current window
$limiter->tooManyAttempts($key, $max);
$limiter->remaining($key, $max);       // hits still allowed
$limiter->availableIn($key);           // seconds before the window ends
$limiter->clear($key);                 // reset the counter
```

`attempt()` counts a hit only when the limit isn't reached yet.

## Limits

Counters are read and written without locks: with many concurrent requests a few more hits than the limit can be allowed.
For failed logins, use the throttling of the [[Authentication]].
