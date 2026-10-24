The `cors` service creates a [[middleware|Middleware]] implementing [Cross-Origin Resource Sharing](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS): preflight requests receive a `204 No Content` response, the other requests are dispatched and CORS headers are added to their responses.

```php
$mf->middleware($mf('cors')->middleware([
    'origins' => ['https://app.example.com', 'https://*.example.org'], // default '*'
    'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    'headers' => ['Content-Type', 'Authorization'], // default: the requested ones
    'expose' => ['X-Total-Count'],
    'credentials' => true,                          // default false
    'max_age' => 3600,                              // preflight cache, default 600
]));
```

Requests without the `Origin` header or from origins not allowed are dispatched without CORS headers.

Use it as a global middleware, so preflight requests are handled even when no `OPTIONS` route is defined.
