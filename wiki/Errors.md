Exceptions thrown by route callbacks and [[middlewares|Middleware]] are converted to responses by the `errors` service.

Errors are rendered as [RFC 9457 Problem Details](https://www.rfc-editor.org/rfc/rfc9457) JSON documents (`application/problem+json`), or as a minimal HTML page when the client accepts `text/html` and doesn't ask for JSON.

```json
{"type":"about:blank","title":"Not Found","status":404,"detail":"User 42 not found."}
```

## HTTP errors

```php
$mf->get('/users/{id:\d+}', function ($id) use ($mf) {
    $user = $mf('database')->read('users', $id);

    if (empty($user)) {
        $mf('errors')->abort(404, sprintf('User %d not found.', $id));
    }

    return $user;
});
```

`abort()` and `create()` accept Problem Details extension members and response headers too:
```php
throw $mf('errors')->create(409, 'Order already paid.', ['order' => 7], ['X-Order' => '7']);
```

Created exceptions extend `RuntimeException`, any exception exposing a `getStatusCode()` method (and optionally `getExtensions()` and `getHeaders()`) is rendered the same way.

## Unexpected errors

Other exceptions are logged with the [[Logger]] and rendered as `500 Internal Server Error` without details. Details and the stack trace can be exposed during development:
```php
$mf('errors')->setDebug(true);
```

## Customization

```php
$errors = $mf('errors');

// always JSON (or "html"), default is "auto"
$errors->setFormat('json');

// custom handler, returning null falls back to the default rendering
$errors->setHandler(function (Throwable $exception, $request) use ($mf) {
    if ($exception instanceof PDOException) {
        return $mf('response')->problem(503, 'Database unavailable.');
    }
});

// error responses can be created directly too
$errors->response(404, 'Not here.');
```
