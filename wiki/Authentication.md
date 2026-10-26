The `auth` service logs users in with native [sessions](https://www.php.net/manual/en/book.session.php) and [password hashing](https://www.php.net/manual/en/book.password.php), protects routes and authorizes actions with gates.
Users can be arrays or objects stored anywhere: only the user identifier is stored in the session.

```php
$mf('database')::setPdo(new PDO('sqlite:'.__DIR__.'/app.sqlite'));
$mf('auth')->useDatabase('users', 'email'); // the "users" table, with "id", "email" and "password" columns

$mf->post('/login', function ($request, $response, $auth) {
    if (!$auth->attempt($request->input('email', ''), $request->input('password', ''))) {
        return $response->redirect('/login?failed=1');
    }

    return $response->redirect('/account');
});

$mf->get('/account', $mf('auth')->protect(function ($auth) {
    echo 'Hello '.htmlspecialchars($auth->user()['email']);
}));
```

The [website with login](Cookbook-Website-With-Login) of the [[Cookbook]] is a complete example, with the forms and [[CSRF]] protection.

## Users

### From the database

With the [[Database]] service configured, users are loaded from a table:

```php
$auth = $mf('auth');

$auth->useDatabase('users', 'email');       // table and column used to log in
$auth->setFields('uid', 'pass');            // when the columns aren't "id" and "password"
```

Passwords are stored hashed:

```php
$mf('database')->create('users', [
    'email' => 'alice@example.com',
    'password' => $mf('auth')->hash('secret'), // password_hash() with PASSWORD_DEFAULT
]);
```

Hashes created with outdated options are updated automatically after a successful login.

### From anywhere else

Two callbacks load the users, they must return `null` when the user doesn't exist:

```php
$mf('auth')->setProvider(
    // used to load the user of the session
    function ($id) { return find_user_by_id($id); },
    // used by attempt() to find the user by email, username, ...
    function ($email) { return find_user_by_email($email); }
);

// optional: store the hashes updated after a login
$mf('auth')->onRehash(function ($user, $hash) {
    // store $hash as the new user password
});
```

## Logging in and out

```php
$auth = $mf('auth');

$auth->attempt($email, $password); // true when the credentials are valid, the user is logged in
$auth->login($user);               // log in without checking credentials, e.g. after a registration
$auth->logout();

$auth->check(); // true when authenticated
$auth->user();  // the current user or null
$auth->id();    // the current user identifier or null

$auth->verify('secret', $hash);    // password_verify()
```

```php
$mf->post('/logout', function ($auth, $response) {
    $auth->logout();

    return $response->redirect('/');
});
```

The session identifier (and the [[CSRF]] token) is regenerated on login and logout to prevent session fixation.
From the command line sessions aren't used: the user is kept in memory for the current execution.

## Protecting routes

`protect()` wraps a route callback, invoked only for authenticated users.
Guests receive an empty 401 response, or the response of the second callback:

```php
$auth = $mf('auth');

// APIs: 401 for guests
$mf->get('/api/me', $auth->protect(function ($auth) {
    return ['email' => $auth->user()['email']];
}));

// websites: guests are sent to the login page
$mf->get('/account', $auth->protect(function () {
    echo 'Your account';
}, function () use ($mf) {
    return $mf('response')->redirect('/login');
}));
```

Route parameters and injected services still work with protected routes.

### Middleware

With [[Middleware]], `$auth->middleware()` returns a 401 response for guests and `$auth->middleware('post:edit')` a 403 response when the gate denies, in this case the gate receives the current user and the request:

```php
$auth->define('post:edit', function ($user, $request) use ($mf) {
    $post = $mf('database')->read('posts', $request->param('id'));

    return $post && $post['author_id'] === $user['id'];
});

$mf->get('/posts/{id:[0-9]+}/edit', $mf('middleware')->wrap(function (int $id) {
    echo 'Editing post '.$id;
}, $auth->middleware('post:edit')));
```

A middleware sending the guests to the login page:

```php
$loginRequired = function ($request, callable $next) use ($mf) {
    return $mf('auth')->check() ? $next() : $mf('response')->redirect('/login');
};

$mf('router')->group('/account', function ($route) {
    $route->get('', $showAccount);
    $route->get('/orders', $listOrders);
}, [$loginRequired]);
```

## Gates

Gates are callbacks receiving the current user and the arguments passed to `can()`.
Guests are never authorized, using an undefined gate throws an exception.

```php
$auth->define('admin', function ($user) {
    return 'admin' === $user['role'];
});

$auth->define('post:edit', function ($user, $post) {
    return $user['id'] === $post['author_id'];
});

if ($auth->can('post:edit', $post)) {
    // ...
}
```

`protect()` can check a gate too (third argument): the gate receives the arguments of the route callback and, if the user isn't authorized, the status code is 403.

```php
$auth->define('team:member', function ($user, $team) {
    return is_team_member($user, $team);
});

$mf->get('/teams/{team}', $auth->protect(function ($team) {
    echo 'Team '.$team;
}, null, 'team:member'));
```

## Throttling

Failed login attempts can be limited for each identifier and IP address, using the [[Cache]] service:

```php
$mf('cache')::setDriver('filename', __DIR__.'/cache');

$auth->throttle(5, 60); // 5 failed attempts, then locked for 60 seconds

$mf->post('/login', function ($request, $auth) {
    if ($auth->attempt($request->input('email', ''), $request->input('password', ''))) {
        // ...
    }

    $seconds = $auth->lockedFor($request->input('email', ''));

    echo $seconds > 0
        ? "Too many attempts, retry in $seconds seconds"
        : 'Invalid credentials';
});
```

While locked even valid credentials are refused, a successful login resets the attempts.
To limit other requests, like the ones of an API, see [[Rate Limiting]].

## Remember me

Users can be remembered after the session ends, using a cookie:

```php
$auth->rememberWithDatabase(); // table "crystal_remember_tokens", created on first use

$auth->attempt($email, $password, true); // true: remember the user
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

```bash
curl -H 'Authorization: Bearer 3f9c...' https://example.com/api/me
```

When a request has a bearer token, the session and the remember-me cookie aren't used, even if the token is invalid.

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

    return $event;
});
```
