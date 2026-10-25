Crystal protects forms from [Cross-Site Request Forgery](https://owasp.org/www-community/attacks/csrf) using a random token stored in the [[Session]].

## Forms

```php
$csrf = $mf('csrf');

$mf('/posts/new', function () use ($csrf) {
    echo '<form method="post" action="/posts">';
    echo $csrf->field(); // <input type="hidden" name="_token" value="...">
    echo '<button>Save</button></form>';
});
```

For JavaScript requests the token can be sent in the `X-CSRF-Token` header:

```php
echo '<meta name="csrf-token" content="' . $csrf->token() . '">';
```

## Protecting routes

`protect` wraps a route callback, invoked only when the token is valid (otherwise the status code is 403):

```php
$mf('/posts', $csrf->protect(function () {
    // save the post
}), 'POST');

// with a custom response
$mf('/posts', $csrf->protect($callback, function () {
    echo 'The page expired, please retry.';
}), 'POST');

// together with authentication
$mf('/posts/(?P<id>[0-9]+)', $csrf->protect($auth->protect(function ($id) {
    // update the post
})), 'POST');
```

Safe methods (`GET`, `HEAD`, `OPTIONS`) and requests authenticated by a bearer token (see [[Authentication]]) aren't checked.

With [[Middleware]], `$csrf->middleware()` returns a 403 response for invalid tokens:

```php
$mf('/posts', $mf('middleware')->wrap($callback, $csrf->middleware(), $auth->middleware()), 'POST');
```

## Manual checks

```php
$csrf->check();         // token read from the `_token` field or the `X-CSRF-Token` header
$csrf->check($token);   // explicit token
$csrf->regenerate();    // new token, done automatically on login and logout
$csrf->setFieldName('csrf_token');
```
