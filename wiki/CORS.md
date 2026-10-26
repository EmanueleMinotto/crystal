Crystal can send the [Cross-Origin Resource Sharing](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS) headers, so APIs can be called by JavaScript running on other origins.
CORS is disabled by default, enable it as soon as possible, before any output:

```php
$mf = require __DIR__.'/crystal.php';

$mf('cors')->enable([
    'origins' => ['https://app.example.com'],
    'paths' => '/api/.*',
]);

$mf->get('/api/users', function () {
    return ['users' => []];
});
```

JavaScript running on `https://app.example.com` can now call the API:

```js
const response = await fetch('https://api.example.com/api/users');
const data = await response.json();
```

When the request comes from an allowed origin, the headers are sent with every response of the matching paths: responses returned by the routes, output of callbacks using `echo` and [[Errors]] responses too, so the client can read the error instead of a generic CORS failure.
Requests from other origins receive no CORS headers and the browser blocks them.

## Options

| Option | Default | Description |
| --- | --- | --- |
| `origins` | `[]` | allowed origins, like `https://example.com`, `https://*.example.com` (any subdomain) or `*` (any origin) |
| `methods` | `[]` | allowed methods of the preflight requests, the requested one when empty |
| `headers` | `[]` | allowed request headers of the preflight requests, the requested ones when empty |
| `expose` | `[]` | response headers readable by the client, like `X-Total-Count` |
| `credentials` | `false` | allow cookies and the `Authorization` header |
| `max_age` | `0` | seconds the browser can cache the preflight response, `0` to not send it |
| `paths` | `.*` | regular expression of the paths, relative to the base path like the routes |

Unknown options throw an `InvalidArgumentException`, so typos don't go unnoticed.

### Common configurations

```php
// a public API, readable from any website
$mf('cors')->enable([
    'origins' => ['*'],
    'paths' => '/api/.*',
]);

// a single page application on another domain, using cookies and the Authorization header
$mf('cors')->enable([
    'origins' => ['https://app.example.com', 'https://*.preview.example.com'],
    'credentials' => true,
    'expose' => ['Link', 'X-RateLimit-Remaining'],
    'max_age' => 3600,
    'paths' => '/api/.*',
]);
```

With `credentials`, the `*` origin can't be used by browsers: the origin of the request is sent instead.
`Vary: Origin` is sent when the response depends on the origin, so caches don't share it between origins.

## Preflight requests

Browsers send an `OPTIONS` request before requests that aren't "simple" (like JSON `POST` requests or requests with an `Authorization` header).
There's no need to define `OPTIONS` routes: when CORS is enabled and some routes match the path but not the `OPTIONS` method, the router answers with a `204` response, the `Allow` header and the CORS headers.
When CORS isn't enabled, `OPTIONS` requests are handled like any other method.

Routes matching the `OPTIONS` method are invoked as usual, and when no route matches the path a 404 response is sent (see [[Errors]]).

## Inspecting the headers

```php
$cors = $mf('cors');

$cors->isEnabled();
$cors->isPreflight(); // OPTIONS request with the Origin and Access-Control-Request-Method headers
$cors->headers();     // headers of the current request, name => value
```
