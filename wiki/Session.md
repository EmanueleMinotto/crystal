Crystal provides a small wrapper of the native [sessions](https://www.php.net/manual/en/book.session.php), used by [[Authentication]] and [[CSRF]] too.

```php
$mf(function () use ($mf) {
    $session = $mf('session');

    $session->set('cart', [42]);
    $session->get('cart', []); // [42]
    $session->has('cart');     // true
    $session->remove('cart');

    $session->regenerate();    // new session identifier, values are kept
});
```

The session is started only when needed, with `httponly` cookies, `samesite=Lax` (unless configured differently) and `secure` cookies on HTTPS.
Reading values of a request without the session cookie doesn't start it (the session would be empty), so pages checking only if the user is logged in don't create a session for each visitor.
Since cookies are sent with the headers, use the session **before** sending any output.

From the command line sessions aren't used: values are kept in memory for the current execution.
