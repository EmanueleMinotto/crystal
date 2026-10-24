## Request

The current request is available as the `request` service, data is read from superglobals and `php://input`.

```php
$mf->post('/orders/{id}', function () use ($mf) {
    $request = $mf('request');

    $request->method();                 // "POST"
    $request->path();                   // "/orders/7"
    $request->uri();                    // "/orders/7?expand=items"
    $request->param('id');              // "7", route parameter
    $request->query('expand');          // "items"
    $request->query('page', 1);         // default value
    $request->header('X-Trace-Id');     // case insensitive
    $request->body();                   // raw body
    $request->input('customer.name');   // JSON or form body, dot notation
    $request->all();                    // body and query string parameters
    $request->bearerToken();            // from "Authorization: Bearer <token>"
    $request->ip();                     // REMOTE_ADDR, proxies aren't trusted
    $request->isJson();                 // JSON Content-Type
    $request->wantsJson();              // JSON Content-Type or Accept
    $request->accepts('text/html');
});
```

JSON bodies are decoded automatically, a malformed JSON body results in a `400 Bad Request` [[error|Errors]]. Form bodies sent with `PUT`, `PATCH` or `DELETE` are parsed too.

Middlewares can share values with callbacks using attributes:
```php
$request->setAttribute('user', $user);
$request->attribute('user');
```

Input can be validated with the [[Validator]]:
```php
$data = $mf('request')->validate(['email' => 'required|email']);
```

## Response

The `response` service creates immutable response objects, using the [PSR-7](https://www.php-fig.org/psr/psr-7/) method names. Callbacks and middlewares can return them.

```php
$response = $mf('response');

$response->json(['id' => 1], 200, ['X-Custom' => 'value']);
$response->text('Hello');
$response->html('<h1>Hello</h1>');
$response->created('/users/1', ['id' => 1]); // 201 with Location header
$response->noContent();                      // 204
$response->redirect('/login', 303);
$response->problem(404, 'User not found');   // RFC 9457 Problem Details
$response->create('body', 200, ['Content-Type' => 'text/csv']);
```

```php
$mf->get('/users/{id:\d+}', function ($id) use ($mf) {
    return $mf('response')
        ->json(['id' => (int) $id])
        ->withStatus(200)
        ->withHeader('X-Version', '1')
        ->withAddedHeader('Vary', 'Accept');
});
```

Available methods: `getStatusCode`, `withStatus`, `getHeaders`, `hasHeader`, `getHeader`, `getHeaderLine`, `withHeader`, `withAddedHeader`, `withoutHeader`, `getBody`, `withBody` and `json` (decoded body, useful in tests).

PSR-7 responses created by other libraries can be returned by callbacks too.

## HTTP caching

```php
$response->json($data)
    ->withCache(3600)               // Cache-Control: public, max-age=3600
    ->withCache(60, false)          // Cache-Control: private, max-age=60
    ->withCache(0)                  // Cache-Control: no-store
    ->withEtag('v1')                // ETag: "v1"
    ->withLastModified($timestamp); // Last-Modified: <HTTP date>
```

The `conditional()` [[middleware|Middleware]] adds an ETag (hash of the body) to successful `GET` and `HEAD` responses and replies with `304 Not Modified` when the `If-None-Match` or `If-Modified-Since` request headers match:
```php
$mf->middleware($mf('response')->conditional());
```
