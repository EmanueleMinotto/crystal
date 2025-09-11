Settings work like dependency injection, but you can't pass anything [`callable`](http://php.net/manual/en/language.types.callable.php).
```php
$mf('pi', M_PI);

$mf('/', function () use ($mf) {
    var_dump($mf('pi'));
});

// Output: 3.1415926535898
```