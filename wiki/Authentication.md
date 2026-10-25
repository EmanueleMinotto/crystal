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

The session is started only when needed, with `httponly` cookies, `samesite=Lax` (unless configured differently) and `secure` cookies on HTTPS.
The session identifier is regenerated on login and logout to prevent session fixation, so log in users **before** sending any output.

From the command line sessions aren't used: the user is kept in memory for the current execution.

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
| `auth:login`   | `$event->user`                               |
| `auth:logout`  | `$event->user`                               |
| `auth:failed`  | `$event->identifier` (used in `attempt`)     |

```php
$mf('listener')->on('auth:failed', function ($event) use ($mf) {
    $mf('logger')->warning('Login failed for {identifier}', ['identifier' => $event->identifier]);
});
```
