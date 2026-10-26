A website where users log in with email and password: a layout shared by the pages, a login form protected from [[CSRF]], a private page and the logout.
Users are created with a command line script.

```
my-site/
├── bin/
│   └── create-user.php
├── templates/
│   ├── account.html.php
│   ├── home.html.php
│   ├── layout.html.php
│   └── login.html.php
├── bootstrap.php
├── crystal.php
└── index.php
```

## Configuration

The configuration shared by the website and the command line script:

`bootstrap.php`:

```php
<?php

$mf = require __DIR__.'/crystal.php';

$mf('database')::setPdo(new PDO('sqlite:'.__DIR__.'/site.sqlite'));
$mf('database')->query('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE, password TEXT NOT NULL)');

// users are loaded from the "users" table, they log in with the "email" column
$mf('auth')->useDatabase('users', 'email');

return $mf;
```

## Creating users

`bin/create-user.php`:

```php
<?php

$mf = require __DIR__.'/../bootstrap.php';

// Usage: php bin/create-user.php <name> <email> <password>
$mf(function ($name = null, $email = null, $password = null) use ($mf) {
    if (is_null($password)) {
        fwrite(STDERR, 'Usage: php bin/create-user.php <name> <email> <password>'.PHP_EOL);
        exit(1);
    }

    $mf('database')->create('users', [
        'name' => $name,
        'email' => $email,
        'password' => $mf('auth')->hash($password),
    ]);

    echo 'User '.$email.' created'.PHP_EOL;
});
```

```bash
$ php bin/create-user.php Alice alice@example.com secret
User alice@example.com created
```

## Routes

`index.php`:

```php
<?php

$mf = require __DIR__.'/bootstrap.php';

$mf('errors')->enable();

// renders a page of the templates directory, with the current user and the CSRF service
$page = function ($name, array $data = []) use ($mf) {
    return $mf('template')->render(__DIR__.'/templates/'.$name.'.html.php', $data + [
        'user' => $mf('auth')->user(),
        'csrf' => $mf('csrf'),
    ]);
};

// wraps the callbacks of the pages reserved to the users, guests are sent to the login page
$loginRequired = function (callable $callback) use ($mf) {
    return $mf('auth')->protect($callback, function () use ($mf) {
        return $mf('response')->redirect('/login');
    });
};

$mf->get('/', function () use ($page) {
    echo $page('home');
});

$mf->get('/login', function ($session) use ($page) {
    // the error of the previous attempt, shown once
    $error = $session->get('login_error');
    $session->remove('login_error');

    echo $page('login', ['error' => $error]);
});

$mf->post('/login', $mf('csrf')->protect(function ($request, $response, $auth, $session) {
    if ($auth->attempt((string) $request->input('email', ''), (string) $request->input('password', ''))) {
        return $response->redirect('/account');
    }

    $session->set('login_error', 'Invalid email or password.');

    return $response->redirect('/login');
}));

$mf->get('/account', $loginRequired(function ($auth) use ($page) {
    echo $page('account', ['name' => $auth->user()['name']]);
}));

$mf->post('/logout', $mf('csrf')->protect(function ($auth, $response) {
    $auth->logout();

    return $response->redirect('/');
}));
```

## Templates

`templates/layout.html.php`:

```html
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title><?php echo $this->section('title', 'My site'); ?></title>
    </head>
    <body>
        <nav>
            <a href="/">Home</a>
            <?php if ($user): ?>
                <a href="/account">Account</a>
                <form method="post" action="/logout">
                    <?php echo $csrf->field(); ?>
                    <button>Log out</button>
                </form>
            <?php else: ?>
                <a href="/login">Log in</a>
            <?php endif; ?>
        </nav>

        <?php echo $this->section('content'); ?>
    </body>
</html>
```

`templates/home.html.php`:

```html
<?php $this->layout(__DIR__.'/layout.html.php'); ?>

<h1>Welcome!</h1>
```

`templates/login.html.php`:

```html
<?php $this->layout(__DIR__.'/layout.html.php'); ?>
<?php $this->start('title'); ?>Log in<?php $this->stop(); ?>

<h1>Log in</h1>

<?php if ($error): ?>
    <p class="error"><?php echo $this->e($error); ?></p>
<?php endif; ?>

<form method="post" action="/login">
    <?php echo $csrf->field(); ?>
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>
    <button>Log in</button>
</form>
```

`templates/account.html.php`:

```html
<?php $this->layout(__DIR__.'/layout.html.php'); ?>
<?php $this->start('title'); ?>Your account<?php $this->stop(); ?>

<h1>Hello <?php echo $this->e($name); ?>!</h1>
```

## How it works

-   `$mf('csrf')->protect()` refuses the forms sent without the token of `$csrf->field()` with a 403 response, so other websites can't log users in or out
-   `attempt()` checks the password hash and stores the user identifier in the [[Session]], regenerating the session identifier
-   `protect()` invokes the callback only for logged in users, the other ones receive the response of the second callback: a redirect to the login page
-   the login error is stored in the session before the redirect and removed when shown, like a [flash message](Session#flash-messages)

## Going further

-   limit the failed logins with the [throttling](Authentication#throttling)
-   keep the users logged in with [remember me](Authentication#remember-me)
-   allow some actions only to some users with [gates](Authentication#gates)
