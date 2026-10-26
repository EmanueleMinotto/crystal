Crystal can handle errors for you: exceptions become error pages, PHP warnings become exceptions and requests not matching any route receive a 404 (or 405) page.
The handling is disabled by default, enable it at the beginning of `index.php`:

```php
$mf = require __DIR__.'/crystal.php';

$mf('errors')->enable();      // production
$mf('errors')->enable(true);  // debug mode: details of the exceptions are shown, never use it in production
```

For example, with the [[Configuration]]:

```php
$mf('errors')->enable((bool) $mf('config')->env('APP_DEBUG', false));
```

## Stopping a request with an error

`abort()` stops the request with an error response:

```php
$mf->get('/posts/{id:[0-9]+}', function (int $id, $database, $errors) {
    $post = $database->read('posts', $id);

    if (is_null($post)) {
        $errors->abort(404, 'Post not found');
    }

    return $post;
});

$mf('errors')->abort(403);
$mf('errors')->abort(429, 'Slow down', ['Retry-After' => '60']);
```

Any exception with a `getStatusCode()` method (and optionally `getHeaders()`), like the Symfony HTTP exceptions, is handled the same way.
Exceptions with a `getErrors()` method, like the ones thrown by the [[Validation]], list their errors in the response.

## Exceptions

When a route callback throws an exception:

-   the output of the callbacks is discarded and an error response is sent, the next callbacks aren't invoked
-   exceptions with a 5xx status code (any exception without a status code is a 500) are reported, by default to the [[Logger]]
-   the response is JSON when the client asks for it (`Accept` header or JSON body) or for the paths of the `json` option, an HTML page otherwise

Exceptions thrown outside the route callbacks are handled too.

In debug mode the response contains the exception class, message, file, line and stack trace.
In production only the status is shown, plus the message of 4xx errors thrown with `abort()`:

```json
{"error":{"status":404,"title":"Not Found","message":"Post not found"}}
```

## Not found and method not allowed

When no route matches the request, a 404 response is sent.
When some routes match the path but not the method, a 405 response is sent, with the `Allow` header when the methods are simple lists like `GET|POST`.

```php
$mf->get('/contact', $showForm);
$mf->post('/contact', $send);

// GET /missing  → 404 Not Found
// PUT /contact  → 405 Method Not Allowed, Allow: GET, POST
```

When [[CORS]] is enabled, `OPTIONS` requests are an exception: they receive a 204 response with the `Allow` header.
Routes with a priority are matched before deciding.

## Custom error pages

The renderer returns a response, or `null` to use the default one:

```php
$mf('errors')->setRenderer(function ($status, $exception, $request) use ($mf) {
    if ($request->isJson() || 0 === strpos($request->path(), '/api/')) {
        return null; // the default JSON response
    }

    return $mf('response')
        ->setStatus($status)
        ->setBody($mf('template')->render(__DIR__.'/templates/error.html.php', ['status' => $status]));
});
```

When the renderer throws an exception, it's reported and the default response is used.

## Reporting

Exceptions with a 5xx status code are sent to the [[Logger]], the reporter replaces it:

```php
$mf('errors')->setReporter(function ($exception) {
    // send it to an error tracking service
    error_log((string) $exception);
});
```

## APIs

The second argument of `enable()` sets the options:

```php
$mf('errors')->enable(false, [
    'json' => '/api/.*',  // paths always answered in JSON
    'problem' => true,    // Problem Details (RFC 9457)
]);
```

| Option | Description |
| --- | --- |
| `json` | regular expression of the paths always answered in JSON, relative to the base path like the routes (`.*` for all of them), the other ones are JSON when the client asks for it |
| `problem` | JSON responses are [Problem Details](https://www.rfc-editor.org/rfc/rfc9457), see below |

Unknown options throw an `InvalidArgumentException`.

### Problem Details

With the `problem` option, JSON responses follow [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) with the `application/problem+json` content type: the message is the `detail` member, validation errors and debug details are extension members.

```json
{"type":"about:blank","title":"Unprocessable Content","status":422,"detail":"The given data is invalid.","errors":{"age":["The age field must be an integer."]}}
```

## PHP errors

Warnings and notices are converted to `ErrorException`, so they're handled like the other exceptions.
Errors silenced with `@`, excluded by `error_reporting` and deprecations aren't converted.

Fatal errors (like exhausted memory) can't be handled.

## Details

-   a route using the `router:not-found` pattern (see [[Routing]]) replaces the automatic 404 response
-   the methods used by the router are available too: `handle($exception)` returns the response of an exception, `response($status, $exception)` and `notFound($methods)` render error responses, `send($response)` sends them even when the headers have already been sent
