As visible in this wiki's home, the CGI usage is pretty simple, here how to print a "Hello World!" phrase in the browser:

```php
$mf = require_once('crystal.php');

$mf('/', function () {
    echo 'Hello World!';
});
```

second argument must be [callable](http://php.net/manual/en/language.types.callable.php), so you can use
```php
$mf('/', function () { /* ... */ });
$mf('/', 'function');
$mf('/', array($object, 'method'));
$mf('/', array('Class', 'staticMethod'));
$mf('/', 'Class::staticMethod');
$mf('/', $object); // Used with __invoke
```

HTTP request methods are the third parameter and are controlled using a regular expression.
```php
$mf('/', function () {
    echo 'Hello ' . $_POST['name'] . '!';
}, 'POST');
```

```php
$mf('/', function () {
    echo 'Hello ' . $_SERVER['REQUEST_METHOD'] . '!';
}, 'GET|POST');
```

Routes are defined using a regular expression too.
```php
$mf('/hello/world', function () {
    echo 'Hello ';
});
$mf('/hello(.*)', function () {
    echo 'World!';
});
// Output: Hello World!
```

Use [named subpatterns](http://php.net/manual/en/regexp.reference.subpatterns.php) to define parameters order.
```php
$mf('/(?P<user>[^\/]+)/(?P<verb>[^\/]+)', function ($verb, $user) {
    echo 'Output: ' . $verb . ' ' . $user . '!';
});

// http://localhost/bob/hello
// Output: hello bob!
```

**Attention:** don't forget to configure your [[webserver|Webserver Configuration]]!

## Priorities

Priority is used to set callbacks order, each function is called using highest priority 0 but you can set it passing a not negative integer or a not negative float number.

```php
// $mf('/', function () { echo 'A'; });
$mf('/', function () { echo 'A'; }, 'GET', 0);
$mf('/', function () { echo 'B'; }, 'GET', 1);

// Output: AB
```

```php
$mf('/', function () { echo 'A'; }, 'GET', 1);
$mf('/', function () { echo 'B'; }, 'GET', 0);

// Output: BA
```

In command line usage priority is the second parameter.

To break callbacks chain use the [`exit()`](http://it2.php.net/manual/en/function.exit.php) function.


## Page not found

The [HTTP 404 error](https://en.wikipedia.org/wiki/HTTP_404) is defined as a regular expression too and is matched if no one of **currently** defined regular expressions match current URL.
To retrieve the 404 regular expression call the function with the argument `router:not-found` (available only if not in CLI mode).
```php
$mf('/', function () {
    echo 'Hello World!';
});

$mf($mf('router:not-found'), function () {
    echo 'Error 404: Page not Found';
});
```
