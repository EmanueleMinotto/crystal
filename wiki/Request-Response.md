Route callbacks can receive the request as the `$request` parameter and return a new `$response`:

```php
$mf->post('/api/users', function ($request, $response) {
    $name = $request->input('name');

    return $response->json(['hello' => $name], 201);
});
```

Both are optional: callbacks using `echo`, `header()` and the superglobals keep working.
Outside the parameters, they're available as `$mf('request')` and `$mf('response')`.

## Request

The request reads the superglobals when its methods are called, so it always sees their current values.

```php
// POST /users/42?page=2
$mf->post('/users/{id}', function ($request) {
    $request->method();               // "POST"
    $request->path();                 // "/users/42", without the query string
    $request->param('id');            // "42", parameters of the route
    $request->params();               // ['id' => '42']

    $request->query('page', 1);       // "2", from $_GET, 1 when missing
    $request->input('name');          // from $_POST, or from the JSON body
    $request->input();                // every value of the body
    $request->json();                 // decoded JSON body
    $request->body();                 // raw body
    $request->header('Accept');       // header names are case insensitive
    $request->cookie('theme');
    $request->file('avatar');         // as described in $_FILES
    $request->ip();                   // REMOTE_ADDR, proxies headers aren't trusted
    $request->isSecure();             // HTTPS
    $request->isJson();               // JSON Content-Type
});
```

### Typed values

Values of the query string and of the body (the body takes precedence) can be read with their type: the default value is used when they're missing or invalid, like an array sent as `?email[]=...`.

```php
$request->string('email');            // "" when missing
$request->integer('page', 1);         // 1 when missing or not an integer
$request->boolean('newsletter');      // "1", "true", "on" and "yes" are true
$request->has('coupon');              // true when sent, even if empty
$request->only(['name', 'email']);    // the values sent of these fields
```

### Behind a proxy

Behind a load balancer, a reverse proxy or Cloudflare, `REMOTE_ADDR` is the address of the proxy.
Trust the `X-Forwarded-For` header of your proxies, so `ip()` returns the address of the client (used by the [[Rate Limiting]] and the throttling of the [[Authentication]] too):

```php
$mf('request')->setTrustedProxies(['10.0.0.0/8', '192.168.1.10']);
```

Only the addresses sent by the trusted proxies are used: the header is ignored for the other requests, so clients can't fake their address.

### Validating the input

`validate()` returns the valid values of the query string and of the body, or stops the request with a 422 response (see [[Validation]]):

```php
$mf->post('/api/users', function ($request) {
    $user = $request->validate([
        'email' => ['required', 'email'],
        'name' => ['required', 'string', 'max' => 100],
    ]);

    // ...
});
```

### Attributes

Attributes share values between [[Middleware]] and callbacks:

```php
$locale = function ($request, callable $next) {
    $request->setAttribute('locale', $request->header('Accept-Language') === 'it' ? 'it' : 'en');

    return $next();
};

$mf->get('/', $mf('middleware')->wrap(function ($request) {
    echo 'Locale: '.$request->attribute('locale');
}, $locale));
```

## Response

`$response` (or `$mf('response')`) is a new response each time, its methods can be chained:

```php
// JSON
$mf->get('/api/users', function ($response) {
    return $response->json(['users' => []]);
});

// redirect
$mf->get('/old', function ($response) {
    return $response->redirect('/new', 301);
});

// status, headers, cookies and body
$mf->get('/hello', function ($response) {
    return $response
        ->setStatus(200)
        ->setHeader('X-Custom', 'value')
        ->cookie('theme', 'dark', ['expires' => time() + 3600])
        ->setBody('Hello')
        ->write(' World!');
});

// a file download
$mf->get('/report.csv', function ($response) {
    return $response
        ->setHeader('Content-Type', 'text/csv')
        ->setHeader('Content-Disposition', 'attachment; filename="report.csv"')
        ->setBody("name,total\nAlice,42\n");
});
```

Status, headers and cookies are sent only at the end, so they can be changed at any time.

### Created, accepted and empty responses

```php
$mf->post('/api/users', function ($response) {
    $id = 42; // the id of the new user

    return $response->created(['id' => $id], '/api/users/'.$id); // 201 with the Location header
});

$mf->post('/api/exports', function ($response) {
    return $response->accepted(['status' => 'queued']);           // 202
});

$mf->delete('/api/users/{id}', function ($id, $response) {
    return $response->noContent();                                // 204
});
```

### Methods

| Method | Description |
| --- | --- |
| `status()` / `setStatus($code)` | status code, 200 by default |
| `header($name)` / `setHeader($name, $value)` / `removeHeader($name)` / `headers()` | names are case insensitive |
| `cookie($name, $value, $options)` / `forgetCookie($name)` / `cookies()` | options of [setcookie](https://www.php.net/manual/en/function.setcookie.php), by default `httponly`, `samesite=Lax`, `secure` on HTTPS, path `/` |
| `body()` / `setBody($body)` / `write($content)` | |
| `json($data, $status, $flags)` | JSON body with the `Content-Type` header, `$flags` of [json_encode](https://www.php.net/manual/en/function.json-encode.php) (the `json_flags` setting when `null`) |
| `created($data, $location)` | 201 with the `Location` header, JSON body when `$data` isn't `null` |
| `accepted($data)` | 202, JSON body when `$data` isn't `null` |
| `noContent()` | 204 without body |
| `setEtag($etag, $weak)` / `etag()` / `isNotModified()` | entity tag and 304 responses, see below |
| `redirect($url, $status = 302)` | `Location` header |
| `send()` | sends status, headers, cookies and body, used by the router |

## Returning arrays

Arrays and [`JsonSerializable`](https://www.php.net/manual/en/class.jsonserializable.php) objects returned by a callback are JSON responses, like the ones of `$response->json()`:

```php
$mf->get('/api/users', function () {
    return ['users' => ['Alice', 'Bob']];
});

// {"users":["Alice","Bob"]}
```

Like the other responses, they're sent by the router and they can be changed by [[Middleware]].
Other values (like strings) are ignored.

### JSON flags

The `json_flags` setting contains the default flags of `json_encode()` for every JSON response, error responses and [[Pagination]] included (`0` when not set):

```php
$mf('json_flags', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$mf('response')->json(['name' => 'Zoë']);                // {"name":"Zoë"}
$mf('response')->json(['name' => 'Zoë'], 200, JSON_PRETTY_PRINT); // flags of a single response
```

## Entity tags and 304 responses

`setEtag($etag)` sets the `ETag` header: when the client sends a matching `If-None-Match` header, the response is sent as a 304 without body.
Without argument, the tag is the hash of the body when it's sent.

```php
$mf->get('/api/posts/{id:[0-9]+}', function (int $id, $database, $response) {
    $post = $database->read('posts', $id);
    $response->setEtag($post['updated_at']);

    // the body isn't needed when the client already has it
    if ($response->isNotModified()) {
        return $response;
    }

    return $response->json($post);
});
```

-   only GET and HEAD requests with a 2xx response are answered with a 304
-   `If-None-Match` lists and `*` are supported, tags are compared ignoring the `W/` prefix of weak tags (`setEtag($etag, true)`)
-   `etag()` returns the header value (with quotes), `null` when not set

## Multiple routes

When more routes match, their callbacks are invoked by priority (see [[Routing]]):

-   the output of the callbacks is buffered until the end of the script, so a response can still set its status and headers
-   **the first returned response is sent and the next callbacks aren't invoked**
-   the output of a callback returning a response is prepended to the response body

```php
$mf->get('/hello', function () {
    echo 'Logged';
}, 0);

$mf->get('/hello', function ($response) {
    return $response->setBody('Hello!');
}, 1);

$mf->get('/hello(.*)', function () {
    echo 'never invoked';
}, 2);

// Output: LoggedHello!
```

Since the output is buffered, streaming large responses from a route isn't supported.

## Details

-   any object with a `send()` method can be returned as a response
-   `$request->setBody($body)` replaces the raw body, e.g. in tests

### Malformed JSON

When the `Content-Type` is JSON but the body can't be decoded, `isMalformedJson()` is `true` (empty bodies aren't malformed) and:

-   `validate()` throws an exception with status code 400
-   `input()` throws it too when the [[Errors]] handling is enabled, it returns an empty array otherwise

With the [[Errors]] handling enabled, the client receives a 400 response:

```json
{"error":{"status":400,"title":"Bad Request","message":"Malformed JSON body: Syntax error."}}
```
