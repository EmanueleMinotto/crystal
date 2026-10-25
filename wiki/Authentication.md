Crystal provides authentication based on native [sessions](https://www.php.net/manual/en/book.session.php) and [password hashing](https://www.php.net/manual/en/book.password.php), plus gates to authorize actions.

Users are loaded through callbacks, so they can be arrays or objects stored anywhere: only the user identifier is stored in the session.

## Configuring the provider

```php
$mf(function () use ($mf) {
    $auth = $mf('auth');

    $auth->setProvider(
        // used to load the user from the session
        function ($id) { return find_user_by_id($id); },
        // used by attempt() to find the user by email, username, ...
        function ($email) { return find_user_by_email($email); }
    );

    // when users have different fields (default: "id" and "password")
    $auth->setFields('uid', 'pass');
});
```

Both callbacks must return `null` when the user doesn't exist.

### Using the database

When the [[Database]] service is configured, users can be loaded from a table:

```php
$auth->useDatabase('users', 'email'); // table and column used to log in
```

Password hashes created with outdated options are updated automatically after a successful login.
Using `setProvider` the same can be done with:

```php
$auth->onRehash(function ($user, $hash) {
    // store $hash as the new user password
});
```

## Logging in and out

```php
$auth = $mf('auth');

$hash = $auth->hash('secret'); // password_hash() with PASSWORD_DEFAULT
$auth->verify('secret', $hash); // true

if ($auth->attempt($_POST['email'], $_POST['password'])) {
    // logged in
}

$auth->login($user); // log in without checking credentials, i.e. after a registration
$auth->logout();

$auth->check(); // true when authenticated
$auth->user();  // the current user or null
$auth->id();    // the current user identifier or null
```

The user identifier is stored using the [[Session]] service.
The session identifier (and the [[CSRF]] token) is regenerated on login and logout to prevent session fixation, so log in users **before** sending any output.

From the command line sessions aren't used: the user is kept in memory for the current execution.

## Throttling

Failed login attempts can be limited for each identifier and IP address, using the [[Cache]] service:

```php
$mf('cache')::setDriver('filename', '/path/to/cache');

$auth->throttle(5, 60); // 5 failed attempts, then locked for 60 seconds

if (!$auth->attempt($_POST['email'], $_POST['password'])) {
    $seconds = $auth->lockedFor($_POST['email']);

    echo $seconds > 0
        ? "Too many attempts, retry in $seconds seconds"
        : 'Invalid credentials';
}
```

While locked even valid credentials are refused, a successful login resets the attempts.
To limit other requests, like the ones of an API, see [[Rate Limiting]].

## Remember me

Users can be remembered after the session ends, using a cookie:

```php
$auth->rememberWithDatabase(); // table "crystal_remember_tokens", created on first use

$auth->attempt($_POST['email'], $_POST['password'], true);
// or
$auth->login($user, true);
```

The cookie contains a selector, used to find the token, and a validator, stored hashed: a leaked table can't be used to log in.
Tokens are used once: when the user is logged in by the cookie, the token is replaced by a new one. Expired and invalid tokens are refused, logout removes the token of the current device.

Using a different storage:

```php
$auth->setRememberProvider(
    function ($selector, $hash, $userId, $expires) { /* store the token */ },
    function ($selector) { /* return ['hash' => ..., 'user_id' => ..., 'expires' => ...] or null */ },
    function ($selector) { /* remove the token */ },
    2592000 // lifetime in seconds, 30 days by default
);
```

## API tokens

Requests with an `Authorization: Bearer <token>` header can be authenticated without sessions:

```php
$token = $auth->generateToken(); // give it to the user once
$hash = $auth->hashToken($token); // and store only the hash

// with the database: the third argument is the column containing the hashed tokens
$auth->useDatabase('users', 'email', 'api_token');

// or with a callback
$auth->setTokenProvider(function ($token) use ($auth) {
    return find_user_by_api_token($auth->hashToken($token));
});

$auth->user();     // the user of the token
$auth->viaToken(); // true
```

When a request has a bearer token, the session and the remember-me cookie aren't used, even if the token is invalid.

## Protecting routes

`protect` wraps a route callback, invoked only for authenticated users:

```php
$auth = $mf('auth');

// guests get a 401 status code
$mf('/account', $auth->protect(function () {
    echo 'Your account';
}));

// or a custom response
$mf('/account', $auth->protect(function () {
    echo 'Your account';
}, function () {
    header('Location: /login');
}));
```

Named subpatterns still work with protected routes.

### Middleware

With [[Middleware]], `$auth->middleware()` returns a 401 response for guests, `$auth->middleware('post:edit')` a 403 response when the gate denies.
In this case the gate receives the current user and the request:

```php
$auth->define('post:edit', function ($user, $request) {
    return is_post_author($user, $request->param('id'));
});

$mf('/posts/(?P<id>[0-9]+)/edit', $mf('middleware')->wrap(function ($id) {
    echo 'Editing post ' . $id;
}, $auth->middleware('post:edit')));
```

## Gates

Gates are callbacks receiving the current user and the arguments passed to `can`.
Guests are never authorized, using an undefined gate throws an exception.

```php
$auth->define('post:edit', function ($user, $post) {
    return $user['id'] === $post['author_id'];
});

if ($auth->can('post:edit', $post)) {
    // ...
}
```

A gate can be checked by `protect` too: it receives the same arguments of the route callback and, if the user isn't authorized, the status code is 403.

```php
$auth->define('post:edit', function ($user, $id) {
    return is_post_author($user, $id);
});

$mf('/posts/(?P<id>[0-9]+)/edit', $auth->protect(function ($id) {
    echo 'Editing post ' . $id;
}, null, 'post:edit'));
```

## Events

When the [[Event Listener]] is available, these events are dispatched:

| Event          | Properties                                   |
| -------------- | -------------------------------------------- |
| `auth:login`   | `$event->user` (remember-me logins too)      |
| `auth:logout`  | `$event->user`                               |
| `auth:failed`  | `$event->identifier` (used in `attempt`)     |
| `auth:lockout` | `$event->identifier` (used in `attempt`)     |

```php
$mf('listener')->on('auth:failed', function ($event) use ($mf) {
    $mf('logger')->warning('Login failed for {identifier}', ['identifier' => $event->identifier]);
});
```
