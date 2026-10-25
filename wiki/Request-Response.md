Route callbacks can read the request from `$mf('request')` and return a response created with `$mf('response')`.
Both are optional: callbacks using `echo`, `header()` and the superglobals keep working.

## Request

`$mf('request')` reads the values from the superglobals when requested, so changes made to them are always visible.

```php
$mf('/users/(?P<id>[0-9]+)', function () use ($mf) {
    $request = $mf('request');

    $request->method();               // "POST"
    $request->path();                 // "/users/42", without the query string
    $request->param('id');            // "42", named route parameters
    $request->params();               // ['id' => '42']

    $request->query('page', 1);       // $_GET
    $request->input('name');          // $_POST, or the JSON body when the Content-Type is JSON
    $request->validate(['name' => ['required']]); // validated query string and body values, see Validation
    $request->json();                 // decoded JSON body
    $request->body();                 // raw body
    $request->header('Accept');       // header names are case insensitive
    $request->cookie('theme');
    $request->file('avatar');         // as described in $_FILES
    $request->ip();                   // REMOTE_ADDR, proxies headers aren't trusted
    $request->isSecure();             // HTTPS
    $request->isJson();

    // values shared between middlewares and callbacks
    $request->setAttribute('locale', 'it');
    $request->attribute('locale');
}, 'POST');
```

`setBody()` replaces the raw body, i.e. in tests.

### Malformed JSON

When the `Content-Type` is JSON but the body can't be decoded, `isMalformedJson()` is `true` (empty bodies aren't malformed) and:

-   `validate()` throws an exception with status code 400
-   `input()` throws it too when the [[Errors]] handling is enabled, it returns an empty array otherwise

With the [[Errors]] handling enabled, the client receives a 400 response:

```json
{"error":{"status":400,"title":"Bad Request","message":"Malformed JSON body: Syntax error."}}
```

## Response

`$mf('response')` returns a new response each time, its methods can be chained:

```php
$mf('/api/users', function () use ($mf) {
    return $mf('response')->json(['users' => []]);
});

$mf('/old', function () use ($mf) {
    return $mf('response')->redirect('/new', 301);
});

$mf('/hello', function () use ($mf) {
    return $mf('response')
        ->setStatus(201)
        ->setHeader('X-Custom', 'value')
        ->cookie('theme', 'dark', ['expires' => time() + 3600])
        ->setBody('Hello')
        ->write(' World!');
});
```

| Method | Description |
| --- | --- |
| `status()` / `setStatus($code)` | status code, 200 by default |
| `header($name)` / `setHeader($name, $value)` / `removeHeader($name)` / `headers()` | names are case insensitive |
| `cookie($name, $value, $options)` / `forgetCookie($name)` / `cookies()` | options of [setcookie](https://www.php.net/manual/en/function.setcookie.php), by default `httponly`, `samesite=Lax`, `secure` on HTTPS, path `/` |
| `body()` / `setBody($body)` / `write($content)` | |
| `json($data, $status)` | JSON body with the `Content-Type` header |
| `redirect($url, $status = 302)` | `Location` header |
| `send()` | sends status, headers, cookies and body, used by the router |

Status, headers and cookies are sent only at the end, so they can be changed at any time.

## Multiple routes

When more routes match, their callbacks are invoked by priority (see [[CGI Usage]]):

-   the output of the callbacks is buffered until the end of the script, so a response can still set its status and headers
-   **the first returned response is sent and the next callbacks aren't invoked**
-   the output of a callback returning a response is prepended to the response body

```php
$mf('/hello', function () {
    echo 'Logged';
}, 'GET', 0);

$mf('/hello', function () use ($mf) {
    return $mf('response')->setBody('Hello!');
}, 'GET', 1);

$mf('/hello(.*)', function () {
    echo 'never invoked';
}, 'GET', 2);

// Output: LoggedHello!
```

Since the output is buffered, streaming large responses from a route isn't supported.

Any object with a `send()` method can be returned as a response.

## Returning arrays

Arrays and [`JsonSerializable`](https://www.php.net/manual/en/class.jsonserializable.php) objects returned by a callback are JSON responses, like the ones of `$mf('response')->json()`:

```php
$mf->get('/api/users', function () {
    return ['users' => ['Alice', 'Bob']];
});
```

Like the other responses, they're sent by the router, they can be changed by [[Middleware]] and the next callbacks aren't invoked.
Other values (like strings) are ignored.

## Injected services

Callbacks can receive the request, a new response and the other services as parameters with the same name, without `use ($mf)` (see [[CGI Usage]]):

```php
$mf->post('/api/users', function ($request, $response) {
    $data = $request->validate(['name' => ['required', 'string']]);

    return $response->json($data, 201);
});
```

## Middlewares

Route callbacks can be wrapped by [[Middleware]].
