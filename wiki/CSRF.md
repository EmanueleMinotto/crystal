The `csrf` service protects forms from [Cross-Site Request Forgery](https://owasp.org/www-community/attacks/csrf): another website can't send a form to your application on behalf of your users, because it doesn't know the random token stored in their [[Session]].

```php
$mf->get('/posts/new', function ($csrf) {
    echo '<form method="post" action="/posts">';
    echo $csrf->field(); // <input type="hidden" name="_token" value="...">
    echo '<input name="title"><button>Save</button>';
    echo '</form>';
});

$mf->post('/posts', $mf('csrf')->protect(function ($request, $database) {
    $database->create('posts', $request->validate(['title' => ['required', 'string']]));

    echo 'Saved!';
}));
```

## Protecting routes

`protect()` wraps a route callback, invoked only when the token is valid, otherwise the status code is 403:

```php
$csrf = $mf('csrf');

$mf->post('/posts', $csrf->protect($save));

// with a custom response
$mf->post('/posts', $csrf->protect($save, function () {
    echo 'The page expired, please retry.';
}));

// together with the authentication
$mf->post('/posts/{id:[0-9]+}', $csrf->protect($mf('auth')->protect($update)));
```

With [[Middleware]], `$csrf->middleware()` returns a 403 response for invalid tokens:

```php
$mf->post('/posts', $mf('middleware')->wrap($save, $mf('csrf')->middleware(), $mf('auth')->middleware()));
```

Safe methods (`GET`, `HEAD`, `OPTIONS`) and requests authenticated by a bearer token (see [[Authentication]]) aren't checked.

## In templates

```html
<form method="post" action="/comments">
    <?php echo $csrf->field(); ?>
    <textarea name="text"></textarea>
    <button>Send</button>
</form>
```

Pass the service to the template, e.g. `$template->render($file, ['csrf' => $csrf])`.

## JavaScript requests

The token can be sent in the `X-CSRF-Token` header:

```html
<meta name="csrf-token" content="<?php echo $this->e($csrf->token()); ?>">

<script>
fetch('/api/comments', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content,
    },
    body: JSON.stringify({text: 'Hello'}),
});
</script>
```

## Manual checks

```php
$csrf->check();         // token read from the `_token` field or the `X-CSRF-Token` header
$csrf->check($token);   // explicit token
$csrf->token();         // the token of the session
$csrf->regenerate();    // new token, done automatically on login and logout
$csrf->setFieldName('csrf_token');
```
