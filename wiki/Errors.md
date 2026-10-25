Crystal can handle errors for you: exceptions become error responses, PHP warnings become exceptions and requests not matching any route receive a 404 (or 405) response.
Error handling is disabled by default, enable it as soon as possible:

```php
$mf = require_once('crystal.php');

$mf('errors')->enable();      // production
$mf('errors')->enable(true);  // debug mode: exceptions details are shown, never use it in production
```

For example using the [[Configuration]]:

```php
$mf('errors')->enable((bool) $mf('config')->env('APP_DEBUG', false));
```

## Exceptions

When a route callback throws an exception:

-   the output of the route callbacks is discarded and an error response is sent, the next route callbacks aren't invoked
-   exceptions with a 5xx status code are reported, by default to the [[Logger]]
-   the response is JSON when the client asks for it (`Accept` header or JSON body), an HTML page otherwise

Exceptions thrown outside the route callbacks are handled too.

In debug mode the response contains the exception class, message, file, line and stack trace.
In production only the status is shown, plus the message of 4xx errors thrown with `abort()`.

```json
{"error":{"status":500,"title":"Internal Server Error"}}
```

## HTTP errors

`abort()` stops the request with an error response:

```php
$mf('/posts/(?P<id>[0-9]+)', function ($id) use ($mf) {
    $post = $mf('database')->read('posts', $id);

    if (is_null($post)) {
        $mf('errors')->abort(404, 'Post not found');
    }

    // ...
});

$mf('errors')->abort(429, 'Slow down', ['Retry-After' => 60]);
```

Any exception with a `getStatusCode()` method (and optionally `getHeaders()`), like the Symfony HTTP exceptions, is handled the same way.

## Not found and method not allowed

When no route matches the request, a 404 response is sent.
When some routes match the path but not the request method, a 405 response is sent, with the `Allow` header when the methods are simple lists like `GET|POST`.

Routes with a priority are matched before deciding, and a route defined with `router:not-found` (see [[CGI Usage]]) replaces the automatic 404 response.

## PHP errors

Warnings and notices are converted to `ErrorException`, so they're handled like the other exceptions.
Errors silenced with `@`, excluded by `error_reporting` and deprecations aren't converted.

Fatal errors (like exhausted memory) can't be handled.

## Customization

```php
$errors = $mf('errors');

// custom pages: return a response, or null to use the default one
$errors->setRenderer(function ($status, $exception, $request) use ($mf) {
    if (404 === $status) {
        return $mf('response')
            ->setStatus(404)
            ->setBody($mf('template')->render('404.html.php'));
    }
});

// custom reporting of the exceptions with a 5xx status code
$errors->setReporter(function ($exception) {
    // send it to an error tracking service
});
```

When the renderer throws an exception, it's reported and the default response is used.

The methods used by the router are available too: `handle($exception)` returns the response of an exception, `response($status, $exception)` and `notFound($methods)` render error responses, `send($response)` sends them even when the headers have already been sent.
