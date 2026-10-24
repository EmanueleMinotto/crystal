The `auth` service provides [JSON Web Tokens](https://www.rfc-editor.org/rfc/rfc7519) helpers and authentication [[middlewares|Middleware]]. Authenticated users are stored in the `user` request attribute, failures result in a `401 Unauthorized` [[error|Errors]] with the `WWW-Authenticate` header.

## JSON Web Tokens

HMAC algorithms are supported: `HS256` (default), `HS384` and `HS512`.

```php
$auth = $mf('auth');

// claims, secret, seconds before expiration (0 to skip "exp"), algorithm
$token = $auth->encode(['sub' => '42', 'role' => 'admin'], $secret, 3600);

// throws UnexpectedValueException if the token is invalid or expired
$claims = $auth->decode($token, $secret, ['HS256'], $leeway = 0);
```

## Bearer tokens

```php
$mf->group('/api', function ($mf) {
    $mf->get('/me', function () use ($mf) {
        return $mf('request')->attribute('user'); // JWT claims
    });
}, ['middleware' => [$mf('auth')->bearer($secret)]]);
```

Opaque tokens can be verified using a closure returning the user or `null`:
```php
$mf('auth')->bearer(function ($token, $request) use ($mf) {
    return $mf('database')->read('users', $token, 'api_token') ?: null;
});
```

## API keys

```php
// valid keys indexed by client name, or a list of keys
$mf('auth')->apiKey(['billing' => 'key-1', 'reports' => 'key-2']);

// custom header and verification
$mf('auth')->apiKey(function ($key, $request) {
    return findClientByKey($key);
}, 'X-Client-Key');
```

Keys are compared using `hash_equals()` to prevent timing attacks.
