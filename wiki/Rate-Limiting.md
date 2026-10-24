The `ratelimit` service creates a [[middleware|Middleware]] limiting the number of requests per client in a time window (fixed window algorithm). Counters are stored using the [[Cache]], so configure a shared driver (APCu, Redis, Memcached, database...) in production.

```php
$mf->middleware($mf('ratelimit')->middleware([
    'limit' => 60,      // requests per window, default 60
    'window' => 60,     // seconds, default 60
    'key' => function ($request) {
        // default: client IP address, null skips the limit
        return $request->attribute('user')['sub'] ?? $request->ip();
    },
    'prefix' => 'api',  // cache keys prefix, default "ratelimit"
]));
```

Responses include the `X-RateLimit-Limit`, `X-RateLimit-Remaining` and `X-RateLimit-Reset` (UNIX timestamp) headers. Clients exceeding the limit receive a `429 Too Many Requests` [[error|Errors]] with the `Retry-After` header.

Different limits can be applied to different routes or [[groups|CGI Usage]] using route middlewares and different prefixes.

Counters can be incremented manually too:
```php
$result = $mf('ratelimit')->hit('login.'.$email, 5, 300);
// ['allowed' => true, 'remaining' => 4, 'reset' => 1767225600]
```

Counter updates aren't atomic, so the limit can be slightly exceeded under heavy concurrency.
