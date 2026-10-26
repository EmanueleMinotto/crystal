The `session` service is a small wrapper of the native [sessions](https://www.php.net/manual/en/book.session.php), used by [[Authentication]] and [[CSRF]] too.

```php
$mf->get('/visits', function ($session) {
    $visits = $session->get('visits', 0) + 1;
    $session->set('visits', $visits);

    echo 'Visits: '.$visits;
});
```

## Methods

```php
$session = $mf('session');

$session->set('cart', [42]);
$session->get('cart', []); // [42], or [] when missing
$session->has('cart');     // true
$session->remove('cart');

$session->regenerate();    // new session identifier, values are kept
```

## Flash messages

`flash()` stores a value for the current request and the next one, like a message shown after a redirect:

```php
$mf->post('/settings', function ($request, $response, $session) {
    // save the settings...

    $session->flash('notice', 'Settings saved.');

    return $response->redirect('/settings');
});

$mf->get('/settings', function ($session) {
    $notice = $session->getFlash('notice'); // null on the next pages

    if (!is_null($notice)) {
        echo '<p class="notice">'.htmlspecialchars($notice).'</p>';
    }

    echo '<form method="post">...</form>';
});
```

Flash values are removed when the session is used by a request after the next one, sessions without flash values aren't changed.

### The values of a form

When a form isn't valid, `flashInput()` keeps its values for the next request, so the form can be shown again with them (fields containing `password` aren't stored):

```php
$mf->post('/contact', function ($request, $response, $session, $validator) {
    $errors = $validator->check($request->input(), ['email' => ['required', 'email']]);

    if (!empty($errors)) {
        $session->flash('errors', $errors);
        $session->flashInput($request->input());

        return $response->redirect('/contact');
    }

    // send the message...
});
```

```html
<input name="email" value="<?php echo $this->e($session->old('email', '')); ?>">
```

### Reading a value once

`pull()` returns a value and removes it:

```php
$cart = $session->pull('cart', []);
```

## How the session works

-   the session is started only when needed, with `httponly` cookies, `samesite=Lax` (unless configured differently) and `secure` cookies on HTTPS
-   reading values of a request without the session cookie doesn't start it (the session would be empty), so pages checking only if the user is logged in don't create a session for each visitor
-   cookies are sent with the headers: the output of the routes is buffered, but don't print anything **before** the routes (see the [[F.A.Q.|F.A.Q.]])
-   from the command line sessions aren't used: values are kept in memory for the current execution

The native session settings still apply, e.g. to change the name of the cookie:

```php
ini_set('session.name', 'my_app');
ini_set('session.gc_maxlifetime', '7200'); // seconds
```
